<?php

namespace App\Filament\Resources\TimeEntries;

use App\Filament\Resources\TimeEntries\Pages\CreateTimeEntry;
use App\Filament\Resources\TimeEntries\Pages\EditTimeEntry;
use App\Filament\Resources\TimeEntries\Pages\ListTimeEntries;
use App\Filament\Resources\TimeEntries\Schemas\TimeEntryForm;
use App\Filament\Resources\TimeEntries\Tables\TimeEntriesTable;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TimeEntryResource extends Resource
{
    protected static ?string $model = TimeEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Labor';

    public static function form(Schema $schema): Schema
    {
        return TimeEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TimeEntriesTable::configure($table);
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
            'index' => ListTimeEntries::route('/'),
            'create' => CreateTimeEntry::route('/create'),
            'edit' => EditTimeEntry::route('/{record}/edit'),
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
        /** @var Builder<TimeEntry> $query */
        $query = parent::getEloquentQuery()->with(['employee.user:id,name', 'project:id,name', 'approver:id,name']);
        $user = auth()->user();

        if (! $user instanceof User) {
            /** @phpstan-ignore-next-line return.type */
            return $query->whereKey([]);
        }

        if ($user->can('view_all_time_entries')) {
            /** @phpstan-ignore-next-line return.type */
            return $query->whereHas('project', function (Builder $projects) use ($user): void {
                /** @var Builder<Project> $projects */
                $projects->visibleTo($user);
            });
        }

        /** @phpstan-ignore-next-line return.type */
        return $query->whereHas('employee', fn (Builder $employees) => $employees->where('user_id', $user->getKey()));
    }
}
