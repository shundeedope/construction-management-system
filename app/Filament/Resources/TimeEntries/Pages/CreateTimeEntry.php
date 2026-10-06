<?php

namespace App\Filament\Resources\TimeEntries\Pages;

use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Models\Employee;
use App\Models\User;
use App\Services\TimeEntryApprovalService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTimeEntry extends CreateRecord
{
    protected static string $resource = TimeEntryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        /** @var Employee $employee */
        $employee = Employee::query()->findOrFail($data['employee_id']);

        /** @var array{project_id: int, work_date: string, regular_hours: float|int|numeric-string, overtime_hours: float|int|numeric-string, is_billable?: bool, description?: string|null} $attributes */
        $attributes = $data;

        return app(TimeEntryApprovalService::class)->create(
            $employee,
            $attributes,
            $actor,
        );
    }
}
