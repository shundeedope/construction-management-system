<?php

namespace App\Filament\Resources\ComplianceRecords;

use App\Enums\Permission;
use App\Filament\Resources\ComplianceRecords\Pages\CreateComplianceRecord;
use App\Filament\Resources\ComplianceRecords\Pages\EditComplianceRecord;
use App\Filament\Resources\ComplianceRecords\Pages\ListComplianceRecords;
use App\Filament\Resources\ComplianceRecords\Schemas\ComplianceRecordForm;
use App\Filament\Resources\ComplianceRecords\Tables\ComplianceRecordsTable;
use App\Models\ComplianceRecord;
use App\Models\Project;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ComplianceRecordResource extends Resource
{
    protected static ?string $model = ComplianceRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|\UnitEnum|null $navigationGroup = 'Compliance';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ViewCompliance->value) === true;
    }

    public static function form(Schema $schema): Schema
    {
        return ComplianceRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComplianceRecordsTable::configure($table);
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
            'index' => ListComplianceRecords::route('/'),
            'create' => CreateComplianceRecord::route('/create'),
            'edit' => EditComplianceRecord::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['requirement', 'employee', 'vendor', 'project', 'document', 'verifier']);
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereKey([]);
        }

        return $query->where(function (Builder $records) use ($user): void {
            $records->whereNull('project_id')
                ->orWhereHas('project', function (Builder $projects) use ($user): void {
                    /** @var Builder<Project> $projects */
                    $projects->visibleTo($user);
                });
        });
    }
}
