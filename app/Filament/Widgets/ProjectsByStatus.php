<?php

namespace App\Filament\Widgets;

use App\Enums\Permission;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class ProjectsByStatus extends ChartWidget
{
    protected ?string $heading = 'Projects By Status';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ViewAnyProject->value) === true;
    }

    protected function getData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $counts = Project::query()
            ->visibleTo($user)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'datasets' => [[
                'label' => 'Projects',
                'data' => array_map(fn (ProjectStatus $status): int => (int) $counts->get($status->value, 0), ProjectStatus::cases()),
                'backgroundColor' => '#f59e0b',
            ]],
            'labels' => array_map(fn (ProjectStatus $status): string => $status->getLabel(), ProjectStatus::cases()),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
