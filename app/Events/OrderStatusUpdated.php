<?php

namespace App\Events;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Order $order,
        public OrderStatusHistory $history,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("orders.{$this->order->id}"),
            new PrivateChannel("users.{$this->order->user_id}.orders"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'from_status' => $this->history->from_status->value,
            'to_status' => $this->history->to_status->value,
            'payment_status' => $this->order->payment_status->value,
            'note' => $this->history->note,
            'created_by_type' => $this->history->created_by_type,
            'created_by_id' => $this->history->created_by_id,
            'changed_at' => $this->history->created_at?->toISOString(),
        ];
    }
}
