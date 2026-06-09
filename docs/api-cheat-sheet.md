# API Cheat Sheet

Minimal command set for a quick demo.

Replace `CUSTOMER_TOKEN`, `ADMIN_TOKEN`, `DELIVERY_TOKEN`, `PENDING_ORDER_ID`, and `PAID_ORDER_ID` before use.

## Login

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

## Customer

List own orders:

```bash
curl http://127.0.0.1:8000/api/orders \
  -H "Accept: application/json" \
  -H "Authorization: Bearer CUSTOMER_TOKEN"
```

Cancel own pending order:

```bash
curl -X POST http://127.0.0.1:8000/api/orders/PENDING_ORDER_ID/cancel \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer CUSTOMER_TOKEN" \
  -d '{"note":"Changed my mind."}'
```

## Admin

List all orders:

```bash
curl http://127.0.0.1:8000/api/admin/orders \
  -H "Accept: application/json" \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

Move paid order to processing:

```bash
curl -X PATCH http://127.0.0.1:8000/api/admin/orders/PAID_ORDER_ID/status \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -d '{"status":"processing","note":"Packed and ready."}'
```

## Delivery

Move processing order to shipped:

```bash
curl -X PATCH http://127.0.0.1:8000/api/delivery/orders/PAID_ORDER_ID/status \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer DELIVERY_TOKEN" \
  -d '{"status":"shipped","note":"Out for delivery."}'
```

## Stripe

```bash
stripe login
```

```bash
stripe listen --forward-to localhost:8000/api/webhooks/stripe
```

```bash
stripe payment_intents confirm pi_xxx --payment-method=pm_card_visa
```

## Reverb

```bash
php artisan reverb:start
```

## Mailpit

```bash
./tools/mailpit --listen 127.0.0.1:8026 --smtp 127.0.0.1:1025
```
