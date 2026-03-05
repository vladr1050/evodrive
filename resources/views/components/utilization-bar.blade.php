@props([
    'minutes' => 0,
    'maxMinutes' => 1440,
])
@php
    $pct = $maxMinutes > 0 ? min(100, round(($minutes / $maxMinutes) * 100, 1)) : 0;
    $hours = round($minutes / 60, 1);
    $bucket = match (true) {
        $hours <= 1 => 'gray',
        $hours <= 4 => 'green',
        $hours <= 8 => 'yellow',
        $hours <= 12 => 'orange',
        default => 'red',
    };
    $colors = [
        'gray' => 'bg-gray-300 dark:bg-gray-600',
        'green' => 'bg-green-500',
        'yellow' => 'bg-yellow-500',
        'orange' => 'bg-orange-500',
        'red' => 'bg-red-500',
    ];
@endphp
<div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-5 min-w-[80px]" title="{{ $hours }}h of 24">
    <div
        class="h-5 rounded-full {{ $colors[$bucket] }}"
        style="width: {{ $pct }}%"
    ></div>
</div>
