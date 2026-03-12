<?php

namespace Yared\SmartStripe\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

class WebhookHandler
{
    protected array $listeners = [];

    public function __construct(protected Application $app)
    {
        //
    }

    public function listen(string $event, callable $callback): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = $callback;
    }

    public function handle(Request $request): array
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('stripe-smart.webhook_secret');

        if (!$secret) {
            throw new \RuntimeException('STRIPE_WEBHOOK_SECRET is not configured');
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException $e) {
            throw new \Yared\SmartStripe\Exceptions\InvalidWebhookSignatureException(
                'Invalid webhook signature: ' . $e->getMessage()
            );
        }

        $eventType = $event->type;
        $normalizedType = $this->normalizeEventType($eventType);

        $responses = [];
        $listenersToRun = array_merge(
            $this->listeners[$eventType] ?? [],
            $this->listeners[$normalizedType] ?? []
        );

        foreach ($listenersToRun as $callback) {
            try {
                $result = $callback($event);
                $responses[] = $result;
            } catch (\Throwable $e) {
                report($e);
                $responses[] = ['error' => $e->getMessage()];
            }
        }

        return [
            'handled' => true,
            'event' => $eventType,
            'listeners_run' => count($listenersToRun),
            'responses' => $responses,
        ];
    }

    protected function normalizeEventType(string $type): string
    {
        $map = [
            'payment_intent.succeeded' => 'payment_succeeded',
            'payment_intent.payment_failed' => 'payment_failed',
            'charge.refunded' => 'refund_created',
            'charge.refund.updated' => 'refund_created',
        ];

        return $map[$type] ?? $type;
    }

    public function getListeners(): array
    {
        return $this->listeners;
    }
}
