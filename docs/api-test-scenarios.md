# API Test Scenarios

A quick reference for testing the main APIs and workflows during a demo or review.

## Seed Data

After running:

```bash
php artisan migrate:fresh --seed
```

Users:

- `admin@example.com / password`
- `customer@example.com / password`
- `delivery@example.com / password`

Orders:

- `ORD-DEMO-PENDING`:
  A `pending` order that the customer can cancel.
- `ORD-DEMO-PAID`:
  A `paid` order that the admin can move to `processing`.

## Environment

Update these values before use:

```bash
CUSTOMER_TOKEN=replace_customer_token
ADMIN_TOKEN=replace_admin_token
DELIVERY_TOKEN=replace_delivery_token
PENDING_ORDER_ID=replace_pending_order_id
PAID_ORDER_ID=replace_paid_order_id
PAYMENT_ID=replace_payment_id
```

## Auth

Purpose: get the tokens used in the rest of the scenarios.

```bash
curl -X POST http://127.0.0.1:8000/api/customer/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"customer@example.com","password":"password"}'
```

```bash
curl -X POST http://127.0.0.1:8000/api/admin/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password"}'
```

```bash
curl -X POST http://127.0.0.1:8000/api/delivery/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"delivery@example.com","password":"password"}'
```

## Customer Orders

Purpose: the customer can view only their own orders.

```bash
curl http://127.0.0.1:8000/api/orders \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN"
```

Purpose: show one order in detail.

```bash
curl http://127.0.0.1:8000/api/orders/$PENDING_ORDER_ID \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN"
```

## Cancel Pending Order

Purpose: the customer cancels their own `pending` order.

```bash
curl -X POST http://127.0.0.1:8000/api/orders/$PENDING_ORDER_ID/cancel \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN" \
  -d '{"note":"Changed my mind."}'
```

Expected: success and the order status becomes `cancelled`.

## Cancel Paid Order As Customer

Purpose: prove that a customer cannot directly cancel a `paid` order.

```bash
curl -X POST http://127.0.0.1:8000/api/orders/$PAID_ORDER_ID/cancel \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN"
```

Expected: `403`.

## Admin Order List

Purpose: the admin can view all orders.

```bash
curl http://127.0.0.1:8000/api/admin/orders \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN"
```

## Admin Update Status

Purpose: the admin moves the order from `paid` to `processing`.

```bash
curl -X PATCH http://127.0.0.1:8000/api/admin/orders/$PAID_ORDER_ID/status \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -d '{"status":"processing","note":"Packed and ready for fulfillment."}'
```

Expected: success, a history record, a notification, and a broadcast event.

## Delivery Update Status

Purpose: delivery staff move the order from `processing` to `shipped`.

```bash
curl -X PATCH http://127.0.0.1:8000/api/delivery/orders/$PAID_ORDER_ID/status \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $DELIVERY_TOKEN" \
  -d '{"status":"shipped","note":"Out for delivery."}'
```

Expected: success only if the order is already `processing`.

## Delivery Invalid Transition

Purpose: prove that delivery staff cannot move an order to `processing`.

```bash
curl -X PATCH http://127.0.0.1:8000/api/delivery/orders/$PAID_ORDER_ID/status \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $DELIVERY_TOKEN" \
  -d '{"status":"processing"}'
```

Expected: `403`.

## Create Checkout Payment

Purpose: create a payment for an order.

```bash
curl -X POST http://127.0.0.1:8000/api/checkout/$PAID_ORDER_ID/payments \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN"
```

Expected: a new payment is created, or the existing pending payment is reused.

## Confirm Payment

Purpose: update the payment state from the confirm endpoint.

```bash
curl -X POST http://127.0.0.1:8000/api/checkout/payments/$PAYMENT_ID/confirm \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $CUSTOMER_TOKEN"
```

## Stripe Local Demo

Purpose: prove that `paid` comes from the payment workflow, not from admin status updates.

```bash
stripe login
```

```bash
stripe listen --forward-to localhost:8000/api/webhooks/stripe
```

Use the returned `whsec_...` value in:

```env
STRIPE_WEBHOOK_SECRET=replace_me
```

Then:

```bash
stripe payment_intents confirm pi_xxx --payment-method=pm_card_visa
```

Expected: the webhook hits Laravel and the order becomes `paid`.

## Reverb Demo

Purpose: test realtime updates.

Run:

```bash
php artisan serve
```

```bash
php artisan reverb:start
```

```bash
php artisan queue:work
```

Then trigger a status change from admin or delivery and watch the client subscribed to:

- `private-orders.{orderId}`
- `private-users.{userId}.orders`

## Mailpit Demo

Purpose: inspect emails sent after order status changes.

```bash
./tools/mailpit --listen 127.0.0.1:8026 --smtp 127.0.0.1:1025
```

Open:

```text
http://127.0.0.1:8026
```

Expected: an email arrives after each important order transition.
