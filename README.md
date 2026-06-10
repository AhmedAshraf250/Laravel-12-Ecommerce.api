# Laravel 12 Ecommerce API

This is a Laravel 12 backend API for a small but realistic ecommerce system. It is built around the normal store journey: browse products, add items to cart, checkout, pay, then follow the order as it moves from pending to delivered.

The project is API-first and role-aware. Customers manage their own orders, admins handle the operational workflow, and delivery users update shipping progress. Payments are handled through provider workflows, so an order becomes paid only when Stripe or PayPal confirms it.

It also includes the pieces that make the flow feel complete: order status history, email notifications, realtime updates with Laravel Reverb, Postman examples, and seeded demo data for quick testing.

## What It Does

- Product and category browsing and management
- Cart and checkout flow
- Stripe and PayPal payment integration
- Payment webhooks for provider-driven updates
- Order status workflow with validated transitions
- Customer, admin, and delivery access separation
- Order status history for audit/debugging
- Realtime order updates with Laravel Reverb
- Email notifications for customer communication
- Postman collection and demo-ready seed data

## Project Structure

```text
app/
  Enum/                         Status and provider enums
  Events/                       Broadcast events such as OrderStatusUpdated
  Http/Controllers/Api/         API controllers
  Http/Requests/                Request validation classes
  Models/                       Eloquent models and query scopes
  Notifications/                Customer email notifications
  Services/
    Orders/                     Order workflow and status transition logic
    Payments/                   Payment orchestration, gateways, and webhooks

database/
  migrations/                   Database schema
  seeders/                      Demo users, products, categories, and orders

docs/                           API notes, demo scripts, QA cases, Stripe CLI notes
postman/                        Postman collection
routes/
  api.php                       API routes
  channels.php                  Reverb private channel authorization
  web.php                       Debug and browser-only helper routes
tools/                          Local helper binaries such as Mailpit and Stripe CLI archive
```

## Main Workflow

```text
pending -> paid -> processing -> shipped -> delivered
```

`cancelled` is supported with rules:

- Customers can cancel only their own pending orders
- Admins can cancel operationally when business rules allow it
- Paid cancellations do not automatically refund money yet; refund handling should be implemented as a separate payment-provider workflow

## Quick Start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

## Environment Notes

The payment flow needs Stripe test credentials in `.env`:

```env
STRIPE_SECRET=sk_test_51TYYDGLwybbHdeth4JN7jj2V76GKjmejbjTNsQ4L0Um8kxFA0YmbTwX25tNJqKl6VpVQYNKZObJ41aqQssX5bYg300c0vqeu4S
STRIPE_PUBLISHABLE_KEY=pk_test_51TYYDGLwybbHdethACvgyY5YFVeuHburBFsmnYRz9C0T2bDU0STcxBFT3u8wh7qSqyj4l9WKG3c4eAQGMoiMvo3g00pEq2neSP
STRIPE_WEBHOOK_SECRET=whsec_replace_me
```

`STRIPE_SECRET` and `STRIPE_PUBLISHABLE_KEY` come from the Stripe dashboard in test mode.
`STRIPE_WEBHOOK_SECRET` is printed by the local listener:

```bash
stripe listen --forward-to localhost:8000/api/webhooks/stripe
```

Keep live keys out of the repository. Test keys are fine for local demos when that is intentional.

Optional local services:

```bash
php artisan reverb:start
php artisan queue:work
./tools/mailpit
```

## Demo Accounts

```text
admin@mail.com / password
customer@mail.com / password
delivery@mail.com / password
```

## Demo Orders

```text
ORD-DEMO-PENDING
```

Customer-cancellable pending order.

```text
ORD-DEMO-PAID
```

Paid order ready for admin processing.

```text
ORD-DEMO-PROCESSING
```

Processing order ready for delivery shipping.

The paid and processing demo orders include completed payment records and status history so the admin and delivery scenarios can be tested right after seeding.

## Realtime Debug Page

Open:

```text
/_debug/reverb/order-status
```

Use a Bearer token and an order id to watch `OrderStatusUpdated` events over Reverb.

## Local Stripe Webhooks

The project includes a local Stripe CLI archive for Linux/Ubuntu:

```text
tools/stripe_1.41.1_linux_x86_64.tar.gz
```

Setup and webhook commands are documented here:

- [Stripe Webhooks CLI Notes](docs/stripe-webhooks-cli.md)

## Documentation

- [API Reference](docs/API_REFERENCE.md)
- [API Test Scenarios](docs/api-test-scenarios.md)
- [API Demo Script](docs/api-demo-script.md)
- [API QA Test Cases](docs/api-qa-test-cases.md)
- [PHP HTTP Examples](docs/php-http-examples.md)

## Postman

The collection is available at:

```text
postman/Laravel-12-Ecommerce.postman_collection.json
```

Import it into Postman, set the `base_url`, run one of the login requests, then use the returned Bearer token for protected endpoints.
