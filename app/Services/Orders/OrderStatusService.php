<?php

namespace App\Services\Orders;

use App\Enum\OrderStatus;
use App\Enum\PaymentStatus;
use App\Events\OrderStatusUpdated;
use App\Exceptions\Orders\OrderStatusTransitionException;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use App\Notifications\OrderStatusUpdatedNotification;
use Illuminate\Support\Facades\DB;

class OrderStatusService
{
    public function transition(
        Order $order,
        OrderStatus $toStatus,
        ?User $createdBy = null,
        ?string $note = null,
        string $createdByType = 'system',
        ?string $createdById = null,
    ): Order
    {
        /** @var array{0: Order, 1: OrderStatusHistory} $result */
        $result = DB::transaction(function () use ($order, $toStatus, $createdBy, $note, $createdByType, $createdById): array {
            $order = $order->lockForUpdate()->findOrFail($order->id);
            $fromStatus = $order->status;
            [$creatorType, $creatorId] = $this->resolveCreator($createdBy, $createdByType, $createdById);

            $this->ensureCreatorCanTransition($order, $createdBy, $creatorType, $toStatus);
            $this->ensureCanTransition($order, $toStatus);

            $order->update(['status' => $toStatus]);

            /** @var OrderStatusHistory $history */
            $history = $order->statusHistories()->create([
                'created_by_type' => $creatorType,
                'created_by_id' => $creatorId,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'note' => $note,
            ]);

            return [$order->fresh(['user', 'items', 'statusHistories']), $history];
        });

        [$order, $history] = $result;

        DB::afterCommit(function () use ($order, $history) {
            event(new OrderStatusUpdated($order, $history));
            $order->user?->notify(new OrderStatusUpdatedNotification($order, $history));
        });

        return $order;
    }

    protected function ensureCanTransition(Order $order, OrderStatus $toStatus): void
    {
        if ($order->status === $toStatus) {
            throw new OrderStatusTransitionException("Order is already {$toStatus->value}.");
        }

        if (! $order->status->canTransitionTo($toStatus)) {
            throw new OrderStatusTransitionException(
                "Order cannot transition from {$order->status->value} to {$toStatus->value}."
            );
        }

        if ($toStatus === OrderStatus::PAID) {
            if ($order->payment_status !== PaymentStatus::COMPLETED || ! $order->paid_at) {
                throw new OrderStatusTransitionException('Paid status requires a completed payment.');
            }

            return;
        }

        if (
            in_array($toStatus, [OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED], true)
            && $order->payment_status !== PaymentStatus::COMPLETED
        ) {
            throw new OrderStatusTransitionException('Order must be paid before it can move forward.');
        }
    }

    protected function ensureCreatorCanTransition(
        Order $order,
        ?User $createdBy,
        string $createdByType,
        OrderStatus $toStatus,
    ): void {
        if ($toStatus === OrderStatus::PAID && $createdByType !== 'payment_provider') {
            throw new OrderStatusTransitionException('Paid status is managed by the payment workflow.');
        }

        if (! $createdBy) {
            return;
        }

        if ($toStatus === OrderStatus::CANCELLED) {
            $isOrderManager = $createdBy->can('update orders');
            $isCustomerCancellingOwnPendingOrder = $createdBy->can('cancel orders')
                && (int) $order->user_id === (int) $createdBy->id
                && $order->status === OrderStatus::PENDING;

            if (! ($isOrderManager || $isCustomerCancellingOwnPendingOrder)) {
                throw new OrderStatusTransitionException('Only order managers can cancel paid orders. Customers can only cancel their own pending orders.', 403);
            }

            return;
        }

        if ($toStatus === OrderStatus::PROCESSING && ! $createdBy->can('update orders')) {
            throw new OrderStatusTransitionException('Only order managers can move an order to this status.', 403);
        }

        if (
            in_array($toStatus, [OrderStatus::SHIPPED, OrderStatus::DELIVERED], true)
            && ! ($createdBy->can('update orders') || $createdBy->can('update delivery status'))
        ) {
            throw new OrderStatusTransitionException('Only order managers or delivery staff can move an order to this status.', 403);
        }
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function resolveCreator(?User $createdBy, string $createdByType, ?string $createdById): array
    {
        if ($createdBy) {
            return ['user', (string) $createdBy->id];
        }

        return [$createdByType, $createdById];
    }
}
