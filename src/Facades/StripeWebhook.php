<?php

namespace Yared\SmartStripe\Facades;

use Illuminate\Support\Facades\Facade;
use Yared\SmartStripe\Services\WebhookHandler;

/**
 * @method static void listen(string $event, callable $callback)
 *
 * @see \Yared\SmartStripe\Services\WebhookHandler
 */
class StripeWebhook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WebhookHandler::class;
    }
}
