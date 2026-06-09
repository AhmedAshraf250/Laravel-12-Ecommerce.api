<?php

use App\Enum\OrderStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Events\OrderStatusUpdated;
use App\Exceptions\Orders\OrderStatusTransitionException;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusUpdatedNotification;
use App\Services\Orders\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('updates an order status and records realtime and email side effects', function () {
    Event::fake([OrderStatusUpdated::class]);
    Notification::fake();

    Permission::create(['name' => 'update orders']);

    $admin = User::factory()->create(['type' => 'admin']);
    $admin->givePermissionTo('update orders');

    $customer = User::factory()->create(['type' => 'customer']);
    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'status' => OrderStatus::PAID,
        'payment_status' => PaymentStatus::COMPLETED,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::PROCESSING->value,
            'note' => 'Packed and ready for fulfillment.',
        ])
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('order.status', OrderStatus::PROCESSING->value);

    expect($order->fresh()->status)->toBe(OrderStatus::PROCESSING);
    expect($order->statusHistories()->count())->toBe(1);

    $history = $order->statusHistories()->first();

    expect($history->from_status)->toBe(OrderStatus::PAID);
    expect($history->to_status)->toBe(OrderStatus::PROCESSING);
    expect($history->created_by_type)->toBe('user');
    expect($history->created_by_id)->toBe((string) $admin->id);

    Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event) => $event->order->id === $order->id);
    Notification::assertSentTo($customer, OrderStatusUpdatedNotification::class);
});

it('prevents moving an unpaid order into fulfillment', function () {
    Permission::create(['name' => 'update orders']);

    $admin = User::factory()->create(['type' => 'admin']);
    $admin->givePermissionTo('update orders');

    $order = Order::factory()->create([
        'status' => OrderStatus::PAID,
        'payment_status' => PaymentStatus::PENDING,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::PROCESSING->value,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Order must be paid before it can move forward.');

    expect($order->fresh()->status)->toBe(OrderStatus::PAID);
    expect($order->statusHistories()->count())->toBe(0);
});

it('prevents customers from updating order status', function () {
    $customer = User::factory()->create(['type' => 'customer']);
    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'status' => OrderStatus::PAID,
        'payment_status' => PaymentStatus::COMPLETED,
    ]);

    $this->actingAs($customer, 'sanctum')
        ->patchJson("/api/admin/orders/{$order->id}/status", [
            'status' => OrderStatus::PROCESSING->value,
        ])
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::PAID);
});

it('allows delivery staff to move processing orders to shipped', function () {
    Event::fake([OrderStatusUpdated::class]);
    Notification::fake();

    Permission::create(['name' => 'update delivery status']);

    $delivery = User::factory()->create(['type' => 'delivery']);
    $delivery->givePermissionTo('update delivery status');

    $order = Order::factory()->create([
        'status' => OrderStatus::PROCESSING,
        'payment_status' => PaymentStatus::COMPLETED,
    ]);

    $this->actingAs($delivery, 'sanctum')
        ->patchJson("/api/delivery/orders/{$order->id}/status", [
            'status' => OrderStatus::SHIPPED->value,
            'note' => 'Out for delivery.',
        ])
        ->assertOk()
        ->assertJsonPath('order.status', OrderStatus::SHIPPED->value);

    expect($order->fresh()->status)->toBe(OrderStatus::SHIPPED);
});

it('prevents delivery staff from moving orders to processing', function () {
    Permission::create(['name' => 'update delivery status']);

    $delivery = User::factory()->create(['type' => 'delivery']);
    $delivery->givePermissionTo('update delivery status');

    $order = Order::factory()->create([
        'status' => OrderStatus::PAID,
        'payment_status' => PaymentStatus::COMPLETED,
    ]);

    $this->actingAs($delivery, 'sanctum')
        ->patchJson("/api/delivery/orders/{$order->id}/status", [
            'status' => OrderStatus::PROCESSING->value,
        ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only order managers can move an order to this status.');

    expect($order->fresh()->status)->toBe(OrderStatus::PAID);
});

it('allows customers to cancel their own pending orders', function () {
    Event::fake([OrderStatusUpdated::class]);
    Notification::fake();

    Permission::create(['name' => 'cancel orders']);

    $customer = User::factory()->create(['type' => 'customer']);
    $customer->givePermissionTo('cancel orders');

    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::PENDING,
    ]);

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/orders/{$order->id}/cancel", [
            'note' => 'Changed my mind.',
        ])
        ->assertOk()
        ->assertJsonPath('order.status', OrderStatus::CANCELLED->value);

    expect($order->fresh()->status)->toBe(OrderStatus::CANCELLED);
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::PENDING);

    Event::assertDispatched(OrderStatusUpdated::class, fn (OrderStatusUpdated $event) => $event->order->id === $order->id);
    Notification::assertSentTo($customer, OrderStatusUpdatedNotification::class);
});

it('keeps cancelled orders visible in the customer order list', function () {
    Permission::create(['name' => 'view orders']);
    Permission::create(['name' => 'cancel orders']);

    $customer = User::factory()->create(['type' => 'customer']);
    $customer->givePermissionTo(['view orders', 'cancel orders']);

    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::PENDING,
    ]);

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/orders/{$order->id}/cancel", [
            'note' => 'Changed my mind.',
        ])
        ->assertOk();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/orders')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $order->id,
            'status' => OrderStatus::CANCELLED->value,
        ]);
});

it('prevents customers from cancelling paid orders directly', function () {
    Permission::create(['name' => 'cancel orders']);

    $customer = User::factory()->create(['type' => 'customer']);
    $customer->givePermissionTo('cancel orders');

    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'status' => OrderStatus::PAID,
        'payment_status' => PaymentStatus::COMPLETED,
        'paid_at' => now(),
    ]);

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/orders/{$order->id}/cancel")
        ->assertForbidden()
        ->assertJsonPath('message', 'Only order managers can cancel paid orders. Customers can only cancel their own pending orders.');

    expect($order->fresh()->status)->toBe(OrderStatus::PAID);
});

it('prevents direct paid status transitions without a completed payment', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::PENDING,
        'paid_at' => null,
    ]);

    app(OrderStatusService::class)->transition($order, OrderStatus::PAID);
})->throws(OrderStatusTransitionException::class, 'Paid status is managed by the payment workflow.');

it('prevents make paid from using an incomplete payment', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::PENDING,
        'paid_at' => null,
    ]);

    $payment = $order->payments()->create([
        'user_id' => $order->user_id,
        'provider' => PaymentProvider::STRIPE,
        'amount' => $order->total,
        'currency' => 'USD',
        'status' => PaymentStatus::PENDING,
        'metadata' => [],
    ]);

    $order->makePaid($payment);
})->throws(OrderStatusTransitionException::class, 'Order can only be marked as paid by a completed payment.');

it('marks the order as paid when a payment completes', function () {
    Event::fake([OrderStatusUpdated::class]);
    Notification::fake();

    $customer = User::factory()->create(['type' => 'customer']);
    $order = Order::factory()->create([
        'user_id' => $customer->id,
        'status' => OrderStatus::PENDING,
        'payment_method' => PaymentProvider::STRIPE,
        'payment_status' => PaymentStatus::PENDING,
    ]);

    $payment = $order->payments()->create([
        'user_id' => $customer->id,
        'provider' => PaymentProvider::STRIPE,
        'amount' => $order->total,
        'currency' => 'USD',
        'status' => PaymentStatus::PENDING,
        'metadata' => [],
    ]);

    $payment->markAsCompleted('pi_test_completed');

    expect($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED);
    expect($order->fresh()->status)->toBe(OrderStatus::PAID);
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::COMPLETED);
    expect($order->statusHistories()->count())->toBe(1);

    $history = $order->statusHistories()->first();

    expect($history->created_by_type)->toBe('payment_provider');
    expect($history->created_by_id)->toBe('stripe:pi_test_completed');

    Event::assertDispatched(OrderStatusUpdated::class);
    Notification::assertSentTo($customer, OrderStatusUpdatedNotification::class);
});
