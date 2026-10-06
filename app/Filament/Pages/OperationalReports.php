<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Filament\Widgets\LowestMarginProjectsReport;
use App\Filament\Widgets\OverdueInvoicesReport;
use App\Filament\Widgets\PendingApprovalsReport;
use App\Filament\Widgets\ProjectsByStatusReport;
use App\Filament\Widgets\UpcomingComplianceReport;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class OperationalReports extends Page
{
    protected string $view = 'filament.pages.operational-reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Reports';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ViewAnyProject->value) === true;
    }

    /** @return array<class-string> */
    protected function getHeaderWidgets(): array
    {
        return [
            OverdueInvoicesReport::class,
            LowestMarginProjectsReport::class,
            PendingApprovalsReport::class,
            UpcomingComplianceReport::class,
            ProjectsByStatusReport::class,
        ];
    }
}
