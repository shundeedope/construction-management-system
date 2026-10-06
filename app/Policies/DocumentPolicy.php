<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->vendor_id !== null || $user->can(Permission::ViewDocuments->value);
    }

    /**
     * A document inherits its project's access rules, and a financial document additionally
     * requires financial access — a supervisor without the flag cannot download a contract.
     */
    public function view(User $user, Document $document): bool
    {
        if ($user->vendor_id !== null) {
            return $document->documentable instanceof Contract && $user->ownsVendorContract($document->documentable)
                || $document->documentable instanceof Invoice && $user->ownsVendorInvoice($document->documentable);
        }

        if (! $user->can(Permission::ViewDocuments->value) || ! $user->hasProjectAccess($document->project)) {
            return false;
        }

        return ! $document->category->isFinancial()
            || $user->canViewFinancialsFor($document->project);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->vendor_id !== null || $user->can(Permission::ManageDocuments->value);
    }

    public function update(User $user, Document $document): bool
    {
        if ($user->vendor_id !== null) {
            return $this->view($user, $document);
        }

        return $this->view($user, $document)
            && $user->canManageDocumentsFor($document->project);
    }

    public function delete(User $user, Document $document): bool
    {
        if ($user->vendor_id !== null) {
            return $this->view($user, $document);
        }

        return $user->can(Permission::DeleteDocuments->value)
            && $this->update($user, $document);
    }
}
