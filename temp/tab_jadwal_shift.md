# Why the Barista / Employee Field Is Available During Testing

## Context
- The stock report form (`/stock-report?outlet=…`) must always send a `user_id` with each submission so the backend can tie a report to a specific barista.
- The real shift data normally comes from the `jadwal_shift` table through the `/api/outlets/{outlet}/schedules/today` endpoint (`app/Http/Controllers/ScheduleLookupController.php`).
- During this testing phase the actual `jadwal_shift` records are not populated, so the live schedule lookup would return an empty list and block any report submissions.

## How the Temporary Fallback Works
- The React screen checks the API first (`resources/js/Pages/StockReport.jsx`, `fetchSchedules()`).
- If the API responds with no staff or an error, the component swaps to a **testing fallback**: it loads a seeded list of baristas (`allBaristas`) that is bundled with the page props by `StockReportController` (`app/Http/Controllers/StockReportController.php` lines 76-85).
- While the fallback is active the UI shows a warning message (`"⚠️ No schedule found. Showing all baristas for testing."`) so users know real schedule data was not used.
- This lets testers continue submitting stock reports without waiting for actual roster data, and it keeps the rest of the reporting workflow (validation, backend writes, notifications) testable end-to-end.

## Talking Points for the Lecturer
1. **Testing necessity** – We still have to exercise the report submission flow, so the dropdown remains usable with seeded baristas until the real roster feed is ready.
2. **Clear messaging** – The interface clearly indicates when the fallback data is shown, preventing confusion between live and test modes.
3. **Future behaviour** – Once the `jadwal_shift` table is populated in production, the fallback branch can be disabled (or flagged only for non-production environments) so the dropdown reflects actual on-duty staff.

## Next Actions Before Production
- Populate the `jadwal_shift` table (or the new `schedules` table) via the official scheduling process.
- Remove or guard the fallback logic (feature flag / environment check) so only live data powers the dropdown in production.
- Optionally add automated tests that assert the fallback is inactive when schedule data exists, ensuring the hand-off is permanent.
