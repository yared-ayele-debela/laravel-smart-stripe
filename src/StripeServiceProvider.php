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

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Yared\SmartStripe\Commands\RetryFailedPaymentsCommand::class,
            ]);
        }
    }

    protected function registerWebhookRoute(): void
    {
        $path = config('stripe-smart.webhook_path', 'stripe/webhook');

        Route::post($path, [WebhookController::class, 'handle'])
            ->name('stripe.webhook')
            ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
    }
}
