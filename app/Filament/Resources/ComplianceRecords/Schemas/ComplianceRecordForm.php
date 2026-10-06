<?php

namespace App\Filament\Resources\ComplianceRecords\Schemas;

use App\Models\Project;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ComplianceRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('compliance_requirement_id')->relationship('requirement', 'name')->searchable()->required(),
            Select::make('employee_id')->relationship('employee', 'employee_number')->searchable(),
            Select::make('vendor_id')->relationship('vendor', 'company_name')->searchable(),
            Select::make('project_id')
                ->options(fn (): array => Project::query()->visibleTo(auth()->user())->pluck('name', 'id')->all())
                ->searchable(),
            Select::make('document_id')->relationship('document', 'name')->searchable()->label('Evidence document'),
            DatePicker::make('issued_at'),
            DatePicker::make('expires_at'),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }
}
