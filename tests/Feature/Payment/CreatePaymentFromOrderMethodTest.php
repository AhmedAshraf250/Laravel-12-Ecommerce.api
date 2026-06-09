<?php

use App\Contracts\Payments\PaymentGateway;
use App\Data\Payments\PaymentConfirmationResult;
use App\Data\Payments\PaymentInitializationResult;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a payment using the payment method stored on the order', function () {
    $user = User::factory()->create([
        'type' => 'customer',
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'currency' => 'USD',
        'payment_method' => PaymentProvider::STRIPE,
        'payment_status' => PaymentStatus::PENDING,
    ]);

    $gateway = \Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('createPayment')
        ->once()
        ->andReturn(new PaymentInitializationResult(
            status: PaymentStatus::PENDING,
            providerReference: 'pi_test_123',
            clientData: ['client_secret' => 'secret_test'],
            metadata: ['provider_status' => 'requires_payment_method'],
        ));

    $gatewayManager = \Mockery::mock(PaymentGatewayManager::class);
    $gatewayManager->shouldReceive('resolve')
        ->once()
        ->with(PaymentProvider::STRIPE)
        ->andReturn($gateway);

    app()->instance(PaymentGatewayManager::class, $gatewayManager);

    $this->actingAs($user, 'sanctum')
        ->withoutMiddleware()
        ->postJson("/api/checkout/{$order->id}/payments")
        ->assertCreated()
        ->assertJsonPath('status', true)
        ->assertJsonPath('payment.provider', PaymentProvider::STRIPE->value)
        ->assertJsonPath('payment.currency', 'USD')
        ->assertJsonPath('payment.provider_reference', 'pi_test_123')
        ->assertJsonPath('payment.metadata.client_data.client_secret', 'secret_test');
});

it('returns a business error when the order does not have a payment method selected', function () {
    $user = User::factory()->create([
        'type' => 'customer',
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'currency' => 'USD',
        'payment_method' => null,
        'payment_status' => PaymentStatus::PENDING,
    ]);

    $this->actingAs($user, 'sanctum')
        ->withoutMiddleware()
        ->postJson("/api/checkout/{$order->id}/payments")
        ->assertUnprocessable()
        ->assertJson([
            'message' => 'This order does not have a payment method selected.',
            'status' => false,
        ]);
});

it('reuses an existing pending payment instead of creating a duplicate one', function () {
    $user = User::factory()->create([
        'type' => 'customer',
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'currency' => 'USD',
        'payment_method' => PaymentProvider::STRIPE,
        'payment_status' => PaymentStatus::PENDING,
    ]);

    $gateway = \Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('createPayment')
        ->once()
        ->andReturn(new PaymentInitializationResult(
            status: PaymentStatus::PENDING,
            providerReference: 'pi_test_duplicate',
            clientData: ['client_secret' => 'secret_duplicate'],
            metadata: ['provider_status' => 'requires_payment_method'],
        ));

    $gatewayManager = \Mockery::mock(PaymentGatewayManager::class);
    $gatewayManager->shouldReceive('resolve')
        ->once()
        ->with(PaymentProvider::STRIPE)
        ->andReturn($gateway);

    app()->instance(PaymentGatewayManager::class, $gatewayManager);

    $firstResponse = $this->actingAs($user, 'sanctum')
        ->withoutMiddleware()
        ->postJson("/api/checkout/{$order->id}/payments")
        ->assertCreated()
        ->json();

    $secondResponse = $this->actingAs($user, 'sanctum')
        ->withoutMiddleware()
        ->postJson("/api/checkout/{$order->id}/payments")
        ->assertCreated()
        ->json();

    expect($firstResponse['payment']['id'])->toBe($secondResponse['payment']['id']);
    expect($secondResponse['payment']['provider_reference'])->toBe('pi_test_duplicate');
    expect($secondResponse['payment']['metadata']['client_data']['client_secret'])->toBe('secret_duplicate');
    expect($order->payments()->count())->toBe(1);
});

it('confirms a payment and returns a single payment resource', function () {
    $user = User::factory()->create([
        'type' => 'customer',
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'currency' => 'USD',
        'payment_method' => PaymentProvider::STRIPE,
        'payment_status' => PaymentStatus::PENDING,
    ]);

    $payment = Payment::query()->create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'provider' => PaymentProvider::STRIPE,
        'provider_reference' => 'pi_test_confirm',
        'amount' => $order->total,
        'currency' => 'USD',
        'status' => PaymentStatus::PENDING,
        'metadata' => [],
    ]);

    $gateway = \Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('confirmPayment')
        ->once()
        ->andReturn(new PaymentConfirmationResult(
            status: PaymentStatus::PENDING,
            providerReference: 'pi_test_confirm',
            message: 'Stripe payment status retrieved successfully.',
            metadata: ['provider_status' => 'requires_confirmation'],
        ));

    $gatewayManager = \Mockery::mock(PaymentGatewayManager::class);
    $gatewayManager->shouldReceive('resolve')
        ->once()
        ->with(PaymentProvider::STRIPE)
        ->andReturn($gateway);

    app()->instance(PaymentGatewayManager::class, $gatewayManager);

    $this->actingAs($user, 'sanctum')
        ->withoutMiddleware()
        ->postJson("/api/checkout/payments/{$payment->id}/confirm", [
            'provider_reference' => 'pi_test_confirm',
            'payment_method_id' => 'pm_card_visa',
            'return_url' => 'http://127.0.0.1:8000/_debug/routes',
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('payment.id', $payment->id)
        ->assertJsonPath('payment.provider_reference', 'pi_test_confirm')
        ->assertJsonPath('payment.order.id', $order->id);
});
