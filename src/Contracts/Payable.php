<?php

namespace Yared\SmartStripe\Contracts;

interface Payable
{
    /**
     * Mark the model as paid after successful Stripe checkout.
     */
    public function markAsPaid(?string $sessionId = null, ?string $paymentIntentId = null): void;

    /**
     * Check if the model is paid.
     */
    public function isPaid(): bool;
}
