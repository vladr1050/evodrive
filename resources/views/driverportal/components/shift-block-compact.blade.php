@props(['shift'])
@php
    $isMine = (bool) ($shift['is_mine'] ?? false);
    $isMyShift = $isMine && (($shift['status'] ?? '') === 'booked');
@endphp
{{-- Compact occupancy row for All shifts mode --}}
<div
    class="relative px-2.5 py-2 rounded-xl border text-left {{ $isMyShift ? 'bg-green-50 border-green-200' : 'bg-slate-100 border-slate-200' }}"
    data-shift-id="{{ $shift['id'] ?? '' }}"
    title="{{ ($shift['station_short'] ?? $shift['station'] ?? '') }} · {{ $shift['vehicle'] ?? '' }}"
>
    <div class="flex items-center justify-between gap-1">
        <span class="text-xs font-bold {{ $isMyShift ? 'text-green-800' : 'text-slate-700' }} leading-none">
            {{ $shift['start'] }}–{{ $shift['end'] }}
        </span>
        <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $isMyShift ? 'bg-green-500' : 'bg-slate-400' }}"></span>
    </div>
    <div class="mt-1 text-[9px] font-medium {{ $isMyShift ? 'text-green-600' : 'text-slate-400' }} truncate">
        {{ $shift['duration'] ?? '' }}h
        @if(!empty($shift['vehicle_reg_number']))
            · {{ $shift['vehicle_reg_number'] }}
        @elseif(!empty($shift['vehicle']))
            · {{ explode(' ', $shift['vehicle'])[0] }}
        @endif
    </div>
</div>
