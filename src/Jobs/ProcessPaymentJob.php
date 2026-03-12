<?php

namespace Yared\SmartStripe\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Yared\SmartStripe\Services\StripePayment;

class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected array $payload
    ) {}

    public function handle(StripePayment $stripePayment): void
    {
        $userId = $this->payload['customer'] ?? null;
        $user = $userId ? $this->resolveUser($userId) : null;

        $payment = $stripePayment
            ->charge($this->payload['amount'])
            ->currency($this->payload['currency'] ?? config('stripe-smart.currency', 'usd'))
            ->description($this->payload['description'] ?? 'Queued payment');

        if ($user) {
            $payment->customer($user);
        }

        if (!empty($this->payload['metadata'])) {
            $payment->metadata($this->payload['metadata']);
        }

        if ($this->payload['detect_fraud'] ?? false) {
            $payment->detectFraud();
        }

        $payment->pay();
    }

    protected function resolveUser(mixed $userId): ?object
    {
        $model = config('auth.providers.users.model', \App\Models\User::class);

        if (!class_exists($model)) {
            return null;
        }

        return $model::find($userId);
    }
}
