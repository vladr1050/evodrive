<x-filament-panels::page>
    <x-filament::section>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Use the button above to move <strong>future booked</strong> shifts from one fleet vehicle to another (e.g. repair swap).
            Each run is logged in <code class="text-xs">shift_vehicle_replacements</code> with a batch ID. Shifts keep
            <code class="text-xs">original_vehicle_id</code> for audit; utilization can attribute booked hours to the original
            vehicle when the option is enabled on the Utilization page or API.
        </p>
    </x-filament::section>
</x-filament-panels::page>
