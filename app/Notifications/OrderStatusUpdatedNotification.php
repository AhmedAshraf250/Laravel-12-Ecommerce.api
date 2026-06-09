<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public OrderStatusHistory $history,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = str($this->history->to_status->value)->headline()->toString();

        return (new MailMessage)
            ->subject("Order {$this->order->order_number} is now {$status}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your order {$this->order->order_number} status changed from {$this->history->from_status->value} to {$this->history->to_status->value}.")
            ->when($this->history->note, fn (MailMessage $message) => $message->line("Note: {$this->history->note}"))
            ->line('We will keep you posted as your order moves forward.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'from_status' => $this->history->from_status->value,
            'to_status' => $this->history->to_status->value,
            'note' => $this->history->note,
        ];
    }
}
