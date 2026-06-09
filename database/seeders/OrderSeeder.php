<?php

namespace Database\Seeders;

use App\Enum\OrderStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customer = User::where('email', 'customer@mail.com')->firstOrFail();

        $pendingProducts = Product::whereIn('sku', ['ELEC-001', 'HOME-001'])->get()->keyBy('sku');
        $paidProducts = Product::whereIn('sku', ['FASH-002', 'HOME-002'])->get()->keyBy('sku');

        $this->createOrder(
            customer: $customer,
            orderNumber: 'ORD-DEMO-PENDING',
            status: OrderStatus::PENDING,
            paymentStatus: PaymentStatus::PENDING,
            products: [
                ['product' => $pendingProducts['ELEC-001'], 'quantity' => 1],
                ['product' => $pendingProducts['HOME-001'], 'quantity' => 2],
            ],
            note: 'Demo pending order that the customer can cancel.',
        );

        $this->createOrder(
            customer: $customer,
            orderNumber: 'ORD-DEMO-PAID',
            status: OrderStatus::PAID,
            paymentStatus: PaymentStatus::COMPLETED,
            products: [
                ['product' => $paidProducts['FASH-002'], 'quantity' => 1],
                ['product' => $paidProducts['HOME-002'], 'quantity' => 1],
            ],
            note: 'Demo paid order ready for admin processing.',
            paidAt: now()->subDay(),
        );
    }

    protected function createOrder(
        User $customer,
        string $orderNumber,
        OrderStatus $status,
        PaymentStatus $paymentStatus,
        array $products,
        string $note,
        $paidAt = null,
    ): void {
        $subtotal = collect($products)->sum(fn(array $item) => round($item['product']->price * $item['quantity'], 2));
        $tax = round($subtotal * 0.08, 2);
        $shippingCost = 5.00;
        $total = round($subtotal + $tax + $shippingCost, 2);

        $order = Order::updateOrCreate(
            ['order_number' => $orderNumber],
            [
                'user_id' => $customer->id,
                'cookie_id' => null,
                'status' => $status,
                'shipping_name' => $customer->name,
                'shipping_address' => '123 Demo Street',
                'shipping_city' => 'Cairo',
                'shipping_state' => 'Cairo',
                'shipping_zipcode' => '11511',
                'shipping_country' => 'Egypt',
                'shipping_phone' => '+201000000000',
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping_cost' => $shippingCost,
                'total' => $total,
                'currency' => 'USD',
                'payment_method' => PaymentProvider::STRIPE,
                'payment_status' => $paymentStatus,
                'paid_at' => $paidAt,
                'notes' => $note,
            ],
        );

        $order->items()->delete();

        foreach ($products as $item) {
            $product = $item['product'];
            $quantity = $item['quantity'];

            $order->items()->create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => $quantity,
                'price' => $product->price,
                'subtotal' => round($product->price * $quantity, 2),
            ]);
        }
    }
}
