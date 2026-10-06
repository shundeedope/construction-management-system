<?php

namespace App\Filament\Widgets;

use App\Enums\ComplianceStatus;
use App\Enums\Permission;
use App\Models\ComplianceRecord;
use App\Models\Project;
use App\Models\User;
use App\Services\CompanySettings;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingComplianceReport extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Upcoming compliance';

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ViewCompliance->value) === true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                /** @var User $user */
                $user = auth()->user();
                $warningDate = today()->addDays(app(CompanySettings::class)->complianceWarningDays());

                return ComplianceRecord::query()
                    ->with(['requirement:id,name', 'project:id,job_number,name'])
                    ->whereHas('requirement', fn (Builder $requirements) => $requirements->where('is_active', true))
                    ->where(function (Builder $records) use ($warningDate): void {
                        $records->whereNull('expires_at')->orWhereDate('expires_at', '<=', $warningDate);
                    })
                    ->where(function (Builder $records) use ($user): void {
                        $records->whereNull('project_id')->orWhereIn('project_id', Project::query()->visibleTo($user)->select('id'));
                    });
            })
            ->columns([
                TextColumn::make('requirement.name')->label('Requirement')->searchable(),
                TextColumn::make('project.job_number')->label('Project')->sortable(),
                TextColumn::make('expires_at')->date()->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ComplianceStatus::class),
            ]);
    }
}
