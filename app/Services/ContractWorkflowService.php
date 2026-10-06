<?php

namespace App\Services;

use App\Enums\ContractStatus;
use App\Models\Contract;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LogicException;

class ContractWorkflowService
{
    public function __construct(
        private ContractCalculationService $calculations,
        private ActivityLogger $activityLogger,
    ) {}

    public function submitForApproval(Contract $contract, User $actor): Contract
    {
        return $this->transition($contract, $actor, ContractStatus::PendingApproval, 'submitted_for_approval');
    }

    public function execute(Contract $contract, User $actor): Contract
    {
        return DB::transaction(function () use ($contract, $actor): Contract {
            /** @var Contract $lockedContract */
            $lockedContract = Contract::query()->lockForUpdate()->findOrFail($contract->getKey());

            if ($lockedContract->status === ContractStatus::Executed) {
                return $lockedContract;
            }

            $this->ensureTransition($lockedContract, ContractStatus::Executed);
            Gate::forUser($actor)->authorize('execute', $lockedContract);
            $this->calculations->syncOriginalAmount($lockedContract);
            $lockedContract->refresh();

            $from = $lockedContract->status;
            $lockedContract->update([
                'status' => ContractStatus::Executed,
                'executed_at' => now(),
                'approved_by' => $actor->getKey(),
            ]);
            $this->activityLogger->logTransition($lockedContract, 'executed', $from, ContractStatus::Executed);

            return $lockedContract->fresh();
        }, attempts: 3);
    }

    public function close(Contract $contract, User $actor): Contract
    {
        return $this->transition($contract, $actor, ContractStatus::Closed, 'closed');
    }

    public function cancel(Contract $contract, User $actor): Contract
    {
        return $this->transition($contract, $actor, ContractStatus::Cancelled, 'cancelled');
    }

    private function transition(Contract $contract, User $actor, ContractStatus $to, string $event): Contract
    {
        return DB::transaction(function () use ($contract, $actor, $to, $event): Contract {
            /** @var Contract $lockedContract */
            $lockedContract = Contract::query()->lockForUpdate()->findOrFail($contract->getKey());

            if ($lockedContract->status === $to) {
                return $lockedContract;
            }

            Gate::forUser($actor)->authorize(match ($to) {
                ContractStatus::PendingApproval => 'update',
                ContractStatus::Closed => 'close',
                ContractStatus::Cancelled => 'cancel',
                default => throw new LogicException('Unsupported contract transition.'),
            }, $lockedContract);

            $this->ensureTransition($lockedContract, $to);
            $from = $lockedContract->status;
            $lockedContract->update(['status' => $to]);
            $this->activityLogger->logTransition($lockedContract, $event, $from, $to);

            return $lockedContract->fresh();
        }, attempts: 3);
    }

    private function ensureTransition(Contract $contract, ContractStatus $to): void
    {
        if (! $contract->status->canTransitionTo($to)) {
            throw new LogicException("The contract cannot transition from [{$contract->status->value}] to [{$to->value}].");
        }
    }
}
