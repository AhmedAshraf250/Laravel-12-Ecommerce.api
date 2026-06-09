<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderStatusHistoryFactory extends Factory
{
    protected $model = OrderStatusHistory::class;

    public function definition()
    {
        return [
            'order_id' => Order::factory(), // Link to an existing order
            'created_by_type' => 'system',
            'created_by_id' => null,
            'from_status' => $this->faker->randomElement(\App\Enum\OrderStatus::values()),
            'to_status' => $this->faker->randomElement(\App\Enum\OrderStatus::values()),
            'note' => $this->faker->sentence(),
        ];
    }
}
