<?php

use App\Enum\OrderStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admins to filter and search the order index', function () {
    $admin = User::factory()->create(['type' => 'admin']);
    $targetCustomer = User::factory()->create([
        'type' => 'customer',
        'name' => 'Sarah Connor',
        'email' => 'sarah@example.com',
    ]);

    $matchedOrder = Order::factory()->create([
        'user_id' => $targetCustomer->id,
        'order_number' => 'ORD-2026-SARAH1',
        'status' => OrderStatus::PAID,
        'payment_status' => PaymentStatus::COMPLETED,
        'payment_method' => PaymentProvider::STRIPE,
        'shipping_name' => 'Sarah Connor',
    ]);

    OrderStatusHistory::query()->create([
        'order_id' => $matchedOrder->id,
        'created_by_type' => 'user',
        'created_by_id' => (string) $admin->id,
        'from_status' => OrderStatus::PENDING,
        'to_status' => OrderStatus::PAID,
        'note' => 'Payment confirmed.',
    ]);

    Order::factory()->create([
        'order_number' => 'ORD-2026-OTHER1',
        'status' => OrderStatus::PENDING,
        'payment_status' => PaymentStatus::PENDING,
        'payment_method' => PaymentProvider::PAYPAL,
        'shipping_name' => 'Different Customer',
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/orders?search=sarah&status=paid&payment_method=stripe&per_page=5')
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('orders.total', 1)
        ->assertJsonPath('orders.per_page', 5)
        ->assertJsonPath('orders.data.0.id', $matchedOrder->id)
        ->assertJsonPath('orders.data.0.order_number', 'ORD-2026-SARAH1')
        ->assertJsonPath('orders.data.0.items_count', 0)
        ->assertJsonPath('orders.data.0.payments_count', 0)
        ->assertJsonPath('orders.data.0.latest_status_history.to_status', OrderStatus::PAID->value)
        ->assertJsonPath('orders.data.0.user.email', 'sarah@example.com');
});

it('returns paginated admin order results with requested sorting', function () {
    $admin = User::factory()->create(['type' => 'admin']);

    $smallerOrder = Order::factory()->create([
        'total' => 100,
        'created_at' => now()->subDay(),
    ]);

    $largerOrder = Order::factory()->create([
        'total' => 300,
        'created_at' => now(),
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/admin/orders?sort_by=total&sort_direction=asc&per_page=1')
        ->assertOk()
        ->assertJsonPath('orders.total', 2)
        ->assertJsonPath('orders.per_page', 1)
        ->assertJsonPath('orders.current_page', 1)
        ->assertJsonPath('orders.data.0.id', $smallerOrder->id)
        ->assertJsonCount(1, 'orders.data')
        ->assertJsonPath('orders.next_page_url', 'http://localhost/api/admin/orders?sort_by=total&sort_direction=asc&per_page=1&page=2');
});
