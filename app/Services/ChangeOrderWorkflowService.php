<?php

namespace App\Services;

use App\Enums\ChangeOrderStatus;
use App\Enums\ChangeOrderType;
use App\Enums\ContractStatus;
use App\Models\ChangeOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;

class ChangeOrderWorkflowService
{
    public function __construct(
        private ActivityLogger $activityLogger,
        private WorkflowNotificationService $notifications,
    ) {}

    public function submit(ChangeOrder $changeOrder, User $actor): ChangeOrder
    {
        return $this->transition($changeOrder, $actor, ChangeOrderStatus::Submitted, 'submitted', function (ChangeOrder $lockedChangeOrder): array {
            $this->ensureSubmittable($lockedChangeOrder);

            return ['submitted_at' => now()];
        });
    }

    public function approve(ChangeOrder $changeOrder, User $actor): ChangeOrder
    {
        return $this->transition($changeOrder, $actor, ChangeOrderStatus::Approved, 'approved', fn (): array => [
            'approved_at' => now(),
            'approved_by' => $actor->getKey(),
        ]);
    }

    public function reject(ChangeOrder $changeOrder, User $actor, string $reason): ChangeOrder
    {
        if (blank($reason)) {
            throw ValidationException::withMessages([
                'rejection_reason' => 'A rejection reason is required.',
            ]);
        }

        return $this->transition($changeOrder, $actor, ChangeOrderStatus::Rejected, 'rejected', fn (): array => [
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * @param  callable(ChangeOrder): array<string, mixed>  $attributes
     */
    private function transition(ChangeOrder $changeOrder, User $actor, ChangeOrderStatus $to, string $event, callable $attributes): ChangeOrder
    {
        return DB::transaction(function () use ($changeOrder, $actor, $to, $event, $attributes): ChangeOrder {
            /** @var ChangeOrder $lockedChangeOrder */
            $lockedChangeOrder = ChangeOrder::query()->lockForUpdate()->findOrFail($changeOrder->getKey());

            Gate::forUser($actor)->authorize(match ($to) {
                ChangeOrderStatus::Submitted => 'submit',
                ChangeOrderStatus::Approved => 'approve',
                ChangeOrderStatus::Rejected => 'reject',
                default => throw new LogicException('Unsupported change-order transition.'),
            }, $lockedChangeOrder);

            if ($lockedChangeOrder->status === $to) {
                return $lockedChangeOrder;
            }

            if (! $lockedChangeOrder->status->canTransitionTo($to)) {
                throw new LogicException("The change order cannot transition from [{$lockedChangeOrder->status->value}] to [{$to->value}].");
            }

            $from = $lockedChangeOrder->status;
            $lockedChangeOrder->update([
                'status' => $to,
                ...$attributes($lockedChangeOrder),
            ]);
            $this->activityLogger->logTransition($lockedChangeOrder, $event, $from, $to);
            $this->notifications->changeOrderTransitioned($lockedChangeOrder, $actor, $to);

            return $lockedChangeOrder->fresh();
        }, attempts: 3);
    }

    private function ensureSubmittable(ChangeOrder $changeOrder): void
    {
        $contract = $changeOrder->contract()->firstOrFail();

        if ($contract->project_id !== $changeOrder->project_id) {
            throw ValidationException::withMessages([
                'contract_id' => 'The change order contract must belong to the selected project.',
            ]);
        }

        if ($contract->status !== ContractStatus::Executed) {
            throw ValidationException::withMessages([
                'contract_id' => 'Change orders may only be submitted for executed contracts.',
            ]);
        }

        $expectedType = $contract->type->isRevenue() ? ChangeOrderType::Owner : ChangeOrderType::Subcontract;

        if ($changeOrder->type !== $expectedType) {
            throw ValidationException::withMessages([
                'type' => 'The change-order type must match the contract type.',
            ]);
        }

        if (! $changeOrder->lineItems()->exists()) {
            throw ValidationException::withMessages([
                'line_items' => 'At least one change-order line is required before submission.',
            ]);
        }
    }
}
