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
                    <label class="block text-sm font-medium mb-1">Status</label>
                    <select wire:model.live="statusMode" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <option value="both">Completed + Booked</option>
                        <option value="completed">Completed only</option>
                        <option value="booked">Booked only</option>
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Vehicles</label>
                    <select wire:model.live="vehicleIds" multiple class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" size="4">
                        @foreach($vehiclesSelect ?? [] as $v)
                            <option value="{{ $v->id }}">{{ $v->registration_number ?? '' }} {{ $v->brand }} {{ $v->model }}</option>
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
                    <p class="text-xs text-gray-500 mt-1">Leave empty for all</p>
                </div>
            </div>
        </x-filament::section>

        @if(!empty($rows))
            {{-- KPIs --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <x-filament::section class="p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Avg hours/vehicle/day</p>
                    <p class="text-2xl font-bold">{{ $kpis->avg_hours ?? 0 }}</p>
                </x-filament::section>
                <x-filament::section class="p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fleet utilization %</p>
                    <p class="text-2xl font-bold">{{ $kpis->fleet_rate ?? 0 }}%</p>
                </x-filament::section>
                <x-filament::section class="p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Peak day (hours)</p>
                    <p class="text-2xl font-bold">{{ $kpis->peak_hours ?? 0 }}</p>
                </x-filament::section>
                <x-filament::section class="p-4">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Idle (&lt;1h)</p>
                    <p class="text-2xl font-bold">{{ $kpis->idle_vehicles ?? 0 }}</p>
                </x-filament::section>
            </div>

            {{-- Heatmap --}}
            <x-filament::section>
                <x-slot name="heading">Heatmap (vehicles × days)</x-slot>
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">0–1h gray, 1–4h green, 4–8h yellow, 8–12h orange, 12–24h red</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr>
                                <th class="sticky left-0 z-10 bg-white dark:bg-gray-900 border-b border-r p-2 text-left min-w-[180px]">Vehicle</th>
                                @foreach($dateKeys ?? [] as $d)
                                    <th class="border-b border-r p-2 text-center whitespace-nowrap">{{ \Carbon\Carbon::parse($d)->format('d.m') }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($vehicles ?? [] as $v)
                                <tr>
                                    <td class="sticky left-0 z-10 bg-white dark:bg-gray-900 border-b border-r p-2 font-medium">{{ $v->name }}</td>
                                    @foreach($dateKeys ?? [] as $d)
                                        @php
                                            $cell = $heatmap[$v->id][$d] ?? null;
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
                                        @endphp
                                        <td
                                            class="border-b border-r p-1 text-center {{ $bg }} min-w-[44px] cursor-pointer hover:ring-2 hover:ring-primary-500"
                                            title="{{ $v->name }} | {{ $d }} | Booked: {{ $cell?->booked_hours ?? 0 }}h | Completed: {{ $cell?->completed_hours ?? 0 }}h | Total: {{ $hours }}h"
                                            wire:click="openIntervalModal({{ $v->id }}, '{{ $d }}')"
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

            {{-- Detail table --}}
            <x-filament::section>
                <x-slot name="heading">Daily detail</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left border-b">
                            <tr>
                                <th class="p-2">Date</th>
                                <th class="p-2">Vehicle</th>
                                <th class="p-2 w-48">Utilization</th>
                                <th class="p-2 text-right">Booked (h)</th>
                                <th class="p-2 text-right">Completed (h)</th>
                                <th class="p-2 text-right">Total (h)</th>
                                <th class="p-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $r)
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <td class="p-2">{{ \Carbon\Carbon::parse($r->date)->format('d.m.Y') }}</td>
                                    <td class="p-2">{{ $r->vehicle_name }}</td>
                                    <td class="p-2">
                                        <x-utilization-bar :minutes="$r->total_minutes" />
                                    </td>
                                    <td class="p-2 text-right">{{ number_format($r->booked_minutes / 60, 1) }}</td>
                                    <td class="p-2 text-right">{{ number_format($r->completed_minutes / 60, 1) }}</td>
                                    <td class="p-2 text-right font-medium">{{ $r->total_hours }}</td>
                                    <td class="p-2">
                                        <x-filament::button size="sm" wire:click="openIntervalModal({{ $r->vehicle_id }}, '{{ $r->date }}')">View intervals</x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <p class="text-gray-500 dark:text-gray-400">No data for the selected filters and date range.</p>
            </x-filament::section>
        @endif
    </div>

    {{-- Interval detail modal --}}
    @if($showIntervalModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/60 dark:bg-gray-900/80" wire:click="closeIntervalModal">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-2xl w-full max-h-[80vh] overflow-hidden" wire:click.stop>
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
                    <h3 class="text-lg font-semibold">Intervals — {{ $intervalModalVehicleName ?? '' }} ({{ $intervalModalDate ?? '' }})</h3>
                    <x-filament::button size="sm" color="gray" wire:click="closeIntervalModal">Close</x-filament::button>
                </div>
                <div class="p-4 overflow-y-auto max-h-[60vh]">
                    @if(!empty($intervalDetail))
                        <table class="w-full text-sm">
                            <thead class="text-left border-b">
                                <tr>
                                    <th class="p-2">Source</th>
                                    <th class="p-2">Status</th>
                                    <th class="p-2">Start</th>
                                    <th class="p-2">End</th>
                                    <th class="p-2 text-right">Minutes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($intervalDetail as $i)
                                    <tr class="border-b border-gray-200 dark:border-gray-700">
                                        <td class="p-2">{{ $i['source_type'] }} #{{ $i['source_id'] }}</td>
                                        <td class="p-2">{{ $i['status'] }}</td>
                                        <td class="p-2">{{ \Carbon\Carbon::parse($i['start'])->format('H:i d.m.Y') }}</td>
                                        <td class="p-2">{{ \Carbon\Carbon::parse($i['end'])->format('H:i d.m.Y') }}</td>
                                        <td class="p-2 text-right">{{ $i['minutes'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-gray-500 dark:text-gray-400">No intervals for this vehicle and date.</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
