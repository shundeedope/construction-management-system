<?php

namespace App\Filament\Resources\ComplianceRequirements;

use App\Enums\Permission;
use App\Filament\Resources\ComplianceRequirements\Pages\CreateComplianceRequirement;
use App\Filament\Resources\ComplianceRequirements\Pages\EditComplianceRequirement;
use App\Filament\Resources\ComplianceRequirements\Pages\ListComplianceRequirements;
use App\Filament\Resources\ComplianceRequirements\Schemas\ComplianceRequirementForm;
use App\Filament\Resources\ComplianceRequirements\Tables\ComplianceRequirementsTable;
use App\Models\ComplianceRequirement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ComplianceRequirementResource extends Resource
{
    protected static ?string $model = ComplianceRequirement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Compliance';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ViewCompliance->value) === true;
    }

    public static function form(Schema $schema): Schema
    {
        return ComplianceRequirementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComplianceRequirementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplianceRequirements::route('/'),
            'create' => CreateComplianceRequirement::route('/create'),
            'edit' => EditComplianceRequirement::route('/{record}/edit'),
        ];
    }
}
