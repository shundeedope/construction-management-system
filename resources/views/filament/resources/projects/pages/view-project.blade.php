@php
    $billedPercent = $financialSummary['revised_owner_value']->cents > 0
        ? min(100, (int) round($financialSummary['client_invoiced']->cents * 100 / $financialSummary['revised_owner_value']->cents))
        : 0;
    $paidPercent = $financialSummary['revised_commitments']->cents > 0
        ? min(100, (int) round($financialSummary['paid']->cents * 100 / $financialSummary['revised_commitments']->cents))
        : 0;
    $marginHealthy = $financialSummary['gross_margin']->cents >= 0;
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    <x-filament::section class="lg:col-span-2" heading="Overview">
        <dl class="grid gap-4 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Client</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $record->client->company_name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Project manager</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $record->projectManager?->name ?? 'Unassigned' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Status</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $record->status->getLabel() }}</dd>
            </div>
            <div>
                <dt class="text-sm text-gray-500 dark:text-gray-400">Expected completion</dt>
                <dd class="font-medium text-gray-950 dark:text-white">{{ $record->expected_completion_date?->toFormattedDateString() ?? 'Not scheduled' }}</dd>
            </div>
        </dl>
    </x-filament::section>

    <x-filament::section heading="Attention">
        <ul class="space-y-2 text-sm">
            @if ($record->expected_completion_date?->isPast() && $record->actual_completion_date === null)
                <li class="text-warning-600 dark:text-warning-400">Past its expected completion date.</li>
            @endif
            @if ($pendingChangeOrders > 0)
                <li class="text-warning-600 dark:text-warning-400">{{ $pendingChangeOrders }} {{ str('change order')->plural($pendingChangeOrders) }} awaiting approval.</li>
            @endif
            @if (! ($record->expected_completion_date?->isPast() && $record->actual_completion_date === null) && $pendingChangeOrders === 0)
                <li class="text-gray-500 dark:text-gray-400">No immediate attention items.</li>
            @endif
        </ul>
    </x-filament::section>
</div>

@can('viewFinancials', $record)
    <div class="grid gap-6 lg:grid-cols-3">
        <x-filament::section class="lg:col-span-2" heading="Financial health">
            <div class="space-y-5">
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Billed to client</span>
                        <span class="font-medium text-gray-950 dark:text-white">{{ $financialSummary['client_invoiced']->format() }} of {{ $financialSummary['revised_owner_value']->format() }} ({{ $billedPercent }}%)</span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-full rounded-full bg-primary-500" style="width: {{ $billedPercent }}%"></div>
                    </div>
                </div>
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Paid to vendors</span>
                        <span class="font-medium text-gray-950 dark:text-white">{{ $financialSummary['paid']->format() }} of {{ $financialSummary['revised_commitments']->format() }} ({{ $paidPercent }}%)</span>
                    </div>
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-full rounded-full bg-info-500" style="width: {{ $paidPercent }}%"></div>
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Gross margin">
            <p class="text-3xl font-semibold {{ $marginHealthy ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400' }}">
                {{ $financialSummary['gross_margin']->format() }}
            </p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ number_format($financialSummary['gross_margin_percentage'], 2) }}% of revised owner value
            </p>
        </x-filament::section>
    </div>

    <x-filament::section heading="Financial summary">
        <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                'Original owner value' => $financialSummary['original_owner_value']->format(),
                'Approved owner changes' => $financialSummary['approved_owner_changes']->format(),
                'Revised owner value' => $financialSummary['revised_owner_value']->format(),
                'Remaining to bill' => $financialSummary['remaining_to_bill']->format(),
                'Original commitments' => $financialSummary['original_commitments']->format(),
                'Approved subcontract changes' => $financialSummary['approved_subcontract_changes']->format(),
                'Revised commitments' => $financialSummary['revised_commitments']->format(),
                'Remaining commitment' => $financialSummary['remaining_commitment']->format(),
                'Client invoiced' => $financialSummary['client_invoiced']->format(),
                'Received' => $financialSummary['received']->format(),
                'Receivable' => $financialSummary['receivable']->format(),
                'Pending change orders' => $pendingChangeOrders,
                'Subcontractor invoiced' => $financialSummary['subcontractor_invoiced']->format(),
                'Paid' => $financialSummary['paid']->format(),
                'Payable' => $financialSummary['payable']->format(),
                'Margin percentage' => number_format($financialSummary['gross_margin_percentage'], 2).'%',
            ] as $metric => $value)
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $metric }}</dt>
                    <dd class="text-lg font-semibold text-gray-950 dark:text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-filament::section>

    <x-filament::section heading="Contracts">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <tr>
                        <th class="px-3 py-2 font-medium">Number</th>
                        <th class="px-3 py-2 font-medium">Title</th>
                        <th class="px-3 py-2 font-medium">Type</th>
                        <th class="px-3 py-2 font-medium">Status</th>
                        <th class="px-3 py-2 text-right font-medium">Original amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($contracts as $contract)
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">{{ $contract->number }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $contract->title }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $contract->type->getLabel() }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $contract->status->getLabel() }}</td>
                            <td class="px-3 py-2 text-right text-gray-950 dark:text-white">{{ \App\Support\Money::fromCents($contract->original_amount)->format() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-3 py-4 text-gray-500 dark:text-gray-400" colspan="5">No contracts have been recorded for this project.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
@endcan

<div class="grid gap-6 lg:grid-cols-2">
    <x-filament::section heading="Team">
        <ul class="space-y-3 text-sm">
            @forelse ($team as $member)
                <li class="flex items-center justify-between">
                    <span class="font-medium text-gray-950 dark:text-white">{{ $member->name }}</span>
                    <span class="text-gray-500 dark:text-gray-400">{{ $member->pivot->project_role?->getLabel() ?? 'Member' }}</span>
                </li>
            @empty
                <li class="text-gray-500 dark:text-gray-400">No users have been assigned yet.</li>
            @endforelse
        </ul>
    </x-filament::section>

    @can('view_all_time_entries')
        <x-filament::section heading="Labor">
            <dl class="grid gap-4 sm:grid-cols-3">
                <div><dt class="text-sm text-gray-500 dark:text-gray-400">Regular hours</dt><dd class="text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format((float) $laborSummary->regular_hours, 2) }}</dd></div>
                <div><dt class="text-sm text-gray-500 dark:text-gray-400">Overtime hours</dt><dd class="text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format((float) $laborSummary->overtime_hours, 2) }}</dd></div>
                <div><dt class="text-sm text-gray-500 dark:text-gray-400">Approved hours</dt><dd class="text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format((float) $laborSummary->approved_hours, 2) }}</dd></div>
            </dl>
        </x-filament::section>
    @endcan

    <x-filament::section class="lg:col-span-2" heading="Activity">
        <ul class="space-y-3 text-sm">
            @forelse ($activities as $activity)
                <li class="flex items-start justify-between gap-4">
                    <span class="text-gray-950 dark:text-white">
                        {{ str(class_basename($activity->subject_type))->headline() }}
                        <span class="text-gray-600 dark:text-gray-300">{{ str($activity->event)->replace('_', ' ') }}</span>
                        @if ($activity->causer !== null)
                            <span class="text-gray-500 dark:text-gray-400">by {{ $activity->causer->name }}</span>
                        @endif
                    </span>
                    <span class="whitespace-nowrap text-gray-500 dark:text-gray-400">{{ $activity->created_at->diffForHumans() }}</span>
                </li>
            @empty
                <li class="text-gray-500 dark:text-gray-400">No activity has been recorded for this project.</li>
            @endforelse
        </ul>
    </x-filament::section>
</div>
