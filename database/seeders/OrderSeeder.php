<?php

namespace Database\Seeders;

use App\Enum\OrderStatus;
use App\Enum\PaymentProvider;
use App\Enum\PaymentStatus;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
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
        $processingProducts = Product::whereIn('sku', ['ELEC-002', 'FASH-001'])->get()->keyBy('sku');

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

        $paidOrder = $this->createOrder(
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

        $this->attachCompletedPayment($paidOrder, 'pi_demo_paid_order');
        $this->recordStatusHistory(
            $paidOrder,
            OrderStatus::PENDING,
            OrderStatus::PAID,
            'payment_provider',
            'stripe:pi_demo_paid_order',
            'Payment completed by stripe.'
        );

        $processingOrder = $this->createOrder(
            customer: $customer,
            orderNumber: 'ORD-DEMO-PROCESSING',
            status: OrderStatus::PROCESSING,
            paymentStatus: PaymentStatus::COMPLETED,
            products: [
                ['product' => $processingProducts['ELEC-002'], 'quantity' => 1],
                ['product' => $processingProducts['FASH-001'], 'quantity' => 1],
            ],
            note: 'Demo processing order ready for delivery shipping.',
            paidAt: now()->subHours(18),
        );

        $this->attachCompletedPayment($processingOrder, 'pi_demo_processing_order');
        $this->recordStatusHistory(
            $processingOrder,
            OrderStatus::PENDING,
            OrderStatus::PAID,
            'payment_provider',
            'stripe:pi_demo_processing_order',
            'Payment completed by stripe.'
        );
        $this->recordStatusHistory(
            $processingOrder,
            OrderStatus::PAID,
            OrderStatus::PROCESSING,
            'user',
            '1',
            'Packed and ready for fulfillment.'
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
    ): Order {
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

        return $order;
    }

    protected function attachCompletedPayment(Order $order, string $providerReference): Payment
    {
        return Payment::updateOrCreate(
            [
                'order_id' => $order->id,
                'provider_reference' => $providerReference,
            ],
            [
                'user_id' => $order->user_id,
                'provider' => PaymentProvider::STRIPE,
                'amount' => $order->total,
                'currency' => $order->currency,
                'status' => PaymentStatus::COMPLETED,
                'metadata' => [
                    'seeded' => true,
                    'provider_status' => 'succeeded',
                ],
                'completed_at' => $order->paid_at ?? now(),
            ],
        );
    }

    protected function recordStatusHistory(
        Order $order,
        OrderStatus $fromStatus,
        OrderStatus $toStatus,
        string $createdByType,
        string $createdById,
        string $note,
    ): OrderStatusHistory {
        return OrderStatusHistory::updateOrCreate(
            [
                'order_id' => $order->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
            ],
            [
                'created_by_type' => $createdByType,
                'created_by_id' => $createdById,
                'note' => $note,
            ],
        );
    }
}
