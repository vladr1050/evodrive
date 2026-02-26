# Driver Portal – React prototype → Blade analysis

## 1. Reusable components (from prototype)

| Component | Description | Blade location |
|-----------|-------------|----------------|
| **Portal logo block** | “E” badge + “EvoDrive” + “Fleet Portal” label | `components/logo.blade.php` |
| **Sidebar nav** | Desktop nav with active state (Dashboard, Shifts, Profile) | Part of `layouts/portal.blade.php` |
| **Mobile header** | Logo + logout on small screens | Part of `layouts/portal.blade.php` |
| **Mobile bottom nav** | Icon + label per item | Part of `layouts/portal.blade.php` |
| **Language switcher** | LV / ENG / RUS buttons | `components/lang-switcher.blade.php` |
| **Logout button** | Red hover, icon + label | Part of layout |
| **Card** | White, border-slate-100, rounded-3xl, shadow-sm | Reused via Tailwind in pages |
| **Primary button** | bg-brand-600, rounded-xl, shadow | `components/button-primary.blade.php` (optional) |
| **Form input** | Label (uppercase), bg-slate-50, rounded-xl, focus:ring-brand-600 | `components/input.blade.php` |
| **Shift row card** | Time, vehicle, station, status badge, chevron | In dashboard + shifts |
| **Shift block (grid)** | Booked (green) vs reserved (slate), duration/station/vehicle | `components/shift-block.blade.php` |
| **Week day column** | Day name, date, “Add” button | Part of shifts grid |
| **Modal overlay** | fixed inset-0, bg-slate-900/60, backdrop-blur | `components/modal.blade.php` |
| **Stats card** | Title + key-value rows + progress bar | In dashboard |
| **Help card** | Dark (slate-900), “Need Help?”, CTA | In dashboard |
| **Profile avatar card** | Circle initials, name, ID, status badge | In profile |
| **Document row** | Icon, label, “Verified” badge | In profile |

## 2. Dynamic variables per page

### login.blade.php
- No server-driven content required for initial render (form state is client-side).
- Optional: `$error` (validation/redirect message).
- Optional: `$redirect` (URL after login).

### dashboard.blade.php
- `$driverName` – e.g. "Aleksandrs"
- `$nextShiftCountdown` – e.g. "02:45:12"
- `$nextShiftVehicle` – e.g. "Toyota Corolla (TX-1234)"
- `$nextShiftStation` – e.g. "Riga Center"
- `$upcomingShifts` – array of `{ id, vehicle, station, time, duration, status }` (status: Confirmed | Pending)
- `$weeklyTotalHours` – e.g. 32
- `$weeklyShiftsDone` – e.g. 4
- `$weeklyShiftsTotal` – e.g. 6

### shifts.blade.php
- `$view` – 'current' | 'next' (week selector)
- `$weekDates` – array of `{ name, date, month }` for Mon–Sun
- `$shifts` – array of `{ id, day, start, end, duration, vehicle, station, status }` (status: booked | full)
- `$vehicles` – options for create form (id/label)
- `$stations` – options for create form
- Copy-week and create-shift actions: use Axios (POST) from frontend; Blade only passes CSRF and endpoints.

### profile.blade.php
- `$driverName` – e.g. "Aleksandrs Bērziņš"
- `$driverEmail` – e.g. "aleksandrs.b@evodrive.lv"
- `$driverPhone` – e.g. "+371 20 000 000"
- `$driverAtd` – e.g. "ATD-998877"
- `$driverId` – e.g. "#EVO-4421"
- `$documents` – array of `{ name, status }` (e.g. "Driver License", "Verified")

## 3. React file → Blade file mapping

| React (prototype) | Blade (Laravel) |
|-------------------|-----------------|
| `PortalLayout.tsx` | `layouts/portal.blade.php` |
| `Login.tsx` | `login.blade.php` |
| `Dashboard.tsx` | `dashboard.blade.php` |
| `Shifts.tsx` | `shifts.blade.php` |
| `Profile.tsx` | `profile.blade.php` |
| (inline nav/logo) | `layouts/portal.blade.php` + `components/logo.blade.php` |
| (inline form fields) | Shared Tailwind classes; optional `components/input.blade.php` |
| (shift cards / modals) | `components/shift-block.blade.php`, `components/modal.blade.php` |

## 4. Translation keys (use Laravel `__()`)

Use namespace `portal.*` (e.g. `lang/en/portal.php`). Suggested keys:

- `portal.title` – Driver Portal / Vadītāja portāls / Портал водителя
- `portal.fleet_portal` – Fleet Portal
- `portal.dashboard` – Dashboard
- `portal.shifts` – Shifts
- `portal.profile` – Profile
- `portal.logout` – Logout
- `portal.back_to_website` – Back to Website
- `portal.operational_access` – Operational Fleet Access
- `portal.login_to_portal` – Login to Portal
- `portal.welcome_back` – Welcome back, :name
- `portal.next_shift` – Next Shift
- `portal.until_start` – until start
- `portal.vehicle` – Vehicle
- `portal.station` – Station
- `portal.upcoming_shifts` – Upcoming Shifts
- `portal.view_all` – View All
- `portal.weekly_stats` – Weekly Stats
- `portal.total_hours` – Total Hours
- `portal.completed_shifts` – Completed Shifts
- `portal.need_help` – Need Help?
- `portal.need_help_desc` – Our fleet managers are available 24/7…
- `portal.contact_support` – Contact Support
- `portal.shifts_subtitle` – Create your own schedule. Avoid reserved slots.
- `portal.current_week` – Current Week
- `portal.next_week` – Next Week
- `portal.copy_prev_week` – Copy Previous Week
- `portal.create_shift` – Create Shift
- `portal.my_shift` – My Shift
- `portal.reserved` – Reserved
- `portal.no_shifts_planned` – No shifts planned
- `portal.shift_created` – Shift Created!
- `portal.shift_created_desc` – Your new shift has been successfully added…
- `portal.great_thanks` – Great, thanks!
- `portal.start_time` – Start Time
- `portal.end_time` – End Time
- `portal.day` – Day
- `portal.personal_details` – Personal Details
- `portal.full_name` – Full Name
- `portal.email_address` – Email Address
- `portal.phone_number` – Phone Number
- `portal.taxi_license_atd` – Taxi License (ATD)
- `portal.save_changes` – Save Changes
- `portal.account_security` – Account Security
- `portal.account_security_desc` – Change your password…
- `portal.update_password` – Update Password
- `portal.active_status` – Active Status
- `portal.documents` – Documents
- `portal.driver_license` – Driver License
- `portal.identity_card` – Identity Card
- `portal.verified` – Verified
- `portal.shifts_legend_info` – You can create shifts in any free time slots. Max 12h per shift.

(Plus overlap error, All Stations, etc. as needed.)

## 5. Axios usage (client-side only)

- **Login** – POST form to login route (or use standard Laravel form submit).
- **Copy previous week** – POST to an API route, then refresh or update shifts.
- **Create shift** – POST to an API route, then show success state / refresh list.
- **Confirm shifts** – if needed later, POST to confirm endpoint.

All strings in the UI must come from `__('portal.key')` (or existing `__('ui.key')` where applicable). Tailwind classes kept consistent with the prototype (brand-600, slate-50, rounded-3xl, etc.).
