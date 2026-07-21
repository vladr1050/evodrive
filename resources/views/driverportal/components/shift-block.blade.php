@props(['shift', 'hideStation' => false])
@php
    $isMine = (bool) ($shift['is_mine'] ?? false);
    $isMyShift = $isMine && (($shift['status'] ?? '') === 'booked');
    $cancellable = (bool) ($shift['cancellable'] ?? false);
    $editable = (bool) ($shift['editable'] ?? false);
    $extendable = (bool) ($shift['extendable'] ?? false);
    $stationLabel = $shift['station_address']
        ?: ($shift['station_short'] ?? $shift['station'] ?? '');
    $vehiclePlate = ! empty($shift['vehicle_reg_number'])
        ? (string) $shift['vehicle_reg_number']
        : null;
    $vehicleFallback = explode(' ', $shift['vehicle'] ?? '')[0] ?? $shift['vehicle'] ?? '-';
    $hideStation = (bool) $hideStation;
    $hasActions = $editable || $extendable || $cancellable;
@endphp
{{-- Layout: time · duration(+actions) · plate · address on one line --}}
<div
    class="shift-card relative w-full text-left px-2 py-1.5 rounded-lg border {{ $isMyShift ? 'bg-green-50 border-green-300' : 'bg-slate-100/80 border-slate-200' }}"
    data-shift-id="{{ $shift['id'] ?? '' }}"
    data-station-name="{{ $shift['station'] ?? '' }}"
    title="{{ $stationLabel }} · {{ $shift['vehicle'] ?? '' }}"
>
    <div class="text-[11px] font-bold {{ $isMyShift ? 'text-green-800' : 'text-slate-800' }} tabular-nums leading-tight whitespace-nowrap">
        {{ $shift['start'] }}<span class="{{ $isMyShift ? 'text-green-500' : 'text-slate-400' }} font-semibold">–</span>{{ $shift['end'] }}
    </div>
    <div class="mt-1 flex items-center justify-between gap-1 min-w-0">
        <div class="text-[10px] font-bold {{ $isMyShift ? 'text-green-600' : 'text-slate-500' }} tabular-nums leading-none">{{ $shift['duration'] }}h</div>
        @if($hasActions)
            <div class="flex items-center shrink-0 -mr-0.5">
                @if($editable || $extendable)
                    <button type="button" class="shifts-grid-edit-btn p-1 text-slate-400 hover:bg-brand-50 hover:text-brand-600 rounded transition-colors" data-testid="shift-edit-btn" data-shift-id="{{ $shift['id'] }}" data-edit-url="{{ route('driverportal.shifts.update', ['locale' => app()->getLocale(), 'shift' => $shift['id']]) }}" data-edit-date="{{ $shift['date_iso'] ?? '' }}" data-edit-start="{{ $shift['start'] ?? '' }}" data-edit-duration="{{ $shift['duration'] ?? 0 }}" data-extend-ongoing="{{ $extendable ? '1' : '0' }}" data-extension-durations='@json($shift['allowed_extension_durations'] ?? [])' data-next-booked='@json($shift['next_vehicle_booked_display'] ?? null)' title="{{ $extendable ? __('portal.extend_shift_title') : __('portal.edit_shift') }}">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </button>
                @endif
                @if($cancellable)
                    <button type="button" class="shifts-grid-cancel-btn p-1 text-slate-400 hover:bg-red-50 hover:text-red-600 rounded transition-colors" data-testid="shift-cancel-btn" data-shift-id="{{ $shift['id'] }}" data-cancel-url="{{ route('driverportal.shifts.cancel', ['locale' => app()->getLocale(), 'shift' => $shift['id']]) }}" title="{{ __('portal.cancel_shift') }}">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>
        @endif
    </div>
    <div class="mt-1 text-[10px] font-semibold {{ $isMyShift ? 'text-green-900' : 'text-slate-700' }} tabular-nums leading-tight whitespace-nowrap">
        {{ $vehiclePlate ?: $vehicleFallback }}
    </div>
    @unless($hideStation)
        <div class="mt-1 text-[9px] font-medium {{ $isMyShift ? 'text-green-700/80' : 'text-slate-500' }} leading-snug break-normal [overflow-wrap:anywhere]">{{ $stationLabel }}</div>
    @endunless
</div>
