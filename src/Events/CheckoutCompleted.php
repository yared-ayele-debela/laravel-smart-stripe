<?php

namespace Yared\SmartStripe\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CheckoutCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $sessionId,
        public ?string $paymentIntentId,
        public object $session,
        public array $metadata
    ) {}
}
