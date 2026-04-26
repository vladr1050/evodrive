<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Filters --}}
        <x-filament::section>
            <x-slot name="heading">Filters</x-slot>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Date from</label>
                    <input type="date" wire:model.live="dateFrom" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Date to</label>
                    <input type="date" wire:model.live="dateTo" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Quick range</label>
                    <div class="flex flex-wrap gap-1">
                        <x-filament::button size="sm" wire:click="setQuickRange('last7')">Last 7</x-filament::button>
                        <x-filament::button size="sm" wire:click="setQuickRange('last14')">Last 14</x-filament::button>
                        <x-filament::button size="sm" wire:click="setQuickRange('last30')">Last 30</x-filament::button>
                        <x-filament::button size="sm" wire:click="setQuickRange('next7')">Next 7</x-filament::button>
                        <x-filament::button size="sm" wire:click="setQuickRange('next14')">Next 14</x-filament::button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Shift data (in range)</label>
                    <select wire:model.live="statusMode" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <option value="both">Completed + Booked</option>
                        <option value="completed">Completed only</option>
                        <option value="booked">Booked only</option>
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium mb-1">Driver account status (who is listed)</label>
                <select wire:model.live="driverStatuses" multiple class="w-full max-w-xl rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" size="3">
                    @foreach($driverStatusCases ?? [] as $st)
                        <option value="{{ $st->value }}">{{ \Illuminate\Support\Str::headline($st->value) }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Leave empty for all. Applies to roster, heatmap, totals, and export.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Drivers</label>
                    <label class="inline-flex items-center gap-2 mb-2 text-xs text-gray-600 dark:text-gray-300">
                        <input type="checkbox" wire:model.live="selectAllDrivers" class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <span>All drivers</span>
                    </label>
                    <select wire:model.live="driverIds" multiple class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" size="4">
                        @foreach($driversSelect ?? [] as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Leave empty for all</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Stations</label>
                    <select wire:model.live="stationIds" multiple class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" size="4">
                        @foreach($stationsSelect ?? [] as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Vehicles</label>
                    <select wire:model.live="vehicleIds" multiple class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" size="4">
                        @foreach($vehiclesSelect ?? [] as $v)
                            <option value="{{ $v->id }}">{{ $v->registration_number ?? '' }} {{ $v->brand }} {{ $v->model }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </x-filament::section>

        @if(isset($fleetInsights) && $fleetInsights->rows->isNotEmpty())
            <x-filament::section>
                <x-slot name="heading">Fleet overview — worked / planned / future / activity</x-slot>
                <p class="text-xs text-gray-600 dark:text-gray-400 mb-3 max-w-4xl">
                    All drivers in scope are listed (including 0 h in range). <strong>Median worked</strong> (completed hours in range): {{ $fleetInsights->median_worked_hours }} h —
                    compare each row to this line (±15% = “at median”). <strong>Future booked</strong>: next {{ $fleetInsights->future_horizon_days }} days from today.
                    <strong>Activity score</strong> (0–100): 45% worked vs median + 35% future booked vs median + 20% reliability (cancellations vs volume). Score is hidden for drivers with <strong>no completed shifts</strong> in history (filters apply), so new hires are not ranked until they complete work.
                    <strong>Table order:</strong> novices first (by name), then the rest by score highest first.
                </p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left border-b">
                            <tr>
                                <th class="p-2">Driver</th>
                                <th class="p-2 text-right">Worked (h)</th>
                                <th class="p-2 text-right">Booked in range (h)</th>
                                <th class="p-2 text-right">Future booked (h)</th>
                                <th class="p-2 text-right">Cancelled (h)</th>
                                <th class="p-2 text-right" title="Distinct calendar days in the selected date range with any booked or completed time. Denominator = number of days in that range.">Days w/ shift</th>
                                <th class="p-2 text-right">vs med.</th>
                                <th class="p-2">Band</th>
                                <th class="p-2 text-right">Score</th>
                                <th class="p-2">Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fleetInsights->rows as $fr)
                                @php
                                    $bandClass = match ($fr->median_band) {
                                        'below_median' => 'text-red-600 dark:text-red-400',
                                        'above_median' => 'text-green-600 dark:text-green-400',
                                        default => 'text-gray-600 dark:text-gray-400',
                                    };
                                @endphp
                                <tr class="border-b border-gray-200 dark:border-gray-700 {{ $fr->is_novice ? 'opacity-90' : '' }}">
                                    <td class="p-2 font-medium">{{ $fr->driver_name }}</td>
                                    <td class="p-2 text-right">{{ number_format($fr->worked_hours, 1) }}</td>
                                    <td class="p-2 text-right">{{ number_format($fr->booked_hours, 1) }}</td>
                                    <td class="p-2 text-right">{{ number_format($fr->future_booked_hours, 1) }}</td>
                                    <td class="p-2 text-right">{{ number_format($fr->cancelled_hours, 1) }}</td>
                                    <td class="p-2 text-right">{{ $fr->shift_days_in_range }} / {{ $fleetInsights->day_count }}</td>
                                    <td class="p-2 text-right {{ $fr->vs_median_worked < 0 ? 'text-red-600 dark:text-red-400' : ($fr->vs_median_worked > 0 ? 'text-green-600 dark:text-green-400' : '') }}">
                                        {{ $fr->vs_median_worked >= 0 ? '+' : '' }}{{ number_format($fr->vs_median_worked, 1) }}
                                    </td>
                                    <td class="p-2 {{ $bandClass }}">{{ str_replace('_', ' ', $fr->median_band) }}</td>
                                    <td class="p-2 text-right font-semibold" title="{{ $fr->is_novice ? '' : 'Worked '.$fr->score_worked_component.' · Forward '.$fr->score_forward_component.' · Reliability '.$fr->score_reliability_component }}">
                                        @if($fr->is_novice)
                                            <span class="text-gray-400">—</span>
                                        @else
                                            {{ $fr->activity_score }}
                                        @endif
                                    </td>
                                    <td class="p-2 text-xs">
                                        @if($fr->is_novice)
                                            <span class="rounded bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5">Novice</span>
                                        @elseif($fr->has_completed_history && $fr->first_shift_at)
                                            Since {{ $fr->first_shift_at->timezone($statisticsTimezone ?? config('app.timezone'))->format('Y-m-d') }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @elseif(isset($fleetInsights))
            <x-filament::section>
                <p class="text-gray-500 dark:text-gray-400">No drivers to show.</p>
            </x-filament::section>
        @endif

        @if(isset($totalsSummary))
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <x-filament::section class="p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Hours by selected status filter</p>
                    <p class="text-2xl font-bold">{{ $totalsSummary->selected_hours ?? 0 }}</p>
                </x-filament::section>
                <x-filament::section class="p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Worked (Completed)</p>
                    <p class="text-2xl font-bold">{{ $totalsSummary->worked_hours ?? 0 }}</p>
                </x-filament::section>
                <x-filament::section class="p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Booked</p>
                    <p class="text-2xl font-bold">{{ $totalsSummary->booked_hours ?? 0 }}</p>
                </x-filament::section>
                <x-filament::section class="p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cancelled</p>
                    <p class="text-2xl font-bold">{{ $totalsSummary->cancelled_hours ?? 0 }}</p>
                </x-filament::section>
            </div>
        @endif

        @if(!empty($driverTotals))
            <x-filament::section>
                <x-slot name="heading">Drivers by total hours in range (desc)</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left border-b">
                            <tr>
                                <th class="p-2">Driver</th>
                                <th class="p-2 text-right">Total (h)</th>
                                <th class="p-2 text-right">Worked (h)</th>
                                <th class="p-2 text-right">Booked (h)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($driverTotals as $dt)
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <td class="p-2">{{ $dt->driver_name }}</td>
                                    <td class="p-2 text-right font-semibold">{{ number_format($dt->total_hours, 1) }}</td>
                                    <td class="p-2 text-right">{{ number_format($dt->worked_hours, 1) }}</td>
                                    <td class="p-2 text-right">{{ number_format($dt->booked_hours, 1) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if(isset($kpis))
            {{-- KPIs --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <x-filament::section class="p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Planned hours</p>
                    <p class="text-2xl font-bold">{{ $kpis->planned_hours ?? 0 }}</p>
                </x-filament::section>
                <x-filament::section class="p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Worked hours</p>
                    <p class="text-2xl font-bold">{{ $kpis->worked_hours ?? 0 }}</p>
                </x-filament::section>
                <x-filament::section class="p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Completion rate</p>
                    <p class="text-2xl font-bold">{{ $kpis->completion_rate ?? 0 }}%</p>
                </x-filament::section>
                <x-filament::section class="p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active drivers</p>
                    <p class="text-2xl font-bold">{{ $kpis->active_drivers ?? 0 }}</p>
                </x-filament::section>
                <x-filament::section class="p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Avg h/driver/day</p>
                    <p class="text-2xl font-bold">{{ $kpis->avg_hours_per_driver_per_day ?? 0 }}</p>
                </x-filament::section>
            </div>
        @endif

        @if(!empty($drivers) && !empty($dateKeys))
            {{-- Heatmap --}}
            <x-filament::section>
                <x-slot name="heading">Heatmap (drivers × days)</x-slot>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">0–1h gray, 1–4h green, 4–8h yellow, 8–12h orange, 12–24h red</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 bg-white dark:bg-gray-900 border-b border-r p-2 text-left min-w-[180px]">Driver</th>
                                @foreach($dateKeys ?? [] as $d)
                                    <th class="border-b border-r p-2 text-center whitespace-nowrap">{{ \Carbon\Carbon::parse($d)->format('d.m') }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($drivers ?? [] as $dr)
                                <tr>
                                    <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 border-b border-r p-2 font-medium">{{ $dr->name }}</td>
                                    @foreach($dateKeys ?? [] as $d)
                                        @php
                                            $cell = $heatmap[$dr->id][$d] ?? null;
                                            $hours = $cell ? $cell->total_hours : 0;
                                            $bucket = match (true) {
                                                $hours <= 1 => 'gray',
                                                $hours <= 4 => 'green',
                                                $hours <= 8 => 'yellow',
                                                $hours <= 12 => 'orange',
                                                default => 'red',
                                            };
                                            $bg = match ($bucket) {
                                                'gray' => 'bg-gray-200 dark:bg-gray-600',
                                                'green' => 'bg-green-400 dark:bg-green-700',
                                                'yellow' => 'bg-yellow-400 dark:bg-yellow-600',
                                                'orange' => 'bg-orange-400 dark:bg-orange-600',
                                                'red' => 'bg-red-400 dark:bg-red-600',
                                                default => 'bg-gray-100',
                                            };
                                            $stationsTip = $cell && !empty($cell->stations) ? implode(', ', $cell->stations) : '—';
                                            $vehiclesTip = $cell && !empty($cell->vehicles) ? implode(', ', $cell->vehicles) : '—';
                                        @endphp
                                        <td
                                            class="border-b border-r p-1 text-center {{ $bg }} min-w-[44px] cursor-pointer hover:ring-2 hover:ring-primary-500"
                                            title="{{ $dr->name }} | {{ $d }} | Planned: {{ $cell?->planned_hours ?? 0 }}h | Worked: {{ $cell?->worked_hours ?? 0 }}h | Total: {{ $hours }}h | Stations: {{ $stationsTip }} | Vehicles: {{ $vehiclesTip }}"
                                            wire:click="openBreakdownModal({{ $dr->id }}, '{{ $d }}')"
                                        >
                                            {{ $hours > 0 ? number_format($hours, 1) : '—' }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            {{-- Station workload --}}
            @if(!empty($stationBreakdown))
                <x-filament::section>
                    <x-slot name="heading">Station workload</x-slot>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left border-b">
                                <tr>
                                    <th class="p-2">Station</th>
                                    <th class="p-2 text-right">Planned (h)</th>
                                    <th class="p-2 text-right">Worked (h)</th>
                                    <th class="p-2 text-right">Drivers</th>
                                    <th class="p-2 text-right">Vehicles used</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($stationBreakdown as $s)
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <td class="p-2">{{ $s->station }}</td>
                                        <td class="p-2 text-right">{{ $s->planned_hours }}</td>
                                        <td class="p-2 text-right">{{ $s->worked_hours }}</td>
                                        <td class="p-2 text-right">{{ $s->drivers_count }}</td>
                                        <td class="p-2 text-right">{{ $s->vehicles_used }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif

            {{-- Vehicle breakdown --}}
            @if(!empty($vehicleBreakdown))
                <x-filament::section>
                    <x-slot name="heading">Vehicle workload</x-slot>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-left border-b">
                                <tr>
                                    <th class="p-2">Vehicle</th>
                                    <th class="p-2 text-right">Planned (h)</th>
                                    <th class="p-2 text-right">Worked (h)</th>
                                    <th class="p-2 text-right">Drivers</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($vehicleBreakdown as $v)
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <td class="p-2">{{ $v->vehicle }}</td>
                                        <td class="p-2 text-right">{{ $v->planned_hours }}</td>
                                        <td class="p-2 text-right">{{ $v->worked_hours }}</td>
                                        <td class="p-2 text-right">{{ $v->drivers_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif
        @endif

        @if(isset($drivers) && $drivers->isEmpty())
            <x-filament::section>
                <p class="text-gray-500 dark:text-gray-400">No drivers in scope.</p>
            </x-filament::section>
        @endif
    </div>

    {{-- Breakdown modal --}}
    @if($showBreakdownModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 dark:bg-gray-900/80" wire:click="closeBreakdownModal">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-2xl w-full max-h-[80vh] overflow-hidden" wire:click.stop>
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Shifts — {{ $breakdownDriverName ?? '' }} ({{ $breakdownDate ?? '' }})</h3>
                    <x-filament::button size="sm" color="gray" wire:click="closeBreakdownModal">Close</x-filament::button>
                </div>
                <div class="p-4 overflow-y-auto max-h-[60vh]">
                    @if(!empty($breakdownDetail))
                        <table class="w-full text-sm">
                            <thead class="text-left border-b">
                                <tr>
                                    <th class="p-2">Station</th>
                                    <th class="p-2">Vehicle</th>
                                    <th class="p-2">Start</th>
                                    <th class="p-2">End</th>
                                    <th class="p-2 text-right">Duration</th>
                                    <th class="p-2">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($breakdownDetail as $i)
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <td class="p-2">{{ $i['station'] }}</td>
                                        <td class="p-2">{{ $i['vehicle'] }}</td>
                                        <td class="p-2">{{ $i['start'] }}</td>
                                        <td class="p-2">{{ $i['end'] }}</td>
                                        <td class="p-2 text-right">{{ (int)($i['duration_minutes'] / 60) }}h {{ $i['duration_minutes'] % 60 }}m</td>
                                        <td class="p-2">{{ $i['status'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-gray-500 dark:text-gray-400">No shifts for this driver and date.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
