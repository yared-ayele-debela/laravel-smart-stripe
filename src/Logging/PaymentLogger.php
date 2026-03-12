<?php

namespace Yared\SmartStripe\Logging;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentLogger
{
    public function logPayment(object $payment, string $type, array $context = []): void
    {
        if (!config('stripe-smart.logging.enabled', true)) {
            return;
        }

        $table = config('stripe-smart.logging.table', 'stripe_payment_logs');

        if (!$this->tableExists($table)) {
            Log::channel('single')->info('Stripe Payment', [
                'type' => $type,
                'payment_id' => $payment->id ?? null,
                'amount' => $payment->amount ?? null,
                'status' => $payment->status ?? null,
                'user_id' => $context['user_id'] ?? null,
                'ip' => $context['ip'] ?? null,
                'metadata' => $payment->metadata ?? [],
            ]);
            return;
        }

        try {
            DB::table($table)->insert([
                'payment_id' => $payment->id ?? null,
                'type' => $type,
                'amount' => $payment->amount ?? 0,
                'currency' => $payment->currency ?? 'usd',
                'status' => $payment->status ?? 'unknown',
                'user_id' => $context['user_id'] ?? null,
                'ip' => $context['ip'] ?? null,
                'browser' => $this->parseBrowser($context['user_agent'] ?? null),
                'device' => $this->parseDevice($context['user_agent'] ?? null),
                'metadata' => json_encode($payment->metadata ?? []),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Stripe payment log failed: ' . $e->getMessage());
        }
    }

    public function logRefund(object $refund): void
    {
        if (!config('stripe-smart.logging.enabled', true)) {
            return;
        }

        $table = config('stripe-smart.logging.table', 'stripe_payment_logs');

        if (!$this->tableExists($table)) {
            Log::channel('single')->info('Stripe Refund', [
                'refund_id' => $refund->id ?? null,
                'amount' => $refund->amount ?? null,
                'status' => $refund->status ?? null,
            ]);
            return;
        }

        try {
            DB::table($table)->insert([
                'payment_id' => $refund->id ?? null,
                'type' => 'refund',
                'amount' => $refund->amount ?? 0,
                'currency' => $refund->currency ?? 'usd',
                'status' => $refund->status ?? 'unknown',
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Stripe refund log failed: ' . $e->getMessage());
        }
    }

    public function logBlocked(string $reason, array $context = []): void
    {
        if (!config('stripe-smart.logging.enabled', true)) {
            return;
        }

        Log::channel('single')->warning('Stripe payment blocked', [
            'reason' => $reason,
            'user_id' => $context['user_id'] ?? null,
            'ip' => $context['ip'] ?? null,
            'amount' => $context['amount'] ?? null,
        ]);
    }

    protected function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
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
}
