<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
        Hours per day each vehicle was in a shift (Booked/Completed) out of 24h. Shifts spanning midnight are split: e.g. 20:00–02:00 gives 4h to the first day and 2h to the next.
    </p>

    @if (empty($rows))
        <x-filament::section>
            <p class="text-gray-500 dark:text-gray-400">No shift data yet.</p>
        </x-filament::section>
    @else
        <x-filament::section>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-4 py-3">Date</th>
                            <th scope="col" class="px-4 py-3">Vehicle</th>
                            <th scope="col" class="px-4 py-3 text-right">Hours (of 24)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-2">{{ \Carbon\Carbon::parse($row['date'])->format('d.m.Y') }}</td>
                                <td class="px-4 py-2">{{ $row['vehicle_label'] }}</td>
                                <td class="px-4 py-2 text-right font-medium">{{ number_format($row['hours'], 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
