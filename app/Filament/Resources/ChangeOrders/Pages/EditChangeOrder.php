<?php

namespace App\Filament\Resources\ChangeOrders\Pages;

use App\Enums\ChangeOrderStatus;
use App\Filament\Concerns\HasReadOnlyLockedForm;
use App\Filament\Resources\ChangeOrders\Actions\ChangeOrderWorkflowActions;
use App\Filament\Resources\ChangeOrders\ChangeOrderResource;
use App\Models\ChangeOrder;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditChangeOrder extends EditRecord
{
    use HasReadOnlyLockedForm;

    protected static string $resource = ChangeOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ChangeOrderWorkflowActions::submit()->record($this->getRecord()),
            ChangeOrderWorkflowActions::approve()->record($this->getRecord()),
            ChangeOrderWorkflowActions::reject()->record($this->getRecord()),
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        Gate::authorize('update', $this->record);

        /** @var ChangeOrder $record */
        $record = $this->record;
        abort_unless($record->status === ChangeOrderStatus::Draft, 403);
    }
}
