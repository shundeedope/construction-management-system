<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Schema;

/**
 * Turns an edit page into a read-only view once the record can no longer be
 * updated (e.g. a workflow has locked it), so lifecycle header actions stay
 * reachable instead of the page returning a 403.
 */
trait HasReadOnlyLockedForm
{
    public function form(Schema $schema): Schema
    {
        return parent::form($schema)->disabled(fn (): bool => ! $this->canUpdateRecord());
    }

    /** @return array<Action|ActionGroup> */
    protected function getFormActions(): array
    {
        return $this->canUpdateRecord() ? parent::getFormActions() : [];
    }

    protected function canUpdateRecord(): bool
    {
        return auth()->user()?->can('update', $this->getRecord()) === true;
    }
}
