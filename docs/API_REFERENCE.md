# API Reference


Base URL:

```text
http://127.0.0.1:8000/api
```

Auth:

- Protected endpoints use `Authorization: Bearer {{access_token}}`
- Login or register first, then copy `access_token` into your Postman environment

Common variables:

- `{{product_id}}`
- `{{category_id}}`
- `{{cart_id}}`
- `{{order_id}}`
- `{{payment_id}}`

## Auth

### Admin

`POST /admin/register`

```json
{
  "name": "Admin One",
  "email": "admin1@mail.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

`POST /admin/login`

```json
{
  "email": "admin@mail.com",
  "password": "password"
}
```

`POST /admin/logout`

`GET /admin/me`

`GET /admin/session-info`

### Customer

`POST /customer/register`

```json
{
  "name": "Customer One",
  "email": "customer1@mail.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

`POST /customer/login`

```json
{
  "email": "customer@mail.com",
  "password": "password"
}
```

`POST /customer/logout`

`GET /customer/me`

`GET /customer/session-info`

### Delivery

`POST /delivery/register`

```json
{
  "name": "Delivery One",
  "email": "delivery1@mail.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

`POST /delivery/login`

```json
{
  "email": "delivery@mail.com",
  "password": "password"
}
```

`POST /delivery/logout`

`GET /delivery/me`

`GET /delivery/session-info`

## Products

### Public

`GET /products`

Query params:

- `page`
- `per_page`
- `search`
- `category`
- `min_price`
- `max_price`

Example:

```text
/products?page=1&per_page=9&search=iphone&category=1&min_price=100&max_price=3000
```

`GET /products/{product}`

### Protected product management

`POST /products`

Use `form-data`

- `name` text
- `description` text
- `price` text/number
- `stock` text/number
- `sku` text
- `is_active` text: `1`
- `categories[]` text: category ids
- `image` file
- `gallery[]` file

`POST /products/{product}` or `PUT /products/{product}`

Use `form-data`

- Any of:
  - `name`
  - `description`
  - `price`
  - `stock`
  - `sku`
  - `is_active`
  - `categories[]`
  - `image`
  - `gallery[]`

`DELETE /products/{product}`

`GET /admin/products`

`POST /products/{product}/restore`

`DELETE /products/{product}/permanent`

## Categories

### Public

`GET /categories`

`GET /categories/{category}`

`GET /categories/{category}/products`

### Protected category management

`POST /categories`

```json
{
  "name": "Accessories",
  "description": "Accessories category",
  "parent_id": 1,
  "is_active": true
}
```

`PUT /categories/{category}`

```json
{
  "name": "Accessories Updated",
  "description": "Updated description",
  "parent_id": 1,
  "is_active": true
}
```

`DELETE /categories/{category}`

## Cart

All cart endpoints are protected by `auth:sanctum` and `permission:create orders`.

`GET /cart`

`POST /cart`

```json
{
  "product_id": 1,
  "quantity": 2
}
```

`PUT /cart/{cart}`

```json
{
  "quantity": 3
}
```

`DELETE /cart/{cart}`

## Checkout

`POST /checkout`

`payment_method` is required and must be one of the configured providers, such as `stripe` or `paypal`.
The order currency is determined by the store configuration at checkout time and is reused for all payment attempts of that order.

```json
{
  "shipping_name": "John Doe",
  "shipping_address": "123 Main St",
  "shipping_city": "Cairo",
  "shipping_state": "Nasr City",
  "shipping_zipcode": "12345",
  "shipping_country": "Egypt",
  "shipping_phone": "01000000000",
  "payment_method": "stripe",
  "notes": "Leave at the front desk"
}
```

## Payments

`POST /checkout/{order}/payments`

Optional body:

```json
{
  "return_url": "http://127.0.0.1:8000/_debug/routes",
  "cancel_url": "http://127.0.0.1:8000/_debug/routes"
}
```

Notes:

- Creates a payment attempt for the order's selected `payment_method`
- Reuses the latest pending payment for the same provider instead of creating duplicates

`POST /checkout/payments/{payment}/confirm`

Optional body:

```json
{
  "provider_reference": "pi_xxx",
  "payment_method_id": "pm_card_visa",
  "return_url": "http://127.0.0.1:8000/_debug/routes"
}
```

Notes:

- `provider_reference` is usually the Stripe `payment_intent` id or equivalent provider reference
- `payment_method_id` is useful for Stripe confirmation flows
- `return_url` is only relevant for redirect-based payment methods; card test flows such as `pm_card_visa` usually return normal JSON responses

## Orders

### Customer-facing

`GET /orders`

Returns only the authenticated customer's own orders.

`GET /orders/{id}`

Returns one visible order with `items` and `statusHistories`.

`POST /orders/{order}/cancel`

```json
{
  "note": "Changed my mind."
}
```

Rules:

- Customers can cancel only their own `pending` orders
- Cancelled orders are not deleted
- Paid order cancellation is not customer-driven and should be handled through admin/refund workflows

### Admin-facing

`GET /admin/orders`

Supported query params:

- `search`
- `status`
- `payment_status`
- `payment_method`
- `user_id`
- `date_from`
- `date_to`
- `per_page`
- `sort_by`: `created_at`, `total`, `paid_at`
- `sort_direction`: `asc`, `desc`

Example:

```text
/admin/orders?search=sarah&status=paid&payment_method=stripe&per_page=10&sort_by=created_at&sort_direction=desc
```

Response notes:

- Paginated results
- Includes a compact `user`
- Includes `latest_status_history`
- Includes `items_count`, `payments_count`, and `status_histories_count`

`GET /admin/orders/{order}`

Returns full order details for admin review.

`PATCH /admin/orders/{order}/status`

```json
{
  "status": "processing",
  "note": "Packed and ready for fulfillment."
}
```

Rules:

- `paid` status is controlled only by the payment workflow
- Admins can move operational statuses such as `paid -> processing`
- Admins can cancel `pending` or `paid` orders according to workflow rules

### Delivery-facing

`PATCH /delivery/orders/{order}/status`

```json
{
  "status": "shipped",
  "note": "Out for delivery."
}
```

Rules:

- Delivery can move `processing -> shipped`
- Delivery can move `shipped -> delivered`
- Delivery cannot move orders to `processing`, `paid`, or `cancelled`

## Webhooks

`POST /webhooks/stripe`

Receives Stripe webhook events such as `payment_intent.succeeded`.

`POST /webhooks/paypal`

Receives PayPal webhook events.

## Realtime

The project broadcasts order status changes through Laravel Reverb.

Main event:

- `OrderStatusUpdated`

Main private channels:

- `orders.{orderId}`
- `users.{userId}.orders`

Broadcast auth route:

- `GET|POST /broadcasting/auth`

For this project, broadcast auth is configured with `auth:sanctum`, so Bearer tokens can authorize private channel subscriptions.

Debug page:

- `GET /_debug/reverb/order-status`

Purpose:

- Manual browser listener for order status events
- Useful for testing that private channel auth, Reverb connection, and payload delivery all work correctly

## Notes Before Release

- Login and register endpoints support optional `device_name`
- The auth flow uses one active token per device name
- Cancelled orders remain stored for audit/history purposes

## Suggested Postman flow

1. Login as customer
2. Save `access_token`
3. List products
4. Add product to cart
5. View cart
6. Checkout
7. Create payment for the returned order
8. Confirm payment
9. View orders