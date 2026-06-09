# API Demo Script

Short script for presenting the main workflow to a senior reviewer.

Replace `CUSTOMER_TOKEN`, `ADMIN_TOKEN`, `DELIVERY_TOKEN`, `PENDING_ORDER_ID`, and `PAID_ORDER_ID` before use.

## 1. Seed the demo data

```bash
php artisan migrate:fresh --seed
```

Use these seeded users:

- `admin@example.com / password`
- `customer@example.com / password`
- `delivery@example.com / password`

Login endpoints:

- `POST /api/admin/login`
- `POST /api/customer/login`
- `POST /api/delivery/login`

Demo orders:

- `ORD-DEMO-PENDING`
- `ORD-DEMO-PAID`

## 2. Show customer visibility

Explain:

`/api/orders` is customer-facing and only returns the authenticated customer's own orders.

Run:

```bash
curl http://127.0.0.1:8000/api/orders \
  -H "Accept: application/json" \
  -H "Authorization: Bearer CUSTOMER_TOKEN"
```

Point out:

- The customer can see their own orders
- This is not an admin management endpoint

## 3. Show customer cancellation rules

Explain:

Customers can cancel only their own `pending` orders.

Run:

```bash
curl -X POST http://127.0.0.1:8000/api/orders/PENDING_ORDER_ID/cancel \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer CUSTOMER_TOKEN" \
  -d '{"note":"Changed my mind."}'
```

Then show the blocked case:

```bash
curl -X POST http://127.0.0.1:8000/api/orders/PAID_ORDER_ID/cancel \
  -H "Accept: application/json" \
  -H "Authorization: Bearer CUSTOMER_TOKEN"
```

Point out:

- Pending cancellation succeeds
- Paid cancellation is rejected for customers

## 4. Show admin-friendly order management

Explain:

Admin management is intentionally separated under `/api/admin/orders`.

Run:

```bash
curl http://127.0.0.1:8000/api/admin/orders \
  -H "Accept: application/json" \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

Then move a paid order to processing:

```bash
curl -X PATCH http://127.0.0.1:8000/api/admin/orders/PAID_ORDER_ID/status \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -d '{"status":"processing","note":"Packed and ready for fulfillment."}'
```

Point out:

- Workflow transitions are validated
- Status history is recorded
- Notifications and broadcasts are triggered

## 5. Show delivery-specific actions

Explain:

Delivery staff have a narrower endpoint and narrower transition rights.

Run:

```bash
curl -X PATCH http://127.0.0.1:8000/api/delivery/orders/PAID_ORDER_ID/status \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer DELIVERY_TOKEN" \
  -d '{"status":"shipped","note":"Out for delivery."}'
```

Point out:

- Delivery can ship or deliver
- Delivery cannot move orders back to processing

## 6. Show that paid comes from the payment workflow

Explain:

`paid` is not an admin-driven status. It is controlled by the payment flow.

Run:

```bash
stripe login
```

```bash
stripe listen --forward-to localhost:8000/api/webhooks/stripe
```

```bash
stripe payment_intents confirm pi_xxx --payment-method=pm_card_visa
```

Point out:

- The payment provider confirms payment
- Laravel receives the webhook
- The order becomes `paid`
- The change is logged in order status history

## 7. Show realtime and email side effects

Run:

```bash
php artisan reverb:start
```

```bash
php artisan queue:work
```

```bash
./tools/mailpit --listen 127.0.0.1:8026 --smtp 127.0.0.1:1025
```

Open:

```text
http://127.0.0.1:8026
```

Point out:

- Reverb handles realtime broadcasting
- Mailpit captures the outgoing status update emails
