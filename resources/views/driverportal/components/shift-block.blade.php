@props(['shift'])
@php
    $isMine = (bool) ($shift['is_mine'] ?? false);
    $isMyShift = $isMine && (($shift['status'] ?? '') === 'booked');
    $cancellable = (bool) ($shift['cancellable'] ?? false);
    $editable = (bool) ($shift['editable'] ?? false);
    $extendable = (bool) ($shift['extendable'] ?? false);
@endphp
<div class="shift-card relative p-3 rounded-2xl border transition-all hover:scale-[1.02] {{ $isMyShift ? 'bg-green-50 border-green-200 shadow-sm' : 'bg-slate-200/50 border-slate-300/50 opacity-80' }}" data-shift-id="{{ $shift['id'] ?? '' }}" data-station-name="{{ $shift['station'] ?? '' }}">
    <div class="flex justify-between items-start mb-2">
        <div class="flex flex-col">
            <span class="text-base font-bold text-slate-900 leading-none">{{ $shift['start'] }}</span>
            <span class="text-[10px] font-bold text-slate-400 mt-1">{{ $shift['end'] }}</span>
        </div>
        <div class="flex items-center gap-2">
            @if($editable || $extendable)
                <button type="button" class="shifts-grid-edit-btn p-1.5 text-slate-400 hover:bg-brand-50 hover:text-brand-600 rounded-lg transition-all" data-testid="shift-edit-btn" data-shift-id="{{ $shift['id'] }}" data-edit-url="{{ route('driverportal.shifts.update', ['locale' => app()->getLocale(), 'shift' => $shift['id']]) }}" data-edit-date="{{ $shift['date_iso'] ?? '' }}" data-edit-start="{{ $shift['start'] ?? '' }}" data-edit-duration="{{ $shift['duration'] ?? 0 }}" data-extend-ongoing="{{ $extendable ? '1' : '0' }}" data-extension-durations='@json($shift['allowed_extension_durations'] ?? [])' data-next-booked='@json($shift['next_vehicle_booked_display'] ?? null)' title="{{ $extendable ? __('portal.extend_shift_title') : __('portal.edit_shift') }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
            @endif
            @if($cancellable)
                <button type="button" class="shifts-grid-cancel-btn p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 rounded-lg transition-all" data-testid="shift-cancel-btn" data-shift-id="{{ $shift['id'] }}" data-cancel-url="{{ route('driverportal.shifts.cancel', ['locale' => app()->getLocale(), 'shift' => $shift['id']]) }}" title="{{ __('portal.cancel_shift') }}">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            @endif
            <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full whitespace-nowrap {{ $isMyShift ? 'bg-green-100 text-green-700' : 'bg-slate-300 text-slate-600' }}">
                {{ $isMyShift ? __('portal.my_shift') : __('portal.reserved') }}
            </span>
        </div>
    </div>
    <div class="space-y-1.5">
        <div class="flex items-center gap-1.5 text-[10px] text-slate-500 font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span class="truncate">{{ $shift['duration'] }}h</span>
        </div>
        <div class="flex items-start gap-1.5 text-[10px] text-slate-500 font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0 mt-0.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <div class="min-w-0 flex-1 break-words">
                <span class="block">{{ $shift['station_short'] ?? $shift['station'] }}</span>
            </div>
        </div>
        <div class="flex items-center gap-1.5 text-[10px] text-slate-500 font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-1.1 0-2 .9-2 2v9c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>
            <span class="truncate">
                @if(!empty($shift['vehicle_reg_number']))
                    Tesla — {{ $shift['vehicle_reg_number'] }}
                @else
                    {{ explode(' ', $shift['vehicle'] ?? '')[0] ?? $shift['vehicle'] ?? '-' }}
                @endif
            </span>
        </div>
    </div>
</div>
