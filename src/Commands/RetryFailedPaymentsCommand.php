<?php

namespace Yared\SmartStripe\Commands;

use Illuminate\Console\Command;
use Stripe\StripeClient;

class RetryFailedPaymentsCommand extends Command
{
    protected $signature = 'stripe:retry-failed-payments';

    protected $description = 'Retry failed subscription payments based on configured schedule';

    public function handle(): int
    {
        if (!config('stripe-smart.retry.enabled', true)) {
            $this->warn('Payment retry is disabled in config.');
            return self::SUCCESS;
        }

        $secretKey = config('stripe-smart.secret_key');
        if (!$secretKey) {
            $this->error('STRIPE_SECRET_KEY is not configured.');
            return self::FAILURE;
        }

        $stripe = new StripeClient($secretKey);

        try {
            $invoices = $stripe->invoices->all([
                'status' => 'open',
                'collection_method' => 'charge_automatically',
            ]);

            $retried = 0;
            foreach ($invoices->data as $invoice) {
                try {
                    $stripe->invoices->pay($invoice->id);
                    $retried++;
                    $this->info("Retried invoice: {$invoice->id}");
                } catch (\Throwable $e) {
                    $this->warn("Failed to retry {$invoice->id}: {$e->getMessage()}");
                }
            }

            $this->info("Retried {$retried} failed payment(s).");
        } catch (\Throwable $e) {
            $this->error('Retry failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
