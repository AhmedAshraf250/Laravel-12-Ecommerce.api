# PHP HTTP Request Examples

This file shows the same login request using different tools and abstraction levels.

Target endpoint:

```text
POST /api/customer/login
```

Request body:

```json
{
  "email": "customer@mail.com",
  "password": "password",
  "device_name": "postman"
}
```

## curl

```bash
curl -X POST http://127.0.0.1:8000/api/customer/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"email":"customer@mail.com","password":"password","device_name":"postman"}'
```

## Pure PHP With file_get_contents()

```php
<?php

$payload = json_encode([
    'email' => 'customer@mail.com',
    'password' => 'password',
    'device_name' => 'postman',
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", [
            'Accept: application/json',
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
        ]),
        'content' => $payload,
        'ignore_errors' => true,
    ],
]);

echo file_get_contents('http://127.0.0.1:8000/api/customer/login', false, $context);
```

## Pure PHP With cURL

```php
<?php

$payload = json_encode([
    'email' => 'customer@mail.com',
    'password' => 'password',
    'device_name' => 'postman',
]);

$ch = curl_init('http://127.0.0.1:8000/api/customer/login');

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => $payload,
]);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

echo "Status: {$statusCode}\n";
echo $response;
```

## Guzzle

```php
<?php

use GuzzleHttp\Client;

$client = new Client();

$response = $client->post('http://127.0.0.1:8000/api/customer/login', [
    'headers' => [
        'Accept' => 'application/json',
    ],
    'json' => [
        'email' => 'customer@mail.com',
        'password' => 'password',
        'device_name' => 'postman',
    ],
]);

echo $response->getBody()->getContents();
```

## Laravel HTTP Client

```php
<?php

use Illuminate\Support\Facades\Http;

$response = Http::acceptJson()->post('http://127.0.0.1:8000/api/customer/login', [
    'email' => 'customer@mail.com',
    'password' => 'password',
    'device_name' => 'postman',
]);

dump($response->status());
dump($response->json());
```
