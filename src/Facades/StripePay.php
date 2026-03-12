<?php

namespace Yared\SmartStripe\Facades;

use Illuminate\Support\Facades\Facade;
use Yared\SmartStripe\Services\CheckoutBuilder;
use Yared\SmartStripe\Services\StripePayment;

/**
 * @method static StripePayment charge(int $amount)
 * @method static CheckoutBuilder checkout()
 * @method static mixed refund(string $paymentIntentId, ?int $amount = null)
 * @method static CheckoutBuilder subscribe(object $user)
 * @method static void retryFailedPayments()
 * @method static array simulateSuccess()
 * @method static void simulateFailure()
 * @method static object simulateRefund()
 * @method static object|null retrieveCheckoutSession(string $sessionId)
 *
 * @see \Yared\SmartStripe\Services\StripePayment
 */
class StripePay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StripePayment::class;
    }
}
