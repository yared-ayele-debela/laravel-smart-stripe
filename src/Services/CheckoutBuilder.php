<?php

namespace Yared\SmartStripe\Services;

use Illuminate\Contracts\Foundation\Application;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class CheckoutBuilder
{
    protected ?string $productName = null;
    protected ?int $price = null;
    protected ?string $priceId = null;
    protected ?string $successUrl = null;
    protected ?string $cancelUrl = null;
    protected ?object $customer = null;
    protected string $mode = 'payment';
    protected ?string $planId = null;
    protected array $metadata = [];
    protected string $currency = 'usd';

    public function setMode(string $mode): self
    {
        $this->mode = $mode;
        return $this;
    }

    public function __construct(protected Application $app)
    {
        $key = config('stripe-smart.secret_key');
        if ($key) {
            Stripe::setApiKey($key);
        }
    }

    public function product(string $name): self
    {
        $this->productName = $name;
        return $this;
    }

    public function price(int $amountInCents): self
    {
        $this->price = $amountInCents;
        return $this;
    }

    public function priceId(string $stripePriceId): self
    {
        $this->priceId = $stripePriceId;
        return $this;
    }

    public function plan(string $planId): self
    {
        $this->planId = $planId;
        $this->priceId = $planId;
        $this->mode = 'subscription';
        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = strtolower($currency);
        return $this;
    }

    public function success(string $url): self
    {
        $this->successUrl = $this->ensureSessionIdPlaceholder($url);
        return $this;
    }

    public function cancel(string $url): self
    {
        $this->cancelUrl = $url;
        return $this;
    }

    /**
     * Ensure success URL includes {CHECKOUT_SESSION_ID} so Stripe passes session_id on redirect.
     */
    protected function ensureSessionIdPlaceholder(string $url): string
    {
        if (str_contains($url, '{CHECKOUT_SESSION_ID}')) {
            return $url;
        }
        $separator = str_contains($url, '?') ? '&' : '?';
        return rtrim($url, '?&') . $separator . 'session_id={CHECKOUT_SESSION_ID}';
    }

    public function customer(object $user): self
    {
        $this->customer = $user;
        return $this;
    }

    public function metadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        return $this;
    }

    public function redirect(): \Illuminate\Http\RedirectResponse
    {
        $session = $this->createSession();
        return redirect($session->url);
    }

    public function redirectToCheckout(): \Illuminate\Http\RedirectResponse
    {
        return $this->redirect();
    }

    public function createSession(): Session|\stdClass
    {
        if (config('stripe-smart.simulator.enabled')) {
            return $this->simulateSession();
        }

        $lineItems = $this->buildLineItems();
        $defaultSuccess = config('stripe-smart.checkout.success_url') ?: url('/success');
        $successUrl = $this->successUrl ?: $this->ensureSessionIdPlaceholder($defaultSuccess);
        $cancelUrl = $this->cancelUrl ?: (config('stripe-smart.checkout.cancel_url') ?: url('/cancel'));

        $params = [
            'mode' => $this->mode,
            'line_items' => $lineItems,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => $this->metadata,
        ];

        if ($this->customer && ($stripeId = $this->customer->stripe_id ?? null)) {
            $params['customer'] = $stripeId;
        } elseif ($this->customer) {
            $params['customer_email'] = $this->customer->email ?? null;
        }

        return Session::create($params);
    }

    protected function buildLineItems(): array
    {
        if ($this->mode === 'subscription' && $this->priceId) {
            return [[
                'price' => $this->priceId,
                'quantity' => 1,
            ]];
        }

        if ($this->mode === 'subscription' && $this->planId) {
            return [[
                'price' => $this->planId,
                'quantity' => 1,
            ]];
        }

        return [[
            'price_data' => [
                'currency' => $this->currency,
                'product_data' => [
                    'name' => $this->productName ?: 'Purchase',
                ],
                'unit_amount' => $this->price ?: 0,
            ],
            'quantity' => 1,
        ]];
    }

    protected function simulateSession(): \stdClass
    {
        $session = new \stdClass;
        $session->id = 'cs_sim_' . uniqid();
        $session->url = $this->successUrl ?: url('/success');
        return $session;
    }

    public function start(): Session
    {
        return $this->createSession();
    }
}
