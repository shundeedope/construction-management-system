<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OverdueInvoicesReport extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Overdue client invoices';

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

                return Invoice::query()
                    ->with('project:id,job_number,name')
                    ->whereIn('project_id', Project::query()->financiallyVisibleTo($user)->select('id'))
                    ->where('type', InvoiceType::Client)
                    ->whereIn('status', [InvoiceStatus::Approved, InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid])
                    ->whereDate('due_date', '<', today());
            })
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('project.job_number')->label('Project')->sortable(),
                TextColumn::make('due_date')->date()->sortable(),
                TextColumn::make('total')->money('USD', divideBy: 100)->sortable(),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('project')->relationship('project', 'name')->searchable(),
            ]);
    }
}
