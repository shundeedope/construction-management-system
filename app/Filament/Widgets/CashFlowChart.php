<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentDirection;
use App\Enums\Permission;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

class CashFlowChart extends ChartWidget
{
    protected ?string $heading = 'Cash flow — last 6 months';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 5;

    public static function canView(): bool
    {
        return auth()->user()?->can(Permission::ViewFinancials->value) === true;
    }

    protected function getData(): array
    {
        /** @var User $user */
        $user = auth()->user();

        /** @var Collection<int, CarbonImmutable> $months */
        $months = collect(range(5, 0))->map(fn (int $offset): CarbonImmutable => CarbonImmutable::now()->startOfMonth()->subMonths($offset));

        $payments = Payment::query()
            ->whereIn('project_id', Project::query()->financiallyVisibleTo($user)->select('id'))
            ->whereDate('payment_date', '>=', $months->first())
            ->get(['direction', 'amount', 'payment_date'])
            ->groupBy(fn (Payment $payment): string => $payment->payment_date->format('Y-m'));

        $totalFor = fn (CarbonImmutable $month, PaymentDirection $direction): float => $payments
            ->get($month->format('Y-m'), collect())
            ->where('direction', $direction)
            ->sum('amount') / 100;

        return [
            'datasets' => [
                [
                    'label' => 'Received',
                    'data' => $months->map(fn (CarbonImmutable $month): float => $totalFor($month, PaymentDirection::Incoming))->all(),
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    'fill' => true,
                ],
                [
                    'label' => 'Paid out',
                    'data' => $months->map(fn (CarbonImmutable $month): float => $totalFor($month, PaymentDirection::Outgoing))->all(),
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.15)',
                    'fill' => true,
                ],
            ],
            'labels' => $months->map(fn (CarbonImmutable $month): string => $month->format('M Y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
