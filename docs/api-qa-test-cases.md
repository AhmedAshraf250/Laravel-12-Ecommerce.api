# API QA Test Cases

Focused QA cases for the most important order management scenarios.

Replace `CUSTOMER_TOKEN`, `ADMIN_TOKEN`, `DELIVERY_TOKEN`, and the order IDs before use.

## Case 1: Customer can list their own orders

Purpose:

Verify customer order visibility is scoped to the authenticated user.

Request:

```http
GET /api/orders
Authorization: Bearer CUSTOMER_TOKEN
```

Expected:

- Response is `200`
- Only the customer's orders are returned

## Case 2: Customer can cancel a pending order

Purpose:

Verify customer cancellation is allowed only for their own pending order.

Request:

```http
POST /api/orders/{pending_order_id}/cancel
Authorization: Bearer CUSTOMER_TOKEN
Content-Type: application/json

{
  "note": "Changed my mind."
}
```

Expected:

- Response is `200`
- `order.status = cancelled`
- `payment_status` remains `pending`
- A status history record is created

## Case 3: Customer cannot cancel a paid order

Purpose:

Verify customers cannot directly cancel paid orders.

Request:

```http
POST /api/orders/{paid_order_id}/cancel
Authorization: Bearer CUSTOMER_TOKEN
```

Expected:

- Response is `403`
- Order status remains unchanged

## Case 4: Admin can move paid order to processing

Purpose:

Verify admin can perform operational workflow transitions.

Request:

```http
PATCH /api/admin/orders/{paid_order_id}/status
Authorization: Bearer ADMIN_TOKEN
Content-Type: application/json

{
  "status": "processing",
  "note": "Packed and ready."
}
```

Expected:

- Response is `200`
- `order.status = processing`
- A status history record is created
- Broadcast event is dispatched
- Email notification is queued/sent

## Case 5: Delivery can move processing order to shipped

Purpose:

Verify delivery access is limited to shipment-oriented transitions.

Request:

```http
PATCH /api/delivery/orders/{processing_order_id}/status
Authorization: Bearer DELIVERY_TOKEN
Content-Type: application/json

{
  "status": "shipped",
  "note": "Out for delivery."
}
```

Expected:

- Response is `200`
- `order.status = shipped`

## Case 6: Delivery cannot move order to processing

Purpose:

Verify delivery users cannot perform admin-only transitions.

Request:

```http
PATCH /api/delivery/orders/{paid_order_id}/status
Authorization: Bearer DELIVERY_TOKEN
Content-Type: application/json

{
  "status": "processing"
}
```

Expected:

- Response is `403`
- Order status remains unchanged

## Case 7: Paid cannot be set manually by admin/customer

Purpose:

Verify `paid` is controlled by the payment workflow only.

Request:

Attempt any direct status transition to `paid` outside the payment flow.

Expected:

- Request or service call is rejected
- Error indicates `paid` is managed by the payment workflow

## Case 8: Payment completion marks order as paid

Purpose:

Verify payment success is the valid entry point for the `paid` status.

Steps:

1. Create or reuse a pending payment
2. Confirm it through Stripe/payment workflow
3. Let the webhook or confirmation logic complete

Expected:

- Payment status becomes `completed`
- Order status becomes `paid`
- `paid_at` is set
- Status history is created with `created_by_type = payment_provider`

## Case 9: Broadcast event is emitted on status change

Purpose:

Verify realtime updates are produced when order status changes.

Trigger:

Any successful transition such as `paid -> processing`

Expected:

- `OrderStatusUpdated` is dispatched
- Event payload includes:
  - `order_id`
  - `from_status`
  - `to_status`
  - `payment_status`
  - `created_by_type`
  - `created_by_id`

## Case 10: Email notification is produced on status change

Purpose:

Verify the customer is notified when order status changes.

Trigger:

Any successful transition such as `processing -> shipped`

Expected:

- A queued mail notification is generated
- Mailpit shows the email when local SMTP is enabled
