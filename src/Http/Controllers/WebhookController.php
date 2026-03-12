<?php

namespace Yared\SmartStripe\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\RateLimiter;
use Yared\SmartStripe\Services\WebhookHandler;

class WebhookController
{
    public function __construct(
        protected WebhookHandler $webhookHandler
    ) {}

    public function handle(Request $request): Response
    {
        if (config('stripe-smart.rate_limit.enabled', true)) {
            $key = 'stripe-webhook:' . $request->ip();
            if (RateLimiter::tooManyAttempts($key, config('stripe-smart.rate_limit.max_attempts', 60))) {
                return response('Too Many Requests', 429);
            }
            RateLimiter::hit($key, config('stripe-smart.rate_limit.decay_minutes', 1) * 60);
        }

        try {
            $result = $this->webhookHandler->handle($request);
            return response()->json($result, 200);
        } catch (\Yared\SmartStripe\Exceptions\InvalidWebhookSignatureException $e) {
            return response('Invalid signature', 400);
        } catch (\Throwable $e) {
            report($e);
            return response('Webhook error', 500);
        }
    }
}
