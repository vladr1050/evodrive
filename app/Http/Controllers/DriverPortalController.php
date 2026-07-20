<?php

namespace App\Http\Controllers;

use App\Enums\DriverStatus;
use App\Enums\ShiftStatus;
use App\Exceptions\ShiftBookingException;
use App\Models\Shift;
use App\Models\ShiftPolicy;
use App\Models\Station;
use App\Services\ShiftAvailabilityService;
use App\Services\ShiftBookingService;
use App\Services\ShiftCancellationService;
use App\Services\ShiftCopyService;
use App\Services\ShiftEditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DriverPortalController extends Controller
{
    public function login(Request $request): View
    {
        return view('driverportal.login');
    }

    public function loginSubmit(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);
        $locale = $request->route('locale', 'en');
        if (Auth::guard('driver')->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $driver = Auth::guard('driver')->user();
            if ($driver->status !== DriverStatus::Active) {
                Auth::guard('driver')->logout();

                return redirect()->route('driverportal.login', ['locale' => $locale])
                    ->withInput($request->only('email'))
                    ->with('driverportal.error', __('portal.portal_access_denied'));
            }
            $request->session()->regenerate();

            return redirect()->intended(route('driverportal.dashboard', ['locale' => $locale]));
        }

        return back()->withInput($request->only('email'))->with('driverportal.error', __('auth.failed'));
    }

    public function dashboard(Request $request): View
    {
        $driver = Auth::guard('driver')->user();
        $locale = $request->route('locale', 'en');
        $tz = ShiftPolicy::active()?->timezone ?: 'Europe/Riga';
        $upcomingShifts = Shift::where('driver_id', $driver->id)
            ->where('status', \App\Enums\ShiftStatus::Booked)
            ->where('starts_at', '>=', now())
            ->with(['vehicle', 'station'])
            ->orderBy('starts_at')
            ->limit(5)
            ->get()
            ->map(function (Shift $s) use ($tz) {
                $startsAt = $s->starts_at->copy()->setTimezone($tz);
                $endsAt = $s->ends_at->copy()->setTimezone($tz);

                return [
                    'id' => (string) $s->id,
                    'vehicle' => $s->vehicle?->label ?? '-',
                    'station' => $s->station?->name ?? '-',
                    'station_address' => $s->station?->address ?: null,
                    'time' => $startsAt->format('H:i').' - '.$endsAt->format('H:i'),
                    'date' => $startsAt->format('Y-m-d'),
                    'duration' => (int) $s->durationHours().'h',
                    'status' => $s->status->value === 'booked' ? 'Confirmed' : $s->status->value,
                ];
            })
            ->all();
        $nextShift = Shift::where('driver_id', $driver->id)
            ->where('status', \App\Enums\ShiftStatus::Booked)
            ->where('starts_at', '>=', now())
            ->with(['vehicle', 'station'])
            ->orderBy('starts_at')
            ->first();
        $weekStart = now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
        $completedThisWeek = Shift::where('driver_id', $driver->id)
            ->where('status', \App\Enums\ShiftStatus::Completed)
            ->whereBetween('starts_at', [$weekStart, $weekEnd])
            ->get();
        $weeklyShiftsDone = $completedThisWeek->count();
        $weeklyTotalHours = $completedThisWeek->sum(fn ($x) => $x->durationHours());
        $weeklyShiftsTotal = count($upcomingShifts) + $weeklyShiftsDone;

        return view('driverportal.dashboard', [
            'driverName' => $driver->name,
            'upcomingShifts' => $upcomingShifts,
            'nextShiftCountdown' => $nextShift ? $nextShift->starts_at->diffForHumans(null, true) : '--:--:--',
            'nextShiftVehicle' => $nextShift?->vehicle?->label ?? '--',
            'nextShiftStation' => $nextShift?->station?->name ?? '--',
            'nextShiftStationAddress' => $nextShift?->station?->address ?: null,
            'weeklyTotalHours' => (int) $weeklyTotalHours,
            'weeklyShiftsDone' => (int) $weeklyShiftsDone,
            'weeklyShiftsTotal' => max(1, $weeklyShiftsTotal),
        ]);
    }

    public function shifts(Request $request): View
    {
        $driver = Auth::guard('driver')->user();
        $policyForTz = ShiftPolicy::active();
        $planningWindowDays = $policyForTz?->planning_window_days ?? 14;
        $tz = $policyForTz?->timezone ?: 'Europe/Riga';
        $nowInTz = now($tz);
        $todayStart = $nowInTz->copy()->startOfDay();
        $dayOfWeek = $todayStart->dayOfWeek;
        $diffToMonday = $dayOfWeek === 0 ? -6 : 1 - $dayOfWeek;
        $firstWeekMonday = $todayStart->copy()->addDays($diffToMonday);
        $lastDayInWindow = $todayStart->copy()->addDays($planningWindowDays - 1);
        $lastWeekMonday = $lastDayInWindow->copy()->subDays($lastDayInWindow->dayOfWeek === 0 ? 6 : $lastDayInWindow->dayOfWeek - 1)->startOfDay();
        $totalWeeks = (int) max(1, 1 + (int) ($firstWeekMonday->diffInDays($lastWeekMonday) / 7));
        $viewParam = $request->get('view', '0');
        $weekIndex = is_numeric($viewParam) ? (int) $viewParam : 0;
        $weekIndex = max(0, min($weekIndex, $totalWeeks - 1));
        $stationId = $request->get('station_id');
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $startOfWeek = $firstWeekMonday->copy()->addDays($weekIndex * 7)->startOfDay();
        $weekDates = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $startOfWeek->copy()->addDays($i);
            $weekDates[] = [
                'name' => $days[$i],
                'date' => $d->day,
                'month' => $d->translatedFormat('M'),
                'iso' => $d->format('Y-m-d'),
            ];
        }
        // Overlap with [Mon 00:00, next Mon 00:00) in policy TZ — not "fully inside" Sun 23:59,
        // so overnight shifts (e.g. Sun 23:00 → Mon 07:00) are not dropped from the week they start in.
        $weekRangeStart = $startOfWeek->copy()->startOfDay();
        $weekRangeEndExclusive = $startOfWeek->copy()->addDays(7)->startOfDay();
        $driverId = $driver->id;
        $editService = app(ShiftEditService::class);
        $mapShiftRow = function (Shift $s) use ($days, $tz, $nowInTz, $driverId, $editService): array {
            $startsAtInTz = $s->starts_at->copy()->setTimezone($tz);
            $endsAtInTz = $s->ends_at->copy()->setTimezone($tz);
            $isMine = (int) $s->driver_id === (int) $driverId;
            $cancellable = $isMine && $s->status === ShiftStatus::Booked && $startsAtInTz->gt($nowInTz);
            $editable = $isMine && $s->status === ShiftStatus::Booked && $startsAtInTz->gt($nowInTz) && $editService->canEditShift($s);
            $extensionDurations = ($isMine && $s->status === ShiftStatus::Booked && $startsAtInTz->lte($nowInTz) && $endsAtInTz->gt($nowInTz))
                ? $editService->allowedExtensionDurationsHours($s, $nowInTz)
                : [];
            $extendable = $isMine && $s->status === ShiftStatus::Booked && $extensionDurations !== [];
            $nextAfter = $editService->nextBookedShiftOnVehicleAfter($s);
            $nextVehicleBookedDisplay = $nextAfter
                ? $nextAfter->starts_at->copy()->setTimezone($tz)->format('Y-m-d H:i')
                : null;
            $vehicle = $s->vehicle;
            $vehicleLabel = $vehicle?->label ?? '-';
            $isTesla = $vehicle && (stripos((string) $vehicle->brand, 'Tesla') !== false || stripos((string) $vehicle->model, 'Tesla') !== false);
            $vehicleRegNumber = ($isTesla && ! empty($vehicle->registration_number)) ? $vehicle->registration_number : null;

            return [
                'id' => (string) $s->id,
                'day' => $days[$startsAtInTz->dayOfWeek === 0 ? 6 : $startsAtInTz->dayOfWeek - 1],
                'start' => $startsAtInTz->format('H:i'),
                'end' => $endsAtInTz->format('H:i'),
                'date_iso' => $startsAtInTz->format('Y-m-d'),
                'duration' => (int) $s->durationHours(),
                'vehicle' => $vehicleLabel,
                'vehicle_reg_number' => $vehicleRegNumber,
                'station' => $s->station?->name ?? '-',
                'station_short' => $s->station?->shortLabel() ?? '-',
                'station_address' => $s->station?->address ?: null,
                'status' => $s->status->value,
                'is_mine' => $isMine,
                'cancellable' => $cancellable,
                'editable' => $editable,
                'extendable' => $extendable,
                'allowed_extension_durations' => $extensionDurations,
                'next_vehicle_booked_display' => $nextVehicleBookedDisplay,
            ];
        };
        $stations = Station::where('is_active', true)->orderBy('name')->get(['id', 'name', 'address', 'provider', 'latitude', 'longitude', 'is_active']);
        $selectedStationId = null;
        $initialFilterStationId = null;
        $initialFilterStation = 'All';
        if ($stationId && $stations->contains('id', (int) $stationId)) {
            $selectedStationId = (int) $stationId;
            $initialFilterStationId = $selectedStationId;
            $initialFilterStation = $stations->firstWhere('id', $selectedStationId)->name;
            $driver->rememberRecentStation($selectedStationId);
        }

        $favoriteStationIds = $driver->favoriteStationIds();
        $favoriteActiveIds = $stations
            ->whereIn('id', $favoriteStationIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $shiftsBaseQuery = Shift::whereIn('status', [ShiftStatus::Booked, ShiftStatus::Completed])
            ->where('starts_at', '<', $weekRangeEndExclusive)
            ->where('ends_at', '>', $weekRangeStart)
            ->with(['vehicle', 'station'])
            ->orderBy('starts_at');
        if ($selectedStationId) {
            $shiftsBaseQuery->where('station_id', $selectedStationId);
        } elseif ($favoriteActiveIds !== []) {
            // Favorites scope for All: occupancy at favorite stations + always include my shifts.
            $shiftsBaseQuery->where(function ($q) use ($favoriteActiveIds, $driverId) {
                $q->whereIn('station_id', $favoriteActiveIds)
                    ->orWhere('driver_id', $driverId);
            });
        }
        $shiftsAll = $shiftsBaseQuery->clone()->get()
            ->map($mapShiftRow)
            ->sort(function (array $a, array $b): int {
                $aMine = ! empty($a['is_mine']) ? 0 : 1;
                $bMine = ! empty($b['is_mine']) ? 0 : 1;
                if ($aMine !== $bMine) {
                    return $aMine <=> $bMine;
                }

                return strcmp(($a['date_iso'] ?? '').' '.($a['start'] ?? ''), ($b['date_iso'] ?? '').' '.($b['start'] ?? ''));
            })
            ->values()
            ->all();
        // My shifts stay unfiltered by station so drivers always see their own schedule.
        $shiftsMine = Shift::whereIn('status', [ShiftStatus::Booked, ShiftStatus::Completed])
            ->where('starts_at', '<', $weekRangeEndExclusive)
            ->where('ends_at', '>', $weekRangeStart)
            ->where('driver_id', $driverId)
            ->with(['vehicle', 'station'])
            ->orderBy('starts_at')
            ->get()
            ->map($mapShiftRow)
            ->all();
        $policy = $policyForTz;
        $allowedDurations = $policy ? $policy->allowedDurations() : [4, 6, 8, 10, 12];
        $timeSlotMinutes = $policy->time_slot_minutes ?? 15;
        $minDate = $todayStart->format('Y-m-d');
        $maxDate = $todayStart->copy()->addDays(max(0, $planningWindowDays - 1))->format('Y-m-d');
        $minTimeToday = null;
        if ($timeSlotMinutes > 0) {
            $minutes = $nowInTz->hour * 60 + $nowInTz->minute;
            $nextSlot = (int) (ceil(($minutes + 1) / $timeSlotMinutes) * $timeSlotMinutes);
            if ($nextSlot < 24 * 60) {
                $minTimeToday = sprintf('%02d:%02d', (int) floor($nextSlot / 60), $nextSlot % 60);
            }
        }
        $dayNames = array_column($weekDates, 'name');
        $recentStationIds = $driver->recentStationIds();

        // Free slots: one selected station, otherwise all favorite stations (keeps the grid usable).
        $availableSlots = [];
        $requireStationForFree = true;
        if ($policy) {
            if ($selectedStationId) {
                $availableSlots = app(ShiftAvailabilityService::class)
                    ->getAvailableSlotsForWeek($startOfWeek, $dayNames, $selectedStationId);
                $requireStationForFree = false;
            } elseif ($favoriteActiveIds !== []) {
                $availableSlots = app(ShiftAvailabilityService::class)
                    ->getAvailableSlotsForWeek($startOfWeek, $dayNames, $favoriteActiveIds);
                $requireStationForFree = false;
            }
        }
        if (! empty($availableSlots)) {
            $availableSlots = array_values(array_filter($availableSlots, function ($slot) use ($minDate, $maxDate) {
                $date = $slot['date_iso'] ?? null;

                return $date && $date >= $minDate && $date <= $maxDate;
            }));
        }
        $shiftsBaseUrl = route('driverportal.shifts', ['locale' => $request->route('locale', app()->getLocale())]);
        $weekOptions = [];
        for ($w = 0; $w < $totalWeeks; $w++) {
            $mon = $firstWeekMonday->copy()->addDays($w * 7)->startOfDay();
            $sun = $mon->copy()->addDays(6);
            $label = $w === 0 ? __('portal.current_week') : ($w === 1 ? __('portal.next_week') : $mon->format('d').'–'.$sun->format('d').' '.$sun->translatedFormat('M'));
            $weekOptions[] = ['index' => $w, 'label' => $label];
        }
        $stationPayload = $stations->map(function (Station $s) use ($favoriteStationIds) {
            return [
                'id' => $s->id,
                'name' => $s->name,
                'short' => $s->shortLabel(),
                'address' => $s->address,
                'provider' => $s->resolvedProvider(),
                'latitude' => $s->latitude,
                'longitude' => $s->longitude,
                'is_active' => (bool) $s->is_active,
                'is_favorite' => in_array((int) $s->id, $favoriteStationIds, true),
            ];
        })->values()->all();
        $shiftsPageInit = [
            'initialFilterStation' => $initialFilterStation,
            'initialFilterStationId' => $initialFilterStationId,
            'stations' => $stationPayload,
            'favoriteStationIds' => $favoriteStationIds,
            'recentStationIds' => $recentStationIds,
            'shiftsBaseUrl' => $shiftsBaseUrl,
            'currentView' => (string) $weekIndex,
            'weekOptions' => $weekOptions,
            'toggleFavoriteUrl' => route('driverportal.stations.toggle-favorite', ['locale' => $request->route('locale', app()->getLocale())]),
            'requireStationForFree' => $requireStationForFree,
            'hasFavoriteStations' => $favoriteStationIds !== [],
            'weekDates' => $weekDates,
            'todayIso' => $todayStart->format('Y-m-d'),
            'lastStationStorageKey' => 'evodrive.driver.lastStationId',
        ];

        return view('driverportal.shifts', [
            'view' => (string) $weekIndex,
            'weekOptions' => $weekOptions,
            'weekDates' => $weekDates,
            'shiftsAll' => $shiftsAll,
            'shiftsMine' => $shiftsMine,
            'stations' => $stations,
            'selectedStationId' => $selectedStationId,
            'allowedDurations' => $allowedDurations,
            'timeSlotMinutes' => $timeSlotMinutes,
            'minDate' => $minDate,
            'maxDate' => $maxDate,
            'minTimeToday' => $minTimeToday,
            'availableSlots' => $availableSlots,
            'initialFilterStation' => $initialFilterStation,
            'shiftsBaseUrl' => $shiftsBaseUrl,
            'shiftsPageInit' => $shiftsPageInit,
        ]);
    }

    public function toggleFavoriteStation(Request $request): JsonResponse
    {
        $request->validate([
            'station_id' => 'required|integer|exists:stations,id',
        ]);
        $driver = Auth::guard('driver')->user();
        $stationId = (int) $request->input('station_id');
        $driver->toggleFavoriteStation($stationId);

        return response()->json([
            'ok' => true,
            'favorite_station_ids' => $driver->favoriteStationIds(),
            'is_favorite' => in_array($stationId, $driver->favoriteStationIds(), true),
        ]);
    }

    public function checkAvailability(Request $request): JsonResponse
    {
        $request->validate([
            'station_id' => 'required|integer|exists:stations,id',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'duration_hours' => 'required|numeric|min:1',
        ]);
        try {
            $tz = ShiftPolicy::active()?->timezone ?: 'Europe/Riga';
            $startsAt = Carbon::parse($request->input('date').' '.$request->input('start_time'), $tz);
            if ($startsAt->lte(now($tz))) {
                return response()->json([
                    'available' => false,
                    'count' => 0,
                    'error' => __('portal.shift_start_must_be_future'),
                    'reason_code' => 'SHIFT_IN_PAST',
                ], 422);
            }
            $durationHours = (float) $request->input('duration_hours');
            $endsAt = $startsAt->copy()->addMinutes((int) round($durationHours * 60));
            $driver = Auth::guard('driver')->user();
            if ($driver !== null && Shift::driverHasOverlappingBookedShift(
                (int) $driver->id,
                $startsAt->copy()->utc(),
                $endsAt->copy()->utc()
            )) {
                return response()->json([
                    'available' => false,
                    'count' => 0,
                    'vehicle_ids' => [],
                    'error' => __('portal.driver_shift_overlaps_existing'),
                    'reason_code' => 'DRIVER_SHIFT_OVERLAP',
                ], 422);
            }

            $result = app(ShiftAvailabilityService::class)->checkAvailability(
                (int) $request->input('station_id'),
                $startsAt,
                $durationHours
            );

            return response()->json([
                'available' => $result['count'] > 0,
                'count' => $result['count'],
                'vehicle_ids' => $result['vehicle_ids'] ?? [],
            ]);
        } catch (ShiftBookingException $e) {
            return response()->json([
                'available' => false,
                'count' => 0,
                'error' => $this->shiftBookingExceptionMessage($e),
                'reason_code' => $e->reasonCode,
            ], 422);
        }
    }

    public function confirmShift(Request $request): JsonResponse
    {
        $request->validate([
            'station_id' => 'required|integer|exists:stations,id',
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'duration_hours' => 'required|numeric|min:1',
        ]);
        $driver = Auth::guard('driver')->user();
        try {
            $policy = ShiftPolicy::active();
            $tz = $policy?->timezone ?: 'Europe/Riga';
            $nowInTz = now($tz);
            $planningWindowDays = $policy?->planning_window_days ?? 14;
            $maxDate = $nowInTz->copy()->addDays(max(0, $planningWindowDays - 1))->format('Y-m-d');
            $requestDate = $request->input('date');
            if ($requestDate > $maxDate) {
                return response()->json([
                    'success' => false,
                    'error' => __('portal.shift_date_outside_planning_window'),
                    'reason_code' => 'DATE_OUTSIDE_PLANNING_WINDOW',
                ], 422);
            }
            $startsAt = Carbon::parse($request->input('date').' '.$request->input('start_time'), $tz);
            if ($startsAt->lte($nowInTz)) {
                return response()->json([
                    'success' => false,
                    'error' => __('portal.shift_start_must_be_future'),
                    'reason_code' => 'SHIFT_IN_PAST',
                ], 422);
            }
            $durationHours = (float) $request->input('duration_hours');
            $shift = app(ShiftBookingService::class)->bookShift(
                $driver->id,
                (int) $request->input('station_id'),
                $startsAt,
                $durationHours
            );

            return response()->json([
                'success' => true,
                'shift' => [
                    'id' => $shift->id,
                    'starts_at' => $shift->starts_at->toIso8601String(),
                    'ends_at' => $shift->ends_at->toIso8601String(),
                    'vehicle' => $shift->vehicle ? ['id' => $shift->vehicle->id, 'label' => $shift->vehicle->label] : null,
                    'station' => $shift->station ? ['id' => $shift->station->id, 'name' => $shift->station->name] : null,
                ],
            ]);
        } catch (ShiftBookingException $e) {
            return response()->json([
                'success' => false,
                'error' => $this->shiftBookingExceptionMessage($e),
                'reason_code' => $e->reasonCode,
            ], 422);
        }
    }

    public function profile(): View
    {
        $driver = Auth::guard('driver')->user();

        return view('driverportal.profile', [
            'driverName' => $driver->name,
            'driverEmail' => $driver->email,
            'driverPhone' => $driver->phone ?? '',
            'driverAtd' => $driver->atd_number ?? '',
            'driverLicense' => $driver->license_number ?? '',
            'driverId' => '#EVO-'.$driver->id,
            'documents' => [
                ['name' => __('portal.taxi_license_atd'), 'number' => $driver->atd_number ?? '', 'status' => __('portal.verified')],
                ['name' => __('portal.driver_license'), 'number' => $driver->license_number ?? '', 'status' => __('portal.verified')],
            ],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'atd_number' => 'nullable|string|max:50',
        ]);
        $driver = Auth::guard('driver')->user();
        $driver->update($request->only('name', 'phone', 'atd_number'));
        $locale = $request->route('locale', 'en');

        return redirect()->route('driverportal.profile', ['locale' => $locale])->with('status', 'saved');
    }

    public function copyWeek(Request $request): JsonResponse|RedirectResponse
    {
        $locale = $request->route('locale', 'en');
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => __('portal.copied')]);
        }

        return redirect()->route('driverportal.shifts', ['locale' => $locale]);
    }

    public function copyPreviousWeekPreview(Request $request): JsonResponse
    {
        $request->validate([
            'target_week_start' => 'required|date_format:Y-m-d',
        ]);
        $driver = Auth::guard('driver')->user();
        $targetWeekStart = Carbon::parse($request->input('target_week_start'))->startOfDay();
        $result = app(ShiftCopyService::class)->previewCopyWeek($driver, $targetWeekStart);

        return response()->json($result);
    }

    public function copyPreviousWeekConfirm(Request $request): JsonResponse
    {
        $request->validate([
            'selections' => 'required|array',
            'selections.*.station_id' => 'required|integer|exists:stations,id',
            'selections.*.starts_at' => 'required|date',
            'selections.*.duration_hours' => 'required|numeric|min:1',
        ]);
        $driver = Auth::guard('driver')->user();
        $result = app(ShiftCopyService::class)->confirmCopyWeek($driver, $request->input('selections'));
        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 422);
    }

    public function updateShift(Request $request, string $locale, Shift $shift): JsonResponse
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'duration_hours' => 'required|numeric|min:1',
            'extend_ongoing' => 'sometimes|boolean',
        ]);
        $driver = Auth::guard('driver')->user();
        if ((int) $shift->driver_id !== (int) $driver->id) {
            return response()->json(['success' => false, 'reason_code' => 'FORBIDDEN'], 403);
        }
        try {
            $policy = ShiftPolicy::active();
            $tz = $policy?->timezone ?: 'Europe/Riga';
            $nowInTz = now($tz);
            $planningWindowDays = $policy?->planning_window_days ?? 14;
            $maxDate = $nowInTz->copy()->addDays(max(0, $planningWindowDays - 1))->format('Y-m-d');
            if ($request->input('date') > $maxDate) {
                return response()->json([
                    'success' => false,
                    'error' => __('portal.shift_date_outside_planning_window'),
                    'reason_code' => 'DATE_OUTSIDE_PLANNING_WINDOW',
                ], 422);
            }
            $editService = app(ShiftEditService::class);
            if ($request->boolean('extend_ongoing')) {
                $expectedStart = $shift->starts_at->copy()->setTimezone($tz);
                if ($request->input('date') !== $expectedStart->format('Y-m-d')
                    || $request->input('start_time') !== $expectedStart->format('H:i')) {
                    return response()->json([
                        'success' => false,
                        'error' => __('portal.extend_shift_start_mismatch'),
                        'reason_code' => 'EXTEND_START_MISMATCH',
                    ], 422);
                }
                $durationInt = (int) round((float) $request->input('duration_hours'));
                $updated = $editService->extendOngoingShift($shift, $durationInt, $nowInTz);
            } else {
                $startsAt = Carbon::parse($request->input('date').' '.$request->input('start_time'), $tz);
                if ($startsAt->lte($nowInTz)) {
                    return response()->json([
                        'success' => false,
                        'error' => __('portal.shift_start_must_be_future'),
                        'reason_code' => 'SHIFT_IN_PAST',
                    ], 422);
                }
                $durationHours = (float) $request->input('duration_hours');
                $updated = $editService->updateShift($shift, $startsAt, $durationHours);
            }

            return response()->json([
                'success' => true,
                'shift' => [
                    'id' => $updated->id,
                    'starts_at' => $updated->starts_at->toIso8601String(),
                    'ends_at' => $updated->ends_at->toIso8601String(),
                    'vehicle' => $updated->vehicle ? ['id' => $updated->vehicle->id, 'label' => $updated->vehicle->label] : null,
                    'station' => $updated->station ? ['id' => $updated->station->id, 'name' => $updated->station->name] : null,
                ],
            ]);
        } catch (ShiftBookingException $e) {
            return response()->json([
                'success' => false,
                'error' => $this->shiftBookingExceptionMessage($e),
                'reason_code' => $e->reasonCode,
            ], 422);
        }
    }

    public function cancelShift(Request $request, string $locale, Shift $shift): JsonResponse
    {
        $driver = Auth::guard('driver')->user();
        if ((int) $shift->driver_id !== (int) $driver->id) {
            return response()->json([
                'success' => false,
                'reason_code' => 'FORBIDDEN',
            ], 403);
        }
        if ($shift->status !== ShiftStatus::Booked) {
            return response()->json([
                'success' => false,
                'reason_code' => 'NOT_BOOKED',
            ], 422);
        }
        $policy = ShiftPolicy::active();
        $tz = $policy?->timezone ?? 'UTC';
        $nowInTz = now($tz);
        $startsAtInTz = $shift->starts_at->copy()->setTimezone($tz);
        if (! $startsAtInTz->isFuture()) {
            return response()->json([
                'success' => false,
                'reason_code' => 'SHIFT_IN_PAST',
            ], 422);
        }
        app(ShiftCancellationService::class)->cancelByDriver($shift, $driver, 'cancelled_by_driver');

        return response()->json(['success' => true]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('driver')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $locale = $request->route('locale', 'en');

        return redirect()->route('driverportal.login', ['locale' => $locale]);
    }

    protected function shiftBookingExceptionMessage(ShiftBookingException $e): string
    {
        return match ($e->reasonCode) {
            'DRIVER_SHIFT_OVERLAP' => __('portal.driver_shift_overlaps_existing'),
            default => $e->getMessage(),
        };
    }
}
