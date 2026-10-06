<?php

use App\Enums\ChangeOrderStatus;
use App\Enums\ComplianceStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentDirection;
use App\Enums\ProjectStatus;
use App\Enums\TimeEntryStatus;
use App\Models\ChangeOrder;
use App\Models\ComplianceRecord;
use App\Models\Contract;
use App\Models\ContractLineItem;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

test('the cash flow chart has movement in every month it plots', function () {
    $months = collect(range(5, 0))
        ->map(fn (int $offset): string => CarbonImmutable::now()->startOfMonth()->subMonths($offset)->format('Y-m'));

    $payments = Payment::query()
        ->get(['direction', 'amount', 'payment_date'])
        ->groupBy(fn (Payment $payment): string => $payment->payment_date->format('Y-m'));

    foreach ($months as $month) {
        $inMonth = $payments->get($month, collect());

        expect($inMonth->where('direction', PaymentDirection::Incoming)->sum('amount'))
            ->toBeGreaterThan(0, "Nothing was received in {$month}, so the cash flow chart has a hole in it.")
            ->and($inMonth->where('direction', PaymentDirection::Outgoing)->sum('amount'))
            ->toBeGreaterThan(0, "Nothing was paid out in {$month}, so the cash flow chart has a hole in it.");
    }
});

test('every status a record can hold appears somewhere in the seeded data', function (string $model, string $enum) {
    $seeded = $model::query()->distinct()->pluck('status')
        ->map(fn ($status): string => $status instanceof BackedEnum ? $status->value : $status);

    $missing = collect($enum::cases())
        ->map(fn (BackedEnum $case): string => $case->value)
        ->diff($seeded)
        ->values();

    expect($missing->all())->toBe([], "The seeders never produce these statuses: {$missing->implode(', ')}.");
})->with([
    'projects' => [Project::class, ProjectStatus::class],
    'invoices' => [Invoice::class, InvoiceStatus::class],
    'change orders' => [ChangeOrder::class, ChangeOrderStatus::class],
    'time entries' => [TimeEntry::class, TimeEntryStatus::class],
    'compliance records' => [ComplianceRecord::class, ComplianceStatus::class],
]);

test('the portfolio carries a year of history behind it', function () {
    $billedMonths = Invoice::query()
        ->pluck('issue_date')
        ->map(fn ($issueDate): string => $issueDate->format('Y-m'))
        ->unique();

    expect(Project::query()->min('start_date'))->toBeLessThanOrEqual(today()->subMonths(11)->toDateString())
        ->and(Payment::query()->min('payment_date'))->toBeLessThanOrEqual(today()->subMonths(9)->toDateString())
        ->and($billedMonths->count())->toBeGreaterThan(9);
});

test('every contract is worth exactly the sum of its schedule of values', function () {
    $mismatched = Contract::query()
        ->withSum('lineItems as line_total', 'amount')
        ->get()
        ->filter(fn (Contract $contract): bool => (int) $contract->line_total !== $contract->original_amount);

    expect($mismatched->pluck('number')->all())->toBe([]);
});

test('every invoice totals its own lines, net of retention', function () {
    $mismatched = Invoice::query()
        ->with('lineItems')
        ->get()
        ->filter(function (Invoice $invoice): bool {
            $billed = $invoice->lineItems->sum('work_completed_this_period') + $invoice->lineItems->sum('materials_stored');

            return $billed !== $invoice->subtotal
                || $invoice->lineItems->sum('retention_amount') !== $invoice->retention_amount
                || $invoice->total !== $invoice->subtotal - $invoice->retention_amount;
        });

    expect($mismatched->pluck('number')->all())->toBe([]);
});

test('no contract line is billed past its scheduled value', function () {
    $countedStatuses = array_map(
        fn (InvoiceStatus $status): string => $status->value,
        array_filter(InvoiceStatus::cases(), fn (InvoiceStatus $status): bool => $status->countsTowardsProjectFinancials()),
    );

    $overbilled = ContractLineItem::query()
        ->get()
        ->filter(function (ContractLineItem $line) use ($countedStatuses): bool {
            $billed = (int) InvoiceLineItem::query()
                ->where('contract_line_item_id', $line->id)
                ->whereHas('invoice', fn ($invoices) => $invoices->whereIn('status', $countedStatuses))
                ->sum('work_completed_this_period');

            return $billed > $line->amount;
        });

    expect($overbilled->pluck('id')->all())->toBe([]);
});

test('no invoice is paid for more than it is worth', function () {
    $overpaid = Invoice::query()
        ->withSum('payments as paid', 'amount')
        ->get()
        ->filter(fn (Invoice $invoice): bool => (int) $invoice->paid > $invoice->total);

    expect($overpaid->pluck('number')->all())->toBe([]);
});

test('nothing is dated in the future', function () {
    expect(Invoice::query()->whereDate('issue_date', '>', today())->count())->toBe(0)
        ->and(Payment::query()->whereDate('payment_date', '>', today())->count())->toBe(0)
        ->and(TimeEntry::query()->whereDate('work_date', '>', today())->count())->toBe(0);
});
