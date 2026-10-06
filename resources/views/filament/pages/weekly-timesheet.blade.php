<x-filament-panels::page>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            <x-filament::icon-button icon="heroicon-o-chevron-left" label="Previous week" wire:click="previousWeek" color="gray" />
            <input wire:model.live="weekOf" type="date" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white" />
            <x-filament::icon-button icon="heroicon-o-chevron-right" label="Next week" wire:click="nextWeek" color="gray" />
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-500 dark:text-gray-400">Week total</p>
            <p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($this->weekTotal(), 2) }} h</p>
        </div>
    </div>

    @if ($this->employee() === null)
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">Your user account is not linked to an employee record, so there is no timesheet to fill in.</p>
        </x-filament::section>
    @elseif ($this->gridProjects()->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">You are not assigned to any projects yet. Ask a project manager to add you to a project team.</p>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th class="px-3 py-2 font-medium">Project</th>
                            @foreach ($this->weekDays() as $day)
                                <th class="px-2 py-2 text-center font-medium {{ $day->isToday() ? 'text-primary-600 dark:text-primary-400' : '' }}">
                                    {{ $day->format('D') }}<br /><span class="text-xs">{{ $day->format('M j') }}</span>
                                </th>
                            @endforeach
                            <th class="px-3 py-2 text-right font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($this->gridProjects() as $project)
                            <tr wire:key="timesheet-row-{{ $project->id }}">
                                <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">
                                    {{ $project->job_number }} — {{ $project->name }}
                                </td>
                                @foreach ($this->weekDays() as $day)
                                    @php
                                        $date = $day->toDateString();
                                        $entry = $this->entryFor($project->id, $date);
                                    @endphp
                                    <td class="px-2 py-2 text-center" wire:key="timesheet-cell-{{ $project->id }}-{{ $date }}">
                                        @if ($this->isEditable($entry))
                                            <input
                                                type="number"
                                                min="0"
                                                step="0.25"
                                                wire:model.blur="hours.{{ $project->id }}.{{ $date }}"
                                                @class([
                                                    'w-16 rounded-lg border-gray-300 text-center text-sm dark:border-white/10 dark:bg-white/5 dark:text-white',
                                                    'border-danger-400 ring-1 ring-danger-400' => $entry?->status === App\Enums\TimeEntryStatus::Rejected,
                                                ])
                                            />
                                            @if ($entry?->status === App\Enums\TimeEntryStatus::Rejected)
                                                <p class="mt-1 text-xs text-danger-600 dark:text-danger-400" title="{{ $entry->rejection_reason }}">Rejected</p>
                                            @endif
                                        @else
                                            <span @class([
                                                'inline-flex items-center gap-1 font-medium',
                                                'text-warning-600 dark:text-warning-400' => $entry->status === App\Enums\TimeEntryStatus::Submitted,
                                                'text-success-600 dark:text-success-400' => $entry->status === App\Enums\TimeEntryStatus::Approved,
                                            ])>
                                                {{ number_format((float) $entry->regular_hours, 2) }}
                                                @if ($entry->status === App\Enums\TimeEntryStatus::Approved)
                                                    <x-filament::icon icon="heroicon-m-check-circle" class="h-4 w-4" />
                                                @endif
                                            </span>
                                            <p class="mt-1 text-xs text-gray-400">{{ $entry->status->getLabel() }}</p>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">
                                    {{ number_format($this->rowTotal($project->id), 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <td class="px-3 py-2 font-medium">Daily total</td>
                            @foreach ($this->weekDays() as $day)
                                <td class="px-2 py-2 text-center font-semibold text-gray-950 dark:text-white">
                                    {{ number_format($this->dayTotal($day->toDateString()), 2) }}
                                </td>
                            @endforeach
                            <td class="px-3 py-2 text-right font-semibold text-gray-950 dark:text-white">
                                {{ number_format($this->weekTotal(), 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Enter regular hours per project and day, then save a draft or submit the whole week for approval. Submitted and approved hours are locked.
            </p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
