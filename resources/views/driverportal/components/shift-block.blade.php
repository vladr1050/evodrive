@props(['shift', 'hideStation' => false])
@php
    $isMine = (bool) ($shift['is_mine'] ?? false);
    $isMyShift = $isMine && (($shift['status'] ?? '') === 'booked');
    $cancellable = (bool) ($shift['cancellable'] ?? false);
    $editable = (bool) ($shift['editable'] ?? false);
    $extendable = (bool) ($shift['extendable'] ?? false);
    $stationLabel = $shift['station_short'] ?? $shift['station'] ?? '';
    $vehicleLabel = ! empty($shift['vehicle_reg_number'])
        ? ($shift['vehicle_reg_number'] ?? '')
        : (explode(' ', $shift['vehicle'] ?? '')[0] ?? $shift['vehicle'] ?? '-');
    $hideStation = (bool) $hideStation;
@endphp
<div
    class="shift-card relative px-2 py-1.5 rounded-lg border {{ $isMyShift ? 'bg-green-50 border-green-200' : 'bg-slate-100 border-slate-200/80 opacity-90' }}"
    data-shift-id="{{ $shift['id'] ?? '' }}"
    data-station-name="{{ $shift['station'] ?? '' }}"
    title="{{ $stationLabel }} · {{ $shift['vehicle'] ?? '' }}"
>
    <div class="flex items-start justify-between gap-1 min-w-0">
        <div class="min-w-0">
            <div class="text-xs font-bold {{ $isMyShift ? 'text-green-900' : 'text-slate-800' }} tabular-nums leading-tight truncate">
                {{ $shift['start'] }}–{{ $shift['end'] }}
            </div>
            <div class="mt-0.5 text-[10px] font-medium text-slate-500 truncate">
                {{ $shift['duration'] }}h · {{ $vehicleLabel }}
            </div>
            @unless($hideStation)
                <div class="mt-0.5 text-[9px] font-medium text-slate-400 truncate">{{ $stationLabel }}</div>
            @endunless
        </div>
        <div class="flex flex-col items-end gap-0.5 shrink-0">
            <span class="text-[8px] font-bold uppercase tracking-wide px-1 py-0.5 rounded {{ $isMyShift ? 'bg-green-100 text-green-700' : 'bg-slate-200 text-slate-600' }}">
                {{ $isMyShift ? __('portal.my_shift') : __('portal.reserved') }}
            </span>
            @if($editable || $extendable || $cancellable)
                <div class="flex items-center">
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
    </div>
</div>
