<?php

namespace App\Filament\Subcontractor\Resources\Projects\Tables;

use App\Enums\ProjectStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('job_number')->label('Project')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('projectManager.name')->label('Project manager'),
                TextColumn::make('expected_completion_date')->date()->label('Expected completion')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(ProjectStatus::class),
            ]);
    }
}
