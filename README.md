# Construction Management System

This project demonstrates how to build a **construction-management panel** with Filament — not a CRUD demo, but a full project-delivery workflow: owner contracts with a Schedule of Values, subcontracts, change-order approval, client and subcontractor invoicing with partial payments and PDF generation, a weekly timesheet grid, compliance tracking, and a financial dashboard. There's also a second, separate Filament panel for subcontractors.

The key idea: Filament stays a thin UI layer. Every workflow transition (submit/approve/reject a change order, approve/send/void an invoice, record a payment) lives in a typed service class that locks the record, authorizes the actor, validates the business rules, and writes an activity-log entry — the Filament actions just call those services. All money is stored as **integer cents** behind a dedicated `Money` value object, and every table and select is scoped to the projects the user is actually assigned to.

The repository contains the complete Laravel + Filament project to demonstrate the functionality, including migrations/seeds for the demo data.

The Filament project is in the `app/Filament` folder.

Feel free to pick the parts that you actually need in your projects.

---

## How to install

- Clone the repository with `git clone`
- Copy the `.env.example` file to `.env` and edit database credentials there (the default is SQLite — run `touch database/database.sqlite` if you keep it)
- Run `composer install`
- Run `php artisan key:generate`
- Run `php artisan migrate --seed` (it seeds a full "Riverside" demo project: a $500,000 owner contract, subcontracts, pending change orders, partial payments, overdue invoices, compliance warnings, and submitted timesheets)
- Run `npm install && npm run build`
- That's it: launch the URL `/admin` and log in with credentials `admin@example.com` and `password`

All seeded accounts share the password `password`, and each demonstrates a different permission set:

| Role | Email | What they see |
| --- | --- | --- |
| Administrator | `admin@example.com` | Everything |
| Project Manager | `manager@example.com` | Assigned projects with financial access |
| Accounting | `accounting@example.com` | Global financial access |
| Site Supervisor | `supervisor@example.com` | Documents and time approval, no financials |
| Employee | `employee@example.com` | Own timesheet and assigned-project documents |
| Executive | `executive@example.com` | Global read-only visibility |

---

## Screenshots

![](https://laraveldaily.com/uploads/2026/07/filamentexamples-construction-1.png)

![](https://laraveldaily.com/uploads/2026/07/filamentexamples-construction-2.png)

![](https://laraveldaily.com/uploads/2026/07/filamentexamples-construction-3.png)

---

## How It Works

The panel is organized around the real construction workflow: client → project → owner contract with a Schedule of Values → subcontracts → change orders → invoices → payments → time approval → dashboard reporting. Each section below is one of those pieces, plus the two foundations everything sits on: exact money math and project-scoped authorization.

### 1. Money as Integer Cents — the `Money` Value Object

Every financial column in the database is an integer number of cents, and every calculation goes through one `final readonly` value object, so no float rounding drift can ever reach the database.

**app/Support/Money.php**

```php
final readonly class Money implements Stringable
{
    private function __construct(public int $cents) {}

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public function plus(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    /**
     * Apply a rate stored as basis points, where 1% = 100 and 10% = 1000.
     */
    public function basisPoints(int $basisPoints): self
    {
        return new self(self::divideRoundingHalfAwayFromZero($this->cents * $basisPoints, 10_000));
    }

    public static function divideRoundingHalfAwayFromZero(int $numerator, int $denominator): int
    {
        $sign = ($numerator < 0) === ($denominator < 0) ? 1 : -1;
        $absoluteNumerator = abs($numerator);
        $absoluteDenominator = abs($denominator);

        return $sign * intdiv(2 * $absoluteNumerator + $absoluteDenominator, 2 * $absoluteDenominator);
    }
}
```

The key points:

- **Every arithmetic operation stays in the integer domain** — `plus()`, `minus()`, `times()`, and `basisPoints()` all return a new `Money` built from integer math, so summing a 40-line Schedule of Values or applying 10% retention can never produce `1999.9999999998`
- **Percentages are basis points, not floats** — retention is stored as an integer like `1000` (10%), and `basisPoints()` is the *single* rounding rule for every "percentage of money" in the app, so retention on a contract and retention on an invoice always round identically
- **`divideRoundingHalfAwayFromZero()` avoids floats even for rounding** — the `2 * numerator + denominator` trick performs half-away-from-zero rounding purely with `intdiv()`, which matters for negative amounts (credits, deductive change orders) where PHP's float rounding would disagree
- **`format()` delegates to a `MoneyFormatter` service** reading the company's configured currency, so widgets and tables just call `$metrics['margin']->format()` and display logic never leaks into the domain math

### 2. Project-Scoped Authorization — Scopes That Mirror the Policies

Access control has two layers: 20 policies decide *can this user touch this record*, and query scopes on `Project` decide *which rows show up in the first place*. The scopes are applied to every Filament table and select, so users never even see projects they aren't assigned to.

**app/Models/Project.php**

```php
#[Scope]
protected function visibleTo(Builder $query, User $user): void
{
    if ($user->can(Permission::ViewAllProjects->value)) {
        return;
    }

    $query->whereHas('users', fn (Builder $assigned) => $assigned->whereKey($user->getKey()));
}

#[Scope]
protected function financiallyVisibleTo(Builder $query, User $user): void
{
    if ($user->can(Permission::ViewAllFinancials->value)) {
        return;
    }

    $query->whereHas('users', fn (Builder $assigned) => $assigned
        ->whereKey($user->getKey())
        ->where('project_user.can_view_financials', true));
}
```

The key points:

- **The scope is the query-level counterpart to `ProjectPolicy::view()`** — policies protect individual records, but a Filament table needs the *list* filtered too; defining both against the same `project_user` assignments keeps them from drifting apart
- **Financial visibility is a per-assignment pivot flag, not a role** — the same site supervisor can manage documents on a project while `can_view_financials` stays `false`, so contract values and invoices simply don't exist in their queries
- **Global permissions short-circuit the scope** — accounting and executives hold `view_all_financials`, so their queries skip the `whereHas` entirely instead of joining against thousands of assignment rows
- **Financial records require both layers** — seeing a client invoice requires a relevant global permission *and* financial access to that project, which is exactly the combination `financiallyVisibleTo()` encodes

### 3. Contracts and the Schedule of Values — `ContractCalculationService`

An owner contract's value isn't typed in as one number — it's the sum of its Schedule of Values line items, and the *revised* value adds every approved change order. One service owns those calculations and the invariants around them.

**app/Services/ContractCalculationService.php**

```php
public function originalAmount(Contract $contract): Money
{
    return Money::fromCents((int) $contract->lineItems()->sum('amount'));
}

public function revisedAmount(Contract $contract): Money
{
    return Money::fromCents((int) $contract->original_amount)->plus($this->approvedChangeAmount($contract));
}

public function ensureOrganizationInvariants(Contract $contract): void
{
    $project = $contract->relationLoaded('project') ? $contract->project : Project::query()->findOrFail($contract->project_id);

    if ($contract->type === ContractType::Owner) {
        if ($contract->client_id !== $project->client_id || $contract->vendor_id !== null) {
            throw ValidationException::withMessages([
                'client_id' => 'An owner contract must use the project client and cannot have a vendor.',
            ]);
        }

        return;
    }

    if ($contract->vendor_id === null || $contract->client_id !== null) {
        throw ValidationException::withMessages([
            'vendor_id' => 'A subcontract or purchase order requires a vendor and cannot have a client.',
        ]);
    }
}
```

The key points:

- **`original_amount` is a synced denormalization, not an input** — `syncOriginalAmount()` recomputes the SoV sum inside a `lockForUpdate()` transaction whenever line items change, so the stored total can never disagree with its lines
- **`revisedAmount()` = original + approved change orders** — pending and rejected changes contribute nothing, which is the industry rule: a change order only moves the contract value once it's approved
- **`ensureOrganizationInvariants()` enforces who sits on each side** — an owner (revenue) contract must point at the project's client and never a vendor, while subcontracts and purchase orders require a vendor; this is thrown as a `ValidationException`, so Filament renders it as a normal field error
- **Retention rides the `Money::basisPoints()` rule** — `retention()` applies the contract's `retention_basis_points` to any amount, so the 10% held back from a $185,000 subcontract is computed by the same code path as every other percentage

### 4. Change-Order Workflow — Locked, Authorized State Transitions

Change orders move Draft → Submitted → Approved/Rejected. The whole lifecycle is one `transition()` method that locks the row, authorizes the specific transition, checks the enum's transition map, and logs the result — every public method is a thin wrapper around it.

**app/Services/ChangeOrderWorkflowService.php**

```php
private function transition(ChangeOrder $changeOrder, User $actor, ChangeOrderStatus $to, string $event, callable $attributes): ChangeOrder
{
    return DB::transaction(function () use ($changeOrder, $actor, $to, $event, $attributes): ChangeOrder {
        $lockedChangeOrder = ChangeOrder::query()->lockForUpdate()->findOrFail($changeOrder->getKey());

        Gate::forUser($actor)->authorize(match ($to) {
            ChangeOrderStatus::Submitted => 'submit',
            ChangeOrderStatus::Approved => 'approve',
            ChangeOrderStatus::Rejected => 'reject',
            default => throw new LogicException('Unsupported change-order transition.'),
        }, $lockedChangeOrder);

        if (! $lockedChangeOrder->status->canTransitionTo($to)) {
            throw new LogicException("The change order cannot transition from [{$lockedChangeOrder->status->value}] to [{$to->value}].");
        }

        $from = $lockedChangeOrder->status;
        $lockedChangeOrder->update(['status' => $to, ...$attributes($lockedChangeOrder)]);
        $this->activityLogger->logTransition($lockedChangeOrder, $event, $from, $to);
        $this->notifications->changeOrderTransitioned($lockedChangeOrder, $actor, $to);

        return $lockedChangeOrder->fresh();
    }, attempts: 3);
}
```

The Filament side stays declarative — one class of shared actions used by both the table and the edit page:

**app/Filament/Resources/ChangeOrders/Actions/ChangeOrderWorkflowActions.php**

```php
public static function approve(): Action
{
    return Action::make('approve')
        ->requiresConfirmation()
        ->color('success')
        ->icon(Heroicon::OutlinedCheckCircle)
        ->visible(fn (ChangeOrder $record): bool => $record->status === ChangeOrderStatus::Submitted)
        ->authorize('approve')
        ->successNotificationTitle('Change order approved')
        ->action(fn (ChangeOrder $record, ChangeOrderWorkflowService $workflow) => $workflow->approve($record, auth()->user()));
}
```

The key points:

- **`lockForUpdate()` re-fetches inside the transaction** — the record the button was clicked on may be stale by the time the action runs; locking and re-reading means two managers approving simultaneously can't double-apply a transition, and `attempts: 3` retries deadlocks
- **Authorization happens *inside* the service, per transition** — the Filament action also declares `->authorize('approve')` to hide the button, but the service re-checks with `Gate::forUser($actor)`, so no code path (bulk action, command, future API) can skip the policy
- **`canTransitionTo()` lives on the `ChangeOrderStatus` enum** — the legal state graph is one source of truth on the enum, so the UI's `->visible()` checks and the service's guard can never disagree about what Draft is allowed to become
- **`ensureSubmittable()` validates the domain, not just the state** — submission requires an executed contract on the same project, a change-order type matching the contract side (owner vs. subcontract), and at least one line item; each failure surfaces as a field-level `ValidationException`
- **Filament injects the service into the closure** — `fn (ChangeOrder $record, ChangeOrderWorkflowService $workflow)` resolves the service from the container, so the action body is a single delegating line and the same actions class serves the table row buttons and the edit-page header

### 5. Invoicing and Partial Payments — `PaymentService`

Client invoices flow Draft → Approved → Sent → Partially Paid → Paid (subcontractor invoices mirror it on the payable side). Payments are the interesting part: each one is validated against the invoice's remaining balance, and the invoice status is *derived* from the sum of its payments.

**app/Services/PaymentService.php**

```php
public function record(Invoice $invoice, User $actor, array $attributes): Payment
{
    return DB::transaction(function () use ($invoice, $actor, $attributes): Payment {
        $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->getKey());
        Gate::forUser($actor)->authorize('create', Payment::class);
        Gate::forUser($actor)->authorize('view', $lockedInvoice);

        $this->validatePayment($lockedInvoice, $attributes);
        $amount = (int) $attributes['amount'];
        $paidAmount = (int) $lockedInvoice->payments()->sum('amount');

        if ($paidAmount + $amount > $lockedInvoice->total) {
            throw ValidationException::withMessages([
                'amount' => 'The payment exceeds the remaining invoice balance.',
            ]);
        }

        $payment = $lockedInvoice->payments()->create([/* ... */]);

        $this->recalculateStatus($lockedInvoice, $paidAmount + $amount);
        $this->activityLogger->log($payment, 'payment_recorded', ['amount' => $payment->amount]);

        return $payment;
    }, attempts: 3);
}

private function recalculateStatus(Invoice $invoice, int $paidAmount): void
{
    $status = match (true) {
        $paidAmount >= $invoice->total => InvoiceStatus::Paid,
        $paidAmount > 0 => InvoiceStatus::PartiallyPaid,
        $invoice->type->isReceivable() && $invoice->sent_at !== null => InvoiceStatus::Sent,
        default => InvoiceStatus::Approved,
    };

    if ($invoice->status !== $status) {
        $invoice->update(['status' => $status]);
    }
}
```

The key points:

- **The overpayment guard sums payments *under the same lock*** — the invoice is locked, then `payments()->sum('amount')` runs inside the transaction, so two accountants recording payments at once can't jointly exceed the invoice total
- **Status is recalculated, never set by hand** — `recalculateStatus()` derives Paid / Partially Paid / Sent / Approved from the paid total, and `remove()` reuses it after deleting a payment, so unwinding a mistaken payment automatically rolls the invoice status back too
- **`validatePayment()` cross-checks direction and project** — an incoming payment can't be attached to a payable invoice and a payment can't point at a different project than its invoice; the enum's `paymentDirection()` ties `InvoiceType::Client` to incoming and `Subcontractor` to outgoing
- **PDFs are a route, not a job** — the "Download PDF" action is just `->url(fn (Invoice $record) => route('client-invoices.pdf', $record))` rendered by DomPDF on demand, and "Preview PDF" wraps the same route with `?inline=1` in a Filament modal, so there's no queue or external service to configure

### 6. Weekly Timesheet — A Custom Filament Page as a Grid

Time entry doesn't fit a CRUD resource — employees think in a week × project grid. `WeeklyTimesheet` is a custom Filament page whose Livewire state is exactly that grid: hours keyed by project id, then work date.

**app/Filament/Pages/WeeklyTimesheet.php**

```php
class WeeklyTimesheet extends Page
{
    protected string $view = 'filament.pages.weekly-timesheet';

    /**
     * Regular hours keyed by project id, then work date (Y-m-d).
     *
     * @var array<int, array<string, string>>
     */
    public array $hours = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_own_time_entries') === true;
    }

    public function saveDraft(): bool
    {
        // ...
        foreach ($this->hours as $projectId => $days) {
            foreach ($days as $date => $value) {
                $entry = $entries->get("{$projectId}|{$date}");
                $newHours = blank($value) ? 0.0 : (float) $value;

                if ($entry !== null && ! $this->isEditable($entry)) {
                    continue;
                }

                if ($entry === null && $newHours > 0) {
                    $service->create($employee, [/* ... */], $user);
                } elseif ($entry !== null && $newHours <= 0 && $entry->status === TimeEntryStatus::Draft) {
                    Gate::authorize('delete', $entry);
                    $entry->delete();
                } elseif ($entry !== null && abs((float) $entry->regular_hours - $newHours) >= 0.005) {
                    Gate::authorize('update', $entry);
                    $entry->update(['regular_hours' => $newHours]);
                }
            }
        }
        // ...
    }

    public function isEditable(?TimeEntry $entry): bool
    {
        return $entry === null || in_array($entry->status, [TimeEntryStatus::Draft, TimeEntryStatus::Rejected], strict: true);
    }
}
```

The key points:

- **`saveDraft()` diffs the grid against existing entries** — a filled cell with no entry creates one, an emptied draft cell deletes one, and a changed cell updates; untouched cells cost nothing, so saving a week is a handful of writes, not 7 × N upserts
- **Submitted and approved cells go read-only in place** — `isEditable()` keys off the entry status, so once "Submit week" sends entries for approval they lock in the grid (and rejected entries unlock again for correction) without any separate "history" screen
- **The page guards itself with `canAccess()`** — the whole page only exists for users holding `manage_own_time_entries`, while each individual write still passes `Gate::authorize()`, mirroring the two-layer authorization used everywhere else
- **Approval is the supervisor's side of the same service** — `TimeEntryApprovalService` powers both the employee's submit and the approver's approve/reject, and the pending count feeds the dashboard's attention widget (next section)

### 7. Dashboard — Permission-Aware Metrics in One Service

The dashboard shows an executive financial overview plus an "attention" strip (overdue invoices, pending change orders, pending timesheets, expiring compliance). Every number comes from `DashboardMetricsService`, and every number respects the viewer's project scope.

**app/Services/DashboardMetricsService.php**

```php
public function financial(User $user): array
{
    $projects = Project::query()->financiallyVisibleTo($user);
    $projectIds = (clone $projects)->select('id');

    // ... original owner value + commitments, summed in SQL ...

    $revisedValue = Money::fromCents($originalOwnerValue + $approvedOwnerChanges);
    $revisedCommitments = Money::fromCents($originalCommitments + $approvedSubcontractChanges);

    return [
        'active_projects' => (clone $projects)->where('status', ProjectStatus::Active)->count(),
        'revised_value' => $revisedValue,
        'receivables' => $this->outstanding($projectIds, InvoiceType::Client),
        'payables' => $this->outstanding($projectIds, InvoiceType::Subcontractor),
        'margin' => $revisedValue->minus($revisedCommitments),
    ];
}

public function attention(User $user): array
{
    return [
        'overdue_invoices' => $user->can(Permission::ViewFinancials->value)
            ? Invoice::query()->whereIn('project_id', $financialProjectIds)/* ... */->whereDate('due_date', '<', today())->count()
            : 0,
        'pending_timesheets' => $user->can(Permission::ApproveTimeEntries->value)
            ? TimeEntry::query()->whereIn('project_id', $visibleProjectIds)->where('status', TimeEntryStatus::Submitted)->count()
            : 0,
        // ...
    ];
}
```

The key points:

- **Every metric starts from `financiallyVisibleTo($user)`** — the same scope from section 2 feeds a `whereIn('project_id', $projectIds)` subquery into each aggregate, so a project manager's dashboard totals only their projects while accounting sees everything, with zero widget-level branching
- **Gross margin falls out of the domain rules** — revised value (owner contracts + approved owner changes) minus revised commitments (subcontracts + POs + approved subcontract changes), each side summed in SQL and only combined as `Money`, reusing the "approved changes only" rule from section 3
- **Receivables are invoiced-minus-paid, not a stored balance** — `outstanding()` subtracts the payment sum from the invoice sum per direction, so a partial payment recorded in section 5 moves the dashboard instantly with nothing to sync
- **Each attention count is gated by the permission that makes it actionable** — a user who can't approve time entries gets `0` pending timesheets rather than a count they can't act on, and widgets like `FinancialOverview` additionally hide themselves entirely via `canView()`

### 8. Compliance Tracking — Derived Status with a Warning Window

Insurance certificates and licenses expire; the panel derives each record's status from its expiry date and surfaces anything that needs attention, scoped to the projects the viewer can see.

**app/Services/ComplianceStatusService.php**

```php
public function statusFor(ComplianceRecord $record, ?Carbon $today = null): ComplianceStatus
{
    $today ??= now()->startOfDay();

    if ($record->expires_at === null) {
        return ComplianceStatus::Missing;
    }

    if ($record->expires_at->lt($today)) {
        return ComplianceStatus::Expired;
    }

    $warningDays = $record->requirement->default_reminder_days ?? app(CompanySettings::class)->complianceWarningDays();

    return $record->expires_at->lte($today->copy()->addDays($warningDays))
        ? ComplianceStatus::ExpiringSoon
        : ComplianceStatus::Valid;
}
```

The key points:

- **Status is computed from the date, not stored as the truth** — `statusFor()` derives Missing / Expired / Expiring Soon / Valid on demand, so a certificate silently crossing its expiry date can never be stuck showing "valid"; `refresh()` writes the derived value back only as a convenience for table filtering
- **The warning window cascades from requirement to company default** — each compliance requirement can set its own `default_reminder_days`, falling back to the company-wide setting, so "warn 30 days out for insurance but 90 for licenses" is data, not code
- **`warningsFor()` respects project scoping** — the dashboard's expiring-compliance count only includes records on projects `visibleTo()` the user (plus company-wide records), filtered again per record by the policy, consistent with every other number in the panel
- **Verification is an audited action** — `verify()` authorizes against the policy and stamps `verified_by` / `verified_at`, so compliance sign-off is attributable just like every workflow transition in the activity log

---

That's it — a `Money` value object keeping every calculation in integer cents, project scopes mirroring the policies, contract values summed from a Schedule of Values, locked-and-authorized workflow services for change orders, invoices, and payments, a grid-based timesheet page, and a dashboard that derives every figure from the same rules. Filament provides the resources, actions, custom pages, and widgets; the construction domain lives entirely in plain Laravel services, enums, and policies — which is also why the separate subcontractor panel (`app/Filament/Subcontractor`) could be added on top of the exact same services without duplicating any business logic.
