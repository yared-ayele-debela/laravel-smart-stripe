<?php

namespace Yared\SmartStripe\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Http;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;
use Stripe\StripeClient;
use Yared\SmartStripe\Jobs\ProcessPaymentJob;
use Yared\SmartStripe\Security\FraudDetector;
use Yared\SmartStripe\Logging\PaymentLogger;

class StripePayment
{
    protected ?int $amount = null;
    protected string $currency;

    protected ?object $customer = null;
    protected ?string $paymentMethod = null;
    protected ?string $description = null;
    protected array $metadata = [];
    protected bool $detectFraud = false;
    protected bool $queue = false;
    protected bool $simulateMode = false;
    protected ?string $simulateResult = null;
    protected FraudDetector $fraudDetector;
    protected PaymentLogger $paymentLogger;

    public function __construct(
        protected Application $app,
        ?FraudDetector $fraudDetector = null,
        ?PaymentLogger $paymentLogger = null,
    ) {
        $this->fraudDetector = $fraudDetector ?? $app->make(FraudDetector::class);
        $this->paymentLogger = $paymentLogger ?? $app->make(PaymentLogger::class);
        $this->currency = config('stripe-smart.currency', 'usd');

        $key = config('stripe-smart.secret_key');
        if ($key) {
            Stripe::setApiKey($key);
        }
    }

    public function charge(int $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = strtolower($currency);
        return $this;
    }

    public function customer(object $user): self
    {
        $this->customer = $user;
        return $this;
    }

    public function from(object $user): self
    {
        return $this->customer($user);
    }

    public function paymentMethod(string $paymentMethodId): self
    {
        $this->paymentMethod = $paymentMethodId;
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function metadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    public function detectFraud(): self
    {
        $this->detectFraud = true;
        return $this;
    }

    public function queue(): self
    {
        $this->queue = true;
        return $this;
    }

    public function pay(): PaymentIntent|array
    {
        if ($this->queue) {
            return $this->dispatchToQueue();
        }

        if (config('stripe-smart.simulator.enabled')) {
            return $this->runSimulator();
        }

        $this->validateCharge();

        if ($this->detectFraud && !$this->fraudDetector->passes($this->getFraudContext())) {
            $this->paymentLogger->logBlocked('fraud_detection', $this->getFraudContext());
            throw new \Yared\SmartStripe\Exceptions\FraudDetectedException(
                'Payment blocked due to risk'
            );
        }

        $metadata = $this->buildMetadata();

        $params = [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'description' => $this->description,
            'metadata' => $metadata,
            'automatic_payment_methods' => ['enabled' => true],
        ];

        if ($this->customer) {
            $customerId = $this->resolveCustomerId();
            $params['customer'] = $customerId;
            if ($this->paymentMethod) {
                $params['payment_method'] = $this->paymentMethod;
                $params['confirm'] = true;
            } elseif ($this->getDefaultPaymentMethod($customerId)) {
                $params['payment_method'] = $this->getDefaultPaymentMethod($customerId);
                $params['confirm'] = true;
            }
        }

        if ($this->paymentMethod && !isset($params['customer'])) {
            $params['payment_method'] = $this->paymentMethod;
            $params['confirm'] = true;
        }

        $intent = PaymentIntent::create($params);

        $this->paymentLogger->logPayment($intent, 'charge', $this->getFraudContext());

        return $intent;
    }

    public function refund(string $paymentIntentId, ?int $amount = null): Refund
    {
        if (config('stripe-smart.simulator.enabled')) {
            return self::simulateRefund();
        }

        $params = ['payment_intent' => $paymentIntentId];
        if ($amount !== null) {
            $params['amount'] = $amount;
        }

        $refund = Refund::create($params);
        $this->paymentLogger->logRefund($refund);

        return $refund;
    }

    public function checkout(): CheckoutBuilder
    {
        return $this->app->make(CheckoutBuilder::class);
    }

    /**
     * Retrieve a Checkout Session with expanded payment_intent.
     * Use on success page to get session_id from query param.
     */
    public function retrieveCheckoutSession(string $sessionId): ?object
    {
        if (config('stripe-smart.simulator.enabled')) {
            return null;
        }

        $key = config('stripe-smart.secret_key');
        if (!$key) {
            return null;
        }

        try {
            $stripe = new StripeClient($key);
            return $stripe->checkout->sessions->retrieve($sessionId, [
                'expand' => ['payment_intent'],
            ]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get metadata from session as array (works with Stripe object or array).
     */
    public static function getSessionMetadata(object $session): array
    {
        $metadata = $session->metadata ?? null;
        if (!$metadata) {
            return [];
        }
        if (is_array($metadata)) {
            return $metadata;
        }
        return method_exists($metadata, 'toArray') ? $metadata->toArray() : [];
    }

    public function subscribe(object $user): CheckoutBuilder
    {
        $builder = $this->app->make(CheckoutBuilder::class);
        $builder->customer($user);
        $builder->setMode('subscription');
        return $builder;
    }

    public static function retryFailedPayments(): void
    {
        $stripe = new StripeClient(config('stripe-smart.secret_key'));
        $invoices = $stripe->invoices->all(['status' => 'open', 'collection_method' => 'charge_automatically']);

        foreach ($invoices->data as $invoice) {
            try {
                $stripe->invoices->pay($invoice->id);
            } catch (\Throwable $e) {
                // Log and continue
            }
        }
    }

    public static function simulateSuccess(): array
    {
        return [
            'id' => 'pi_sim_success_' . uniqid(),
            'status' => 'succeeded',
            'amount' => 0,
            'simulated' => true,
        ];
    }

    public static function simulateFailure(): void
    {
        throw new \Yared\SmartStripe\Exceptions\SimulatedFailureException('Simulated payment failure');
    }

    public static function simulateRefund(): object
    {
        return (object) [
            'id' => 're_sim_' . uniqid(),
            'status' => 'succeeded',
            'simulated' => true,
        ];
    }

    protected function dispatchToQueue(): array
    {
        $job = new ProcessPaymentJob([
            'amount' => $this->amount,
            'currency' => $this->currency,
            'customer' => $this->customer?->id ?? null,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'detect_fraud' => $this->detectFraud,
        ]);

        dispatch($job);

        return [
            'queued' => true,
            'message' => 'Payment queued for processing',
        ];
    }

    protected function runSimulator(): array
    {
        return self::simulateSuccess();
    }

    protected function validateCharge(): void
    {
        if ($this->amount === null || $this->amount <= 0) {
            throw new \InvalidArgumentException('Amount must be a positive integer (in cents)');
        }
    }

    protected function resolveCustomerId(): string
    {
        $stripeId = $this->customer->stripe_id ?? null;

        if (!$stripeId) {
            $customer = Customer::create([
                'email' => $this->customer->email ?? null,
                'name' => $this->customer->name ?? null,
                'metadata' => ['user_id' => (string) ($this->customer->id ?? '')],
            ]);
            $stripeId = $customer->id;

            if (method_exists($this->customer, 'updateStripeId')) {
                $this->customer->updateStripeId($stripeId);
            } elseif (property_exists($this->customer, 'stripe_id')) {
                $this->customer->stripe_id = $stripeId;
                $this->customer->save();
            }
        }

        return $stripeId;
    }

    protected function getDefaultPaymentMethod(string $customerId): ?string
    {
        try {
            $customer = Customer::retrieve($customerId);
            return $customer->invoice_settings->default_payment_method ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function buildMetadata(): array
    {
        if (!config('stripe-smart.metadata.enabled', true)) {
            return $this->metadata;
        }

        $request = request();
        $auto = [];

        $include = config('stripe-smart.metadata.include', []);

        if (in_array('user_id', $include) && $this->customer) {
            $auto['user_id'] = (string) ($this->customer->id ?? '');
        }
        if (in_array('ip', $include) && $request) {
            $auto['ip'] = $request->ip() ?? '';
        }
        if (in_array('browser', $include) && $request) {
            $auto['browser'] = $this->parseBrowser($request->userAgent());
        }
        if (in_array('device', $include) && $request) {
            $auto['device'] = $this->parseDevice($request->userAgent());
        }
        if (in_array('country', $include) && $request) {
            $auto['country'] = $this->getCountryFromRequest();
        }
        if (in_array('app_name', $include)) {
            $auto['app'] = config('app.name', 'Laravel');
        }
        if (in_array('laravel_version', $include)) {
            $auto['laravel_version'] = \Illuminate\Foundation\Application::VERSION;
        }

        return array_merge($auto, $this->metadata);
    }

    protected function getFraudContext(): array
    {
        $request = request();
        return [
            'user_id' => $this->customer?->id ?? null,
            'ip' => $request?->ip(),
            'amount' => $this->amount,
            'user_agent' => $request?->userAgent(),
        ];
    }

    protected function parseBrowser(?string $ua): string
    {
        if (!$ua) return 'Unknown';
        if (str_contains($ua, 'Chrome') && !str_contains($ua, 'Edg')) return 'Chrome';
        if (str_contains($ua, 'Firefox')) return 'Firefox';
        if (str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')) return 'Safari';
        if (str_contains($ua, 'Edg')) return 'Edge';
        return 'Other';
    }

    protected function parseDevice(?string $ua): string
    {
        if (!$ua) return 'Desktop';
        if (str_contains($ua, 'Mobile') && !str_contains($ua, 'iPad')) return 'Mobile';
        if (str_contains($ua, 'iPad') || str_contains($ua, 'Tablet')) return 'Tablet';
        return 'Desktop';
    }

    protected function getCountryFromRequest(): string
    {
        $request = request();
        if (!$request) return 'Unknown';

        $ip = $request->ip();
        if (!$ip || in_array($ip, ['127.0.0.1', '::1'])) return 'Local';

        try {
            $response = Http::timeout(2)->get("http://ip-api.com/json/{$ip}?fields=country");
            $data = $response->json();
            return $data['country'] ?? 'Unknown';
        } catch (\Throwable) {
            return 'Unknown';
        }
    }
}
