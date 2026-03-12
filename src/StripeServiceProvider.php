<?php

namespace Yared\SmartStripe;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Yared\SmartStripe\Http\Controllers\WebhookController;
use Yared\SmartStripe\Logging\PaymentLogger;
use Yared\SmartStripe\Security\FraudDetector;
use Yared\SmartStripe\Services\StripePayment;
use Yared\SmartStripe\Services\WebhookHandler;

class StripeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FraudDetector::class);
        $this->app->singleton(PaymentLogger::class);

        $this->app->bind(StripePayment::class, function ($app) {
            return new StripePayment(
                $app,
                $app->make(FraudDetector::class),
                $app->make(PaymentLogger::class)
            );
        });

        $this->app->singleton(WebhookHandler::class, function ($app) {
            return new WebhookHandler($app);
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../config/stripe-smart.php',
            'stripe-smart'
        );
    }

    protected function registerWebhookListeners(): void
    {
        $this->app->booted(function () {
            $handler = $this->app->make(WebhookHandler::class);

            $handler->listen('checkout.session.completed', [$this, 'handleCheckoutCompleted']);

            $listeners = config('stripe-smart.webhook_listeners', []);
            foreach ($listeners as $event => $callback) {
                if (is_string($callback) && class_exists($callback)) {
                    $handler->listen($event, fn ($e) => $this->app->make($callback)->handle($e));
                } elseif (is_callable($callback)) {
                    $handler->listen($event, $callback);
                }
            }
        });
    }

    public function handleCheckoutCompleted($event): void
    {
        $session = $event->data->object;
        $metadata = $session->metadata ?? null;
        $metaArray = $metadata && method_exists($metadata, 'toArray') ? $metadata->toArray() : [];
        $sessionId = $session->id ?? null;
        $pi = $session->payment_intent ?? null;
        $paymentIntentId = is_string($pi) ? $pi : ($pi->id ?? null);

        event(new \Yared\SmartStripe\Events\CheckoutCompleted(
            $sessionId,
            $paymentIntentId,
            $session,
            $metaArray
        ));

        $handlers = config('stripe-smart.payable_handlers', []);
        foreach ($handlers as $metadataKey => $config) {
            $id = $metaArray[$metadataKey] ?? null;
            if (!$id || !isset($config['model'], $config['method'])) {
                continue;
            }
            $model = $config['model']::find($id);
            if (!$model || !method_exists($model, $config['method'])) {
                continue;
            }
            if ($model instanceof \Yared\SmartStripe\Contracts\Payable && $model->isPaid()) {
                continue;
            }
            $model->{$config['method']}($sessionId, $paymentIntentId);
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/stripe-smart.php' => config_path('stripe-smart.php'),
        ], 'stripe-smart-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'stripe-smart-migrations');

        $this->registerWebhookRoute();
        $this->registerWebhookListeners();

    }

    protected function registerWebhookRoute(): void
    {
        $path = config('stripe-smart.webhook_path', 'stripe/webhook');

        Route::post($path, [WebhookController::class, 'handle'])
            ->name('stripe.webhook')
            ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    }
}
