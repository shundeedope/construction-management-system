<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->vendor_id !== null || $user->canAny([
            Permission::ViewFinancials->value,
            Permission::ViewAllFinancials->value,
        ]);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->ownsVendorInvoice($invoice) || $user->canViewFinancialsFor($invoice->project);
    }

    public function create(User $user): bool
    {
        return $user->vendor_id !== null || $user->can(Permission::ManageInvoices->value);
    }

    /**
     * Values are frozen once an invoice leaves draft, so that billing history cannot be
     * rewritten after it has been approved, sent, or paid.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return ($user->ownsVendorInvoice($invoice) || $user->can(Permission::ManageInvoices->value)
            && $user->canViewFinancialsFor($invoice->project)
        ) && ! $invoice->status->isFinancesLocked();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::ManageInvoices->value)
            && $user->canViewFinancialsFor($invoice->project)
            && $invoice->status === InvoiceStatus::Draft;
    }

    public function submit(User $user, Invoice $invoice): bool
    {
        return ($user->ownsVendorInvoice($invoice) || $user->can(Permission::ManageInvoices->value)
            && $user->canViewFinancialsFor($invoice->project)
        ) && $invoice->status->canTransitionTo(InvoiceStatus::Submitted);
    }

    public function resubmit(User $user, Invoice $invoice): bool
    {
        return $user->ownsVendorInvoice($invoice)
            && $invoice->status->canTransitionTo(InvoiceStatus::Draft);
    }

    public function approve(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::ApproveInvoices->value)
            && $user->canViewFinancialsFor($invoice->project)
            && ($invoice->status === InvoiceStatus::Approved || $invoice->status->canTransitionTo(InvoiceStatus::Approved));
    }

    public function reject(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::ApproveInvoices->value)
            && $user->canViewFinancialsFor($invoice->project)
            && $invoice->status->canTransitionTo(InvoiceStatus::Rejected);
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::ManageInvoices->value)
            && $user->canViewFinancialsFor($invoice->project)
            && $invoice->status->canTransitionTo(InvoiceStatus::Sent);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::VoidInvoices->value)
            && $user->canViewFinancialsFor($invoice->project)
            && $invoice->status->canTransitionTo(InvoiceStatus::Void);
    }
}
