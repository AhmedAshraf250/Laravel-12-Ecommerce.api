# Stripe Webhooks CLI Notes

This project keeps a local Stripe CLI archive at:

```text
tools/stripe_1.41.1_linux_x86_64.tar.gz
```

These notes are for Ubuntu/Linux local testing. The goal is to forward Stripe webhook events to the Laravel API and verify that payment events can move orders through the payment workflow.

## Install Locally

Extract the archive into a local tools folder:

```bash
mkdir -p tools/stripe-cli
tar -xzf tools/stripe_1.41.1_linux_x86_64.tar.gz -C tools/stripe-cli
```

Check that the binary works:

```bash
./tools/stripe-cli/stripe --version
```

Optional convenience alias for the current terminal session:

```bash
alias stripe="$PWD/tools/stripe-cli/stripe"
```

## Login

```bash
stripe login
```

This authorizes the local CLI against your Stripe account. It is required before using commands such as `listen`, `trigger`, or `payment_intents confirm`.

## Forward Webhooks To Laravel

Keep Laravel running:

```bash
php artisan serve
```

Start the Stripe webhook listener:

```bash
stripe listen --forward-to localhost:8000/api/webhooks/stripe
```

Copy the `whsec_...` value printed by Stripe into `.env`:

```env
STRIPE_WEBHOOK_SECRET=whsec_replace_me
```

Then clear config:

```bash
php artisan config:clear
```

## Useful Scenarios

Trigger a generic succeeded event:

```bash
stripe trigger payment_intent.succeeded
```

This is useful to prove that the webhook endpoint receives Stripe events. It may not update a local payment record if the generated PaymentIntent does not match a `provider_reference` stored in your database.

Confirm a real PaymentIntent created by this API:

```bash
stripe payment_intents confirm pi_xxx --payment-method=pm_card_visa
```

This is the better demo path when testing the full local workflow because the PaymentIntent belongs to a payment row created by the app.

Confirm with a return URL when Stripe asks for one:

```bash
stripe payment_intents confirm pi_xxx \
  --payment-method=pm_card_visa \
  --return-url=http://127.0.0.1:8000/_debug/routes
```

## Expected Local Flow

1. Create an order from checkout.
2. Create a payment with `POST /api/checkout/{order}/payments`.
3. Confirm the Stripe PaymentIntent.
4. Stripe sends `payment_intent.succeeded` to `/api/webhooks/stripe`.
5. Laravel marks the payment as `completed`.
6. The related order moves to `paid`.
7. Order status history, Reverb broadcast, and customer notification are produced.

## Helpful Local Commands

Watch payment logs:

```bash
tail -f storage/logs/daily/payments/payments-$(date +%F).log
```

Run queued mail/broadcast jobs if the queue driver is not `sync`:

```bash
php artisan queue:work
```

Start Reverb when testing realtime order updates:

```bash
php artisan reverb:start
```
