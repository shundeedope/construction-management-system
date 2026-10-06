<?php

namespace App\Filament\Widgets;

use App\Enums\ChangeOrderStatus;
use App\Enums\Permission;
use App\Models\ChangeOrder;
use App\Models\Project;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingApprovalsReport extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Pending change-order approvals';

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

                return ChangeOrder::query()
                    ->with('project:id,job_number,name')
                    ->withSum('lineItems', 'amount')
                    ->whereIn('project_id', Project::query()->financiallyVisibleTo($user)->select('id'))
                    ->where('status', ChangeOrderStatus::Submitted);
            })
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('project.job_number')->label('Project')->sortable(),
                TextColumn::make('line_items_sum_amount')->label('Value')->money('USD', divideBy: 100),
                TextColumn::make('submitted_at')->dateTime()->sortable(),
            ]);
    }
}
