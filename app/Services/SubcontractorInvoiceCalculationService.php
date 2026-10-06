<?php

namespace App\Services;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubcontractorInvoiceCalculationService
{
    public function __construct(private ContractCalculationService $contractCalculations) {}

    /** @return array{subtotal: Money, retention: Money, total: Money} */
    public function sync(Invoice $invoice): array
    {
        return DB::transaction(function () use ($invoice): array {
            /** @var Invoice $lockedInvoice */
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());

            if ($lockedInvoice->status->isFinancesLocked()) {
                throw ValidationException::withMessages([
                    'invoice' => 'Finalized invoice values cannot be recalculated.',
                ]);
            }

            return $this->syncLocked($lockedInvoice);
        }, attempts: 3);
    }

    /** @return array{subtotal: Money, retention: Money, total: Money} */
    public function syncLocked(Invoice $invoice): array
    {
        $contract = $invoice->contract()->lockForUpdate()->firstOrFail();
        $this->ensureSubcontractorInvoiceContract($invoice, $contract);

        $lines = $invoice->lineItems()->lockForUpdate()->orderBy('sort_order')->get();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages([
                'line_items' => 'At least one Schedule of Values line is required.',
            ]);
        }

        if ($lines->pluck('contract_line_item_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'line_items' => 'Each contract line may only be billed once per invoice.',
            ]);
        }

        $contractLines = ContractLineItem::query()
            ->whereBelongsTo($contract)
            ->whereKey($lines->pluck('contract_line_item_id'))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($contractLines->count() !== $lines->count()) {
            throw ValidationException::withMessages([
                'line_items' => 'Every invoice line must belong to the selected subcontract or purchase order.',
            ]);
        }

        $priorBilling = $this->priorBillingByContractLine($invoice);
        $subtotal = Money::zero();
        $retention = Money::zero();

        foreach ($lines as $line) {
            $this->ensureNonNegative($line);
            $contractLine = $contractLines->get($line->contract_line_item_id);
            $previouslyBilled = Money::fromCents((int) ($priorBilling[$line->contract_line_item_id] ?? 0));
            $workThisPeriod = Money::fromCents((int) $line->work_completed_this_period);
            $materialsStored = Money::fromCents((int) $line->materials_stored);
            $grossCurrentBilling = $workThisPeriod->plus($materialsStored);
            $lineRetention = $grossCurrentBilling->basisPoints($contract->retention_basis_points);
            $scheduledValue = Money::fromCents((int) $contractLine->amount);

            if ($previouslyBilled->plus($grossCurrentBilling)->greaterThan($scheduledValue)) {
                throw ValidationException::withMessages([
                    "line_items.{$line->getKey()}" => "Billing exceeds the scheduled value for [{$contractLine->description}].",
                ]);
            }

            $line->update([
                'scheduled_value' => $scheduledValue->cents,
                'previously_billed' => $previouslyBilled->cents,
                'retention_amount' => $lineRetention->cents,
                'current_due' => $grossCurrentBilling->minus($lineRetention)->cents,
            ]);

            $subtotal = $subtotal->plus($grossCurrentBilling);
            $retention = $retention->plus($lineRetention);
        }

        $this->ensureAggregateNotOverbilled($invoice, $contract, $subtotal);
        $total = $subtotal->minus($retention);
        $invoice->update(['subtotal' => $subtotal->cents, 'retention_amount' => $retention->cents, 'total' => $total->cents]);

        return compact('subtotal', 'retention', 'total');
    }

    private function ensureSubcontractorInvoiceContract(Invoice $invoice, Contract $contract): void
    {
        if ($invoice->type !== InvoiceType::Subcontractor
            || ! in_array($contract->type, [ContractType::Subcontract, ContractType::PurchaseOrder], strict: true)
            || $contract->project_id !== $invoice->project_id
            || $contract->vendor_id === null) {
            throw ValidationException::withMessages([
                'contract_id' => 'A subcontractor invoice requires a vendor subcontract or purchase order from the selected project.',
            ]);
        }

        if ($contract->status !== ContractStatus::Executed) {
            throw ValidationException::withMessages([
                'contract_id' => 'Subcontractor invoices may only use executed subcontracts or purchase orders.',
            ]);
        }
    }

    /** @return array<int, int> */
    private function priorBillingByContractLine(Invoice $invoice): array
    {
        return InvoiceLineItem::query()
            ->whereHas('invoice', fn ($query) => $query
                ->where('contract_id', $invoice->contract_id)
                ->whereIn('status', $this->financialStatuses())
                ->whereKeyNot($invoice->getKey()))
            ->selectRaw('contract_line_item_id, SUM(work_completed_this_period + materials_stored) as billed')
            ->groupBy('contract_line_item_id')
            ->pluck('billed', 'contract_line_item_id')
            ->map(fn ($amount): int => (int) $amount)
            ->all();
    }

    private function ensureAggregateNotOverbilled(Invoice $invoice, Contract $contract, Money $currentSubtotal): void
    {
        $priorSubtotal = (int) Invoice::query()
            ->where('contract_id', $invoice->contract_id)
            ->whereIn('status', $this->financialStatuses())
            ->whereKeyNot($invoice->getKey())
            ->sum('subtotal');

        if (Money::fromCents($priorSubtotal)->plus($currentSubtotal)->greaterThan($this->contractCalculations->revisedAmount($contract))) {
            throw ValidationException::withMessages([
                'subtotal' => 'Billing exceeds the revised subcontract commitment.',
            ]);
        }
    }

    /** @return array<int, string> */
    private function financialStatuses(): array
    {
        return array_map(
            fn (InvoiceStatus $status): string => $status->value,
            array_filter(InvoiceStatus::cases(), fn (InvoiceStatus $status): bool => $status->countsTowardsProjectFinancials()),
        );
    }

    private function ensureNonNegative(InvoiceLineItem $line): void
    {
        if ($line->work_completed_this_period < 0 || $line->materials_stored < 0) {
            throw ValidationException::withMessages([
                "line_items.{$line->getKey()}" => 'Work completed and stored materials cannot be negative.',
            ]);
        }
    }
}
