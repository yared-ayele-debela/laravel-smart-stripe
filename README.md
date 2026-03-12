# Laravel Smart Stripe

**One-time Stripe payments for Laravel — flexible for hotel bookings, ecommerce, donations, and more.**

[![Latest Version](https://img.shields.io/packagist/v/yared/laravel-smart-stripe.svg)](https://packagist.org/packages/yared/laravel-smart-stripe)
[![License](https://img.shields.io/packagist/l/yared/laravel-smart-stripe.svg)](https://packagist.org/packages/yared/laravel-smart-stripe)

One-line payments:

```php
StripePay::charge(1000)->from($user)->pay();
```

or redirect to Stripe Checkout:

```php
return StripePay::checkout()
    ->product('Hotel Room')
    ->price(1999)
    ->success('/success')
    ->cancel('/cancel')
    ->redirect();
```

## Features

- **One-time payments only** — No subscriptions; ideal for bookings, orders, donations
- **One-line charge** — Fluent API for direct charges
- **Smart Checkout** — Single item or multi-item cart (ecommerce)
- **Webhooks** — `payment_succeeded`, `payment_failed`, `refund_created`
- **Fraud detection** — IP limits, rapid attempts, suspicious countries
- **Smart metadata** — Auto-attach user_id, IP, browser, country, app name
- **Payable handlers** — Auto-update models (e.g. Booking, Order) when paid
- **Payment logging** — Audit trail for every Stripe interaction
- **Queue support** — Background processing
- **Test simulator** — Simulate success/failure/refund locally

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

Add to `.env`:

```env
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_CURRENCY=usd
```

## Usage

### Direct charge

```php
use Yared\SmartStripe\Facades\StripePay;

StripePay::charge(2000)
    ->currency('usd')
    ->customer($user)
    ->description('Order #2001')
    ->metadata(['order_id' => 2001])
    ->pay();
```

### With fraud detection

```php
StripePay::charge(1000)
    ->from($user)
    ->detectFraud()
    ->pay();
```

### Stripe Checkout (single item)

```php
return StripePay::checkout()
    ->product('Premium Plan')
    ->price(1999)
    ->success('/success')
    ->cancel('/cancel')
    ->redirect();
```

### Multi-item checkout (ecommerce cart)

```php
return StripePay::checkout()
    ->items([
        ['name' => 'Room A', 'price' => 15000, 'quantity' => 2, 'description' => '2 nights'],
        ['name' => 'Breakfast', 'price' => 999, 'quantity' => 1],
    ])
    ->metadata(['booking_id' => $booking->id])
    ->success('/hotel/success')
    ->cancel('/hotel/cancel')
    ->redirect();
```

### Hotel booking example

```php
StripePay::checkout()
    ->product("Room {$room->name} - {$checkIn} to {$checkOut}")
    ->price($totalCents)
    ->metadata(['booking_id' => $booking->id])
    ->success(route('hotel.success'))
    ->cancel(route('hotel.cancel'))
    ->redirect();
```

### Refund

```php
StripePay::refund($paymentIntentId);
StripePay::refund($paymentIntentId, 500); // Partial refund
```

### Queue payment

```php
StripePay::charge(2000)
    ->customer($user)
    ->queue();
```

### Auto-update payment status (Payable handlers)

In `config/stripe-smart.php`:

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

Include the metadata key when creating checkout:

```php
StripePay::checkout()
    ->product('Hotel Booking')
    ->price(1999)
    ->metadata(['booking_id' => $booking->id])
    ->success('/success')
    ->redirect();
```

### Success page helper

```php
$session = StripePay::retrieveCheckoutSession($request->query('session_id'));
$metadata = StripePayment::getSessionMetadata($session);
$bookingId = $metadata['booking_id'] ?? null;
```

### Webhook listeners

In `AppServiceProvider::boot()`:

```php
use Yared\SmartStripe\Facades\StripeWebhook;

StripeWebhook::listen('payment_succeeded', function ($event) {
    $orderId = $event->data->object->metadata['order_id'] ?? null;
    if ($orderId) {
        Order::find($orderId)?->markPaid();
    }
});

StripeWebhook::listen('payment_failed', fn ($event) => /* ... */);
StripeWebhook::listen('refund_created', fn ($event) => /* ... */);
```

Supported events: `payment_succeeded`, `payment_failed`, `refund_created`.

### Test simulator

```env
STRIPE_SIMULATOR_ENABLED=true
```

```php
StripePay::simulateSuccess();
StripePay::simulateFailure(); // Throws exception
StripePay::simulateRefund();
```

### Billable user trait

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

See `config/stripe-smart.php`:

- `fraud_detection` — Limits per IP/user, suspicious countries
- `metadata` — Auto-attach user_id, IP, browser, etc.
- `logging` — Payment audit logs
- `simulator` — Test mode for local development

## License

MIT
