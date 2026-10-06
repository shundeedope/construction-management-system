<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectFinancialReportQuery;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LowestMarginProjectsReport extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Lowest-margin projects';

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ViewFinancials->value) === true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                /** @var User $user */
                $user = auth()->user();

                return app(ProjectFinancialReportQuery::class)->apply(Project::query()->financiallyVisibleTo($user)->with('client:id,company_name'));
            })
            ->columns([
                TextColumn::make('job_number')->label('Project')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('client.company_name')->label('Client')->sortable(),
                TextColumn::make('revised_owner_value')->label('Revised value')->money('USD', divideBy: 100)->sortable(),
                TextColumn::make('revised_commitments')->label('Commitments')->money('USD', divideBy: 100)->sortable(),
                TextColumn::make('gross_margin')->label('Gross margin')->money('USD', divideBy: 100)->sortable(),
            ])
            ->defaultSort('gross_margin');
    }
}
