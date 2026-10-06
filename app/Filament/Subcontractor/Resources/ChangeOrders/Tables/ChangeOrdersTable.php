<?php

namespace App\Filament\Subcontractor\Resources\ChangeOrders\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChangeOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('number')->searchable()->sortable(),
            TextColumn::make('title')->searchable()->sortable(),
            TextColumn::make('project.job_number')->label('Project')->sortable(),
            TextColumn::make('contract.number')->label('Contract')->sortable(),
            TextColumn::make('approved_at')->label('Approved')->date()->sortable(),
        ]);
    }
}
