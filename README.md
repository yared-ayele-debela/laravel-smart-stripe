# Laravel Smart Stripe

**Secure Stripe Payments for Laravel with Fraud Detection, Automatic Webhooks, Smart Checkout, and Payment Logging.**

[![Latest Version](https://img.shields.io/packagist/v/yared/laravel-smart-stripe.svg)](https://packagist.org/packages/yared/laravel-smart-stripe)
[![License](https://img.shields.io/packagist/l/yared/laravel-smart-stripe.svg)](https://packagist.org/packages/yared/laravel-smart-stripe)

Instead of writing many lines of Stripe code, developers can simply write:

```php
StripePay::charge(1000)->from($user)->pay();
```

or

```php
return StripePay::checkout($order)->redirect();
```

## Features

- **One-Line Payment** — Fluent API for charges
- **Smart Checkout Builder** — Create Stripe Checkout sessions in seconds
- **Automatic Webhook Handler** — Handles `payment_succeeded`, `payment_failed`, `refund_created`, `subscription_created`
- **Fraud Detection** — Block suspicious payments (IP, rapid attempts, country)
- **Automatic Payment Retry** — Retry failed subscription payments
- **Smart Metadata** — Auto-attach user_id, IP, browser, country, app name
- **Payment Audit Logger** — Log every Stripe interaction
- **Queue Integration** — Run heavy operations in background jobs
- **Test Mode Simulator** — Simulate success/failure/refund locally

## Installation

```bash
composer require yared/laravel-smart-stripe
```

Publish config and migrations:

```bash
php artisan vendor:publish --tag=stripe-smart-config
php artisan vendor:publish --tag=stripe-smart-migrations
php artisan migrate
```

Add to your `.env`:

```env
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_CURRENCY=usd
```

## Usage

### Charge Customer

```php
use Yared\SmartStripe\Facades\StripePay;

StripePay::charge(2000)
    ->currency('usd')
    ->customer($user)
    ->description('Order #2001')
    ->metadata(['order_id' => 2001])
    ->pay();
```

### With Fraud Detection

```php
StripePay::charge(1000)
    ->from($user)
    ->detectFraud()
    ->pay();
```

### Stripe Checkout

The success URL automatically includes `{CHECKOUT_SESSION_ID}` so Stripe passes the session ID on redirect.

```php
return StripePay::checkout()
    ->product('Premium Plan')
    ->price(1999)
    ->success('/success')
    ->cancel('/cancel')
    ->redirect();
```

### Subscription

```php
StripePay::subscribe($user)
    ->plan('pro_monthly')
    ->success('/dashboard')
    ->cancel('/pricing')
    ->redirect();
```

### Refund

```php
StripePay::refund($paymentIntentId);
StripePay::refund($paymentIntentId, 500); // Partial refund
```

### Queue Payment

```php
StripePay::charge(2000)
    ->customer($user)
    ->queue();
```

### Auto-Update Payment Status (Payable Handlers)

Add to `config/stripe-smart.php` to automatically update models when checkout completes:

```php
'payable_handlers' => [
    'booking_id' => [
        'model' => \App\Models\Booking::class,
        'method' => 'markAsPaid',
    ],
    'order_id' => [
        'model' => \App\Models\Order::class,
        'method' => 'markAsPaid',
    ],
],
```

Your model must have a `markAsPaid($sessionId, $paymentIntentId)` method. Include the metadata key (e.g. `booking_id`) when creating checkout:

```php
StripePay::checkout()
    ->product('Hotel Booking')
    ->price(1999)
    ->metadata(['booking_id' => $booking->id])
    ->success('/success')
    ->redirect();
```

### Success Page Helper

On your success page, retrieve the session and metadata:

```php
$session = StripePay::retrieveCheckoutSession($request->query('session_id'));
$metadata = StripePayment::getSessionMetadata($session);
$bookingId = $metadata['booking_id'] ?? null;
```

### Webhook Listeners

In `AppServiceProvider::boot()`:

```php
use Yared\SmartStripe\Facades\StripeWebhook;

StripeWebhook::listen('payment_succeeded', function ($event) {
    $orderId = $event->data->object->metadata['order_id'] ?? null;
    if ($orderId) {
        Order::find($orderId)?->markPaid();
    }
});

StripeWebhook::listen('payment_failed', function ($event) {
    // Handle failed payment
});

StripeWebhook::listen('refund_created', function ($event) {
    // Handle refund
});
```

Supported event names: `payment_succeeded`, `payment_failed`, `refund_created`, `subscription_created`, `subscription_updated`, `subscription_deleted`, `invoice_paid`, `invoice_payment_failed`.

### Retry Failed Payments

```php
StripePay::retryFailedPayments();
```

Or schedule in `app/Console/Kernel.php`:

```php
$schedule->command('stripe:retry-failed-payments')->hourly();
```

### Test Mode Simulator

Enable in config or `.env`:

```env
STRIPE_SIMULATOR_ENABLED=true
```

```php
StripePay::simulateSuccess();
StripePay::simulateFailure(); // Throws exception
StripePay::simulateRefund();
```

### Billable User Trait

Add to your User model:

```php
use Yared\SmartStripe\Traits\BillableUser;

class User extends Authenticatable
{
    use BillableUser;
}
```

Add `stripe_id` to users (migration included):

```php
$user->createAsStripeCustomer();
$user->asStripeCustomer();
```

## Security

- Webhook signature verification
- CSRF excluded for webhook route only
- Fraud detection (configurable)
- Rate limiting on webhook endpoint
- Payment logging for audit trail

## Configuration

See `config/stripe-smart.php` for all options:

- `fraud_detection` — Enable/disable, limits per IP/user, suspicious countries
- `retry` — Schedule for failed payment retries
- `metadata` — Auto-attach user_id, IP, browser, etc.
- `logging` — Enable payment audit logs
- `simulator` — Test mode for local development

## License

MIT
