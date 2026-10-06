<?php

namespace App\Services;

use App\Enums\ChangeOrderStatus;
use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Filament\Resources\ChangeOrders\ChangeOrderResource;
use App\Filament\Resources\ClientInvoices\ClientInvoiceResource;
use App\Filament\Resources\SubcontractorInvoices\SubcontractorInvoiceResource;
use App\Filament\Subcontractor\Resources\Invoices\InvoiceResource as PortalInvoiceResource;
use App\Models\ChangeOrder;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Sends panel (bell) notifications when a workflow record changes hands,
 * deep-linking each recipient to the record in the panel they use.
 */
class WorkflowNotificationService
{
    public function changeOrderTransitioned(ChangeOrder $changeOrder, User $actor, ChangeOrderStatus $to): void
    {
        $url = ChangeOrderResource::getUrl('edit', ['record' => $changeOrder], panel: 'admin');

        match ($to) {
            ChangeOrderStatus::Submitted => $this->send(
                $this->projectApprovers($changeOrder->project, Permission::ApproveChangeOrders, $actor),
                "Change order {$changeOrder->number} awaits approval",
                $changeOrder->title,
                $url,
                'warning',
            ),
            ChangeOrderStatus::Approved => $this->send(
                $this->creatorOf($changeOrder->creator, $actor),
                "Change order {$changeOrder->number} approved",
                $changeOrder->title,
                $url,
                'success',
            ),
            ChangeOrderStatus::Rejected => $this->send(
                $this->creatorOf($changeOrder->creator, $actor),
                "Change order {$changeOrder->number} rejected",
                $changeOrder->rejection_reason,
                $url,
                'danger',
            ),
            default => null,
        };
    }

    public function clientInvoiceTransitioned(Invoice $invoice, User $actor, InvoiceStatus $to): void
    {
        $url = ClientInvoiceResource::getUrl('edit', ['record' => $invoice], panel: 'admin');

        match ($to) {
            InvoiceStatus::Approved => $this->send(
                $this->creatorOf($invoice->creator, $actor),
                "Client invoice {$invoice->number} approved",
                null,
                $url,
                'success',
            ),
            InvoiceStatus::Void => $this->send(
                $this->creatorOf($invoice->creator, $actor),
                "Client invoice {$invoice->number} voided",
                $invoice->void_reason,
                $url,
                'danger',
            ),
            default => null,
        };
    }

    public function subcontractorInvoiceTransitioned(Invoice $invoice, User $actor, InvoiceStatus $to): void
    {
        match ($to) {
            InvoiceStatus::Submitted => $this->send(
                $this->projectApprovers($invoice->project, Permission::ApproveInvoices, $actor),
                "Subcontractor invoice {$invoice->number} awaits approval",
                $invoice->contract->title,
                SubcontractorInvoiceResource::getUrl('edit', ['record' => $invoice], panel: 'admin'),
                'warning',
            ),
            InvoiceStatus::Approved => $this->notifySubcontractorSide($invoice, $actor, "Subcontractor invoice {$invoice->number} approved", null, 'success'),
            InvoiceStatus::Rejected => $this->notifySubcontractorSide($invoice, $actor, "Subcontractor invoice {$invoice->number} rejected", $invoice->rejection_reason, 'danger'),
            default => null,
        };
    }

    /**
     * Vendor portal users see the record in their own panel; an admin creator sees it in the admin panel.
     */
    private function notifySubcontractorSide(Invoice $invoice, User $actor, string $title, ?string $body, string $status): void
    {
        $vendorUsers = User::query()
            ->whereNotNull('vendor_id')
            ->where('vendor_id', $invoice->contract->vendor_id)
            ->whereKeyNot($actor->getKey())
            ->get();

        $this->send($vendorUsers, $title, $body, PortalInvoiceResource::getUrl('edit', ['record' => $invoice], panel: 'subcontractor'), $status);

        $this->send(
            $this->creatorOf($invoice->creator, $actor)->reject(fn (User $user): bool => $user->vendor_id !== null),
            $title,
            $body,
            SubcontractorInvoiceResource::getUrl('edit', ['record' => $invoice], panel: 'admin'),
            $status,
        );
    }

    /** @return Collection<int, User> */
    private function projectApprovers(Project $project, Permission $permission, User $actor): Collection
    {
        return User::permission($permission->value)
            ->whereNull('vendor_id')
            ->whereKeyNot($actor->getKey())
            ->get()
            ->filter(fn (User $user): bool => $user->canViewFinancialsFor($project))
            ->values();
    }

    /** @return Collection<int, User> */
    private function creatorOf(?User $creator, User $actor): Collection
    {
        return collect([$creator])
            ->filter(fn (?User $user): bool => $user !== null && $user->isNot($actor));
    }

    /** @param  Collection<int, User>  $recipients */
    private function send(Collection $recipients, string $title, ?string $body, string $url, string $status): void
    {
        foreach ($recipients as $recipient) {
            Notification::make()
                ->title($title)
                ->body($body)
                ->status($status)
                ->actions([
                    Action::make('review')
                        ->button()
                        ->url($url)
                        ->markAsRead(),
                ])
                ->sendToDatabase($recipient);
        }
    }
}
