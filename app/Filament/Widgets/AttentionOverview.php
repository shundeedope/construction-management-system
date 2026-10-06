<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Filament\Resources\ChangeOrders\ChangeOrderResource;
use App\Filament\Resources\ClientInvoices\ClientInvoiceResource;
use App\Filament\Resources\ComplianceRecords\ComplianceRecordResource;
use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Models\User;
use App\Services\DashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AttentionOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ViewAnyProject->value) === true;
    }

    protected function getStats(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $metrics = app(DashboardMetricsService::class)->attention($user);

        return [
            Stat::make('Overdue invoices', (string) $metrics['overdue_invoices'])
                ->color('danger')
                ->description('Review overdue billing')
                ->url(ClientInvoiceResource::getUrl().'?tab=overdue'),
            Stat::make('Pending changes', (string) $metrics['pending_changes'])
                ->color('warning')
                ->description('Change orders to decide')
                ->url(ChangeOrderResource::getUrl().'?tab=awaiting_approval'),
            Stat::make('Pending timesheets', (string) $metrics['pending_timesheets'])
                ->color('warning')
                ->description('Hours awaiting approval')
                ->url(TimeEntryResource::getUrl()),
            Stat::make('Expiring compliance', (string) $metrics['expiring_compliance'])
                ->color('danger')
                ->description('Certificates to renew')
                ->url(ComplianceRecordResource::getUrl()),
        ];
    }
}
