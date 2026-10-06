<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinancialOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ViewFinancials->value) === true;
    }

    protected function getStats(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $metrics = app(DashboardMetricsService::class)->financial($user);

        return [
            Stat::make('Active projects', (string) $metrics['active_projects']),
            Stat::make('Revised value', $metrics['revised_value']->format()),
            Stat::make('Receivables', $metrics['receivables']->format()),
            Stat::make('Payables', $metrics['payables']->format()),
            Stat::make('Gross margin', $metrics['margin']->format()),
        ];
    }
}
