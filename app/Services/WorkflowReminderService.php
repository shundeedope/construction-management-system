<?php

namespace App\Services;

use App\Enums\ChangeOrderStatus;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\Permission;
use App\Enums\TimeEntryStatus;
use App\Models\ChangeOrder;
use App\Models\ComplianceRecord;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use App\Notifications\WorkflowReminder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;

class WorkflowReminderService
{
    public function send(): int
    {
        $sent = 0;

        foreach ($this->overdueInvoices() as $invoice) {
            foreach ($this->financialRecipients($invoice->project) as $recipient) {
                $sent += $this->notifyOnce($recipient, "overdue-invoice:{$invoice->getKey()}:".today()->toDateString(), 'Invoice overdue', "Client invoice {$invoice->number} is overdue.");
            }
        }

        foreach ($this->pendingTimeEntries() as $timeEntry) {
            foreach ($this->timeApprovalRecipients($timeEntry->project, $timeEntry->employee_id) as $recipient) {
                $sent += $this->notifyOnce($recipient, "pending-timesheet:{$timeEntry->getKey()}:".today()->toDateString(), 'Timesheet awaiting approval', "A timesheet for {$timeEntry->project->name} is awaiting approval.");
            }
        }

        foreach ($this->upcomingCompliance() as $record) {
            foreach (User::permission(Permission::ViewCompliance->value)->cursor() as $recipient) {
                if ($recipient->can('view', $record)) {
                    $sent += $this->notifyOnce($recipient, "compliance:{$record->getKey()}:".today()->toDateString(), 'Compliance requires attention', "{$record->requirement->name} requires attention.");
                }
            }
        }

        foreach ($this->rejectedChangeOrders() as $changeOrder) {
            if ($changeOrder->creator !== null) {
                $sent += $this->notifyOnce($changeOrder->creator, "change-order-rejected:{$changeOrder->getKey()}", 'Change order rejected', "Change order {$changeOrder->number} was rejected.");
            }
        }

        foreach ($this->rejectedInvoices() as $invoice) {
            if ($invoice->creator !== null) {
                $sent += $this->notifyOnce($invoice->creator, "invoice-rejected:{$invoice->getKey()}", 'Invoice rejected', "Invoice {$invoice->number} was rejected and requires correction.");
            }
        }

        return $sent;
    }

    /** @return Collection<int, Invoice> */
    private function overdueInvoices(): Collection
    {
        return Invoice::query()
            ->with('project:id,name')
            ->where('type', InvoiceType::Client)
            ->whereIn('status', [InvoiceStatus::Approved, InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid])
            ->whereDate('due_date', '<', today())
            ->get();
    }

    /** @return Collection<int, TimeEntry> */
    private function pendingTimeEntries(): Collection
    {
        return TimeEntry::query()->with('project:id,name')->where('status', TimeEntryStatus::Submitted)->get();
    }

    /** @return Collection<int, ComplianceRecord> */
    private function upcomingCompliance(): Collection
    {
        return ComplianceRecord::query()
            ->with(['requirement:id,name,default_reminder_days', 'project:id'])
            ->whereHas('requirement', fn ($requirements) => $requirements->where('is_active', true))
            ->where(function ($records): void {
                $records->whereNull('expires_at')
                    ->orWhereDate('expires_at', '<=', today()->addDays(app(CompanySettings::class)->complianceWarningDays()));
            })
            ->get()
            ->filter(fn (ComplianceRecord $record): bool => app(ComplianceStatusService::class)->statusFor($record)->needsAttention());
    }

    /** @return Collection<int, ChangeOrder> */
    private function rejectedChangeOrders(): Collection
    {
        return ChangeOrder::query()->with('creator:id,name')->where('status', ChangeOrderStatus::Rejected)->get();
    }

    /** @return Collection<int, Invoice> */
    private function rejectedInvoices(): Collection
    {
        return Invoice::query()->with('creator:id,name')->where('status', InvoiceStatus::Rejected)->get();
    }

    /** @return Collection<int, User> */
    private function financialRecipients(Project $project): Collection
    {
        return User::query()
            ->where(function ($users) use ($project): void {
                $users->permission(Permission::ViewAllFinancials->value)
                    ->orWhereHas('projects', fn ($projects) => $projects
                        ->whereKey($project->getKey())
                        ->where('project_user.can_view_financials', true));
            })
            ->get();
    }

    /** @return Collection<int, User> */
    private function timeApprovalRecipients(Project $project, int $employeeId): Collection
    {
        return User::permission(Permission::ApproveTimeEntries->value)
            ->whereHas('projects', fn ($projects) => $projects->whereKey($project->getKey()))
            ->whereDoesntHave('employee', fn ($employee) => $employee->whereKey($employeeId))
            ->get();
    }

    private function notifyOnce(User $recipient, string $key, string $title, string $body): int
    {
        $alreadySent = DatabaseNotification::query()
            ->where('notifiable_type', $recipient->getMorphClass())
            ->where('notifiable_id', $recipient->getKey())
            ->where('type', WorkflowReminder::class)
            ->get()
            ->contains(fn (DatabaseNotification $notification): bool => $notification->data['key'] === $key);

        if ($alreadySent) {
            return 0;
        }

        $recipient->notify((new WorkflowReminder($key, $title, $body))->afterCommit());

        return 1;
    }
}
