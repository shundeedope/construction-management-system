<?php

namespace App\Filament\Widgets;

use App\Enums\ChangeOrderStatus;
use App\Enums\Permission;
use App\Filament\Resources\ChangeOrders\Actions\ChangeOrderWorkflowActions;
use App\Models\ChangeOrder;
use App\Models\Project;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ApprovalsInbox extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Change orders awaiting your approval';

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ApproveChangeOrders->value) === true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                /** @var User $user */
                $user = auth()->user();

                return ChangeOrder::query()
                    ->with(['project:id,job_number,name', 'contract:id,number,type'])
                    ->withSum('lineItems', 'amount')
                    ->whereIn('project_id', Project::query()->financiallyVisibleTo($user)->select('id'))
                    ->where('status', ChangeOrderStatus::Submitted)
                    ->orderBy('submitted_at');
            })
            ->columns([
                TextColumn::make('number')->searchable()->sortable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('project.job_number')->label('Project'),
                TextColumn::make('line_items_sum_amount')->label('Value')->money('USD', divideBy: 100),
                TextColumn::make('submitted_at')->since()->sortable(),
            ])
            ->recordActions([
                ChangeOrderWorkflowActions::approve(),
                ChangeOrderWorkflowActions::reject(),
            ])
            ->paginated([5, 10])
            ->emptyStateHeading('Nothing is waiting on you')
            ->emptyStateDescription('Submitted change orders will appear here for a decision.');
    }
}
