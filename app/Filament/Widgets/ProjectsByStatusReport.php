<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ProjectsByStatusReport extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Projects by status';

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ViewAnyProject->value) === true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                /** @var User $user */
                $user = auth()->user();

                return Project::query()->visibleTo($user)->with('client:id,company_name');
            })
            ->columns([
                TextColumn::make('job_number')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('client.company_name')->label('Client')->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('expected_completion_date')->date()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ProjectStatus::class),
            ]);
    }
}
