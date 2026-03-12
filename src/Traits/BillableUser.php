<?php

namespace Yared\SmartStripe\Traits;

use Stripe\Customer;
use Stripe\Stripe;

trait BillableUser
{
    public function stripeId(): ?string
    {
        return $this->stripe_id ?? null;
    }

    public function hasStripeId(): bool
    {
        return !empty($this->stripe_id);
    }

    public function createAsStripeCustomer(?array $options = []): Customer
    {
        Stripe::setApiKey(config('stripe-smart.secret_key'));

        $customer = Customer::create(array_merge([
            'email' => $this->email ?? null,
            'name' => $this->name ?? null,
            'metadata' => [
                'user_id' => (string) $this->getKey(),
            ],
        ], $options));

        $this->updateStripeId($customer->id);

        return $customer;
    }

    public function updateStripeId(?string $stripeId): void
    {
        $this->forceFill([
            'stripe_id' => $stripeId,
        ])->save();
    }

    public function asStripeCustomer(): ?Customer
    {
        if (!$this->stripe_id) {
            return null;
        }

        Stripe::setApiKey(config('stripe-smart.secret_key'));

        return Customer::retrieve($this->stripe_id);
    }
}
