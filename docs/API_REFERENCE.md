# API Reference

Publish:

```url
https://documenter.getpostman.com/view/29717063/2sBXwpMAjV
```

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
  "email": "admin1@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

`POST /admin/login`

```json
{
  "email": "admin@example.com",
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
  "email": "customer1@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

`POST /customer/login`

```json
{
  "email": "customer@example.com",
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
  "email": "delivery1@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

`POST /delivery/login`

```json
{
  "email": "delivery@example.com",
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

`GET /products/admin`

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

```json
{
  "return_url": "https://example.com/payment/success",
  "cancel_url": "https://example.com/payment/cancel"
}
```

This endpoint uses the payment method already stored in the order during checkout. If `order.payment_method` is missing, the API returns a `422` business error.

`POST /checkout/payments/{payment}/confirm`

```json
{
  "provider_reference": "pi_test_123",
  "payment_method_id": "pm_card_visa",
  "metadata": {
    "confirmed_by": "postman"
  }
}
```

## Orders

`GET /orders`

`GET /orders/{id}`

## Webhooks

`POST /webhooks/stripe`

Send raw JSON body from Stripe event payload.

`POST /webhooks/paypal`

Send raw JSON body from PayPal event payload.

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
