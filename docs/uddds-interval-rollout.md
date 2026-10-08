# UDDDS interval recurrence

Supply schedules use calendar days in Asia/Manila, anchored to the enrollment start date. Daily is interval 1; every 48h and 72h mean intervals 2 and 3 calendar days, not exact administration times. Custom intervals are positive SQL Server INT values (1–2147483647). The end date is inclusive. Quantities are copied for one supply occurrence and are never multiplied by the interval.

Existing null intervals remain daily. Missed supplies do not change the anchor or cause automatic catch-up. An original order already issued on a due date supplies that occurrence. Schedule changes require removal and re-enrollment. Historical slips and issued records remain available; off-schedule or stale pending generated rows cannot be processed through either UDDDS or encounter charge/issue actions.

## Human operator deployment

No database commands or application requests were executed by the implementation agent.

1. Apply `database/migrations/2026_10_08_000001_add_uddds_interval_days_to_hrxo_table.php` through your approved migration procedure. The migration only adds the nullable column if absent. If both applications share `hospital.dbo.hrxo`, the physical column is added once; each deployment can record its migration through its normal procedure. Do not use refresh/reset/fresh or rollback commands.
2. Deploy the paired application changes. Without the column, daily operation remains available and non-daily enrollment is disabled. The daily scheduler remains at 07:00 Asia/Manila.
3. In an approved test environment, enroll a BASIC inpatient order beginning October 8 with interval 3 and ending October 20. Verify supplies on October 8, 11, 14, 17 and 20, and no actionable entry on October 9 or 10. Verify the displayed next supply date and the quantity for each occurrence.
4. Repeat generation and queue processing for the same source/date; verify a second order is not created. Verify an original order issued on the same supply date is not duplicated. Verify insufficient stock, alternate funding and printing still behave normally.
5. Remove and re-enroll using a different interval. Verify old pending rows are rejected, issued history remains printable, and future supplies follow the new anchor. Review any pre-existing off-schedule charged/pending rows manually using hospital procedures; the feature does not delete records or reverse charges.
6. Test existing null-interval daily orders, custom intervals, invalid input, boundaries, and both applications' enrollment and new-order forms. Ensure G24/OR orders cannot activate a recurring schedule.

## Database-free verification

Run `php tests/Standalone/UdddsRecurrenceTest.php` from either application's directory. This runner does not bootstrap Laravel or load application configuration. It uses in-memory stand-ins for database/schema facades and drug order persistence; unexpected connection attempts fail immediately. It exercises the actual schedule helper and service paths. Full browser, database concurrency, stock/charge integration and live scheduler behavior require human validation.

## Main PDIMS files changed

All paths below are relative to `C:\laragon\www\emr2\pdims`:

- `app/Services/Pharmacy/UdddsSchedule.php` — pure calendar-day recurrence helper.
- `app/Services/Pharmacy/UdddsService.php` — enrollment, due filtering, generation, materialization, processing guards and historical summaries.
- `app/Models/Pharmacy/Dispensing/DrugOrder.php` — interval mass assignment.
- `app/Livewire/Pharmacy/Dispensing/DispensingEncounter.php` — enrollment modal, new-order persistence, activation and direct charge/issue guards.
- `app/Livewire/Pharmacy/Prescriptions/UdddsWard.php` — report materialization errors before processing.
- `app/Console/Commands/GenerateUdddsDailyOrders.php` — readable invalid-schedule failures.
- `resources/views/livewire/pharmacy/dispensing/dispensing-encounter.blade.php` — enrollment controls and summaries.
- `resources/views/livewire/pharmacy/dispensing/uddds-schedule-fields.blade.php` — native recurrence/date inputs.
- `resources/views/livewire/pharmacy/dispensing/uddds-schedule-summary.blade.php` — recurrence and next-date display.
- `resources/views/livewire/pharmacy/prescriptions/uddds-ward.blade.php` — queue schedule display.
- `database/migrations/2026_10_08_000001_add_uddds_interval_days_to_hrxo_table.php` — additive schema source, not executed.
- `tests/Standalone/UdddsRecurrenceTest.php` — isolated fixtures and regression checks.
- `docs/uddds-interval-rollout.md` — rollout instructions and this inventory.

The satellite uses its existing `app/Http/Livewire/Pharmacy/Dispensing/EncounterTransactionView.php`, `app/Http/Livewire/Records/UdddsWard.php`, encounter SweetAlert view and `resources/views/livewire/records/uddds-ward.blade.php` for the corresponding PDIMS surfaces. Shared service, model, command, migration, summary, test and documentation changes are paired. There is no satellite-only feature; the satellite's SweetAlert controls are adapted to PDIMS native Livewire modal fields rather than copied into PDIMS.

## Issue and return metadata follow-up

Apply `database/migrations/2026_10_08_000002_add_uddds_fields_to_hrxo_transactions.php` through the approved human-operated migration procedure after the hrxo interval update. It adds `order_type`, `is_uddds`, `uddds_start_date`, `uddds_end_date`, `uddds_source_docointkey`, and `uddds_interval_days` to both `hospital.dbo.hrxoissue` and `hospital.dbo.hrxoreturn`, checking each column first. The transaction columns are nullable so old records remain unknown rather than being assigned invented historical schedules. No migration or backfill was executed by the agent.

Issue/return model creation captures available metadata fields from the corresponding order. Existing populated snapshots are preserved. Active encounter return writers include the fields using bound parameters and prefer a populated issue snapshot matching the order, encounter, patient and drug identifiers. If no issue snapshot was captured, legacy returns use the current order metadata. Missing or partially deployed columns are omitted from writes.

The current encounter issue logger is disabled in the satellite and does not create hrxoissue rows in PDIMS; the UDDDS service also does not create hrxoissue rows. This follow-up adds schema/model support without enabling an additional issue logger. The satellite's existing issue-job and shared-controller model writers receive metadata through the issue model's creation hook. Database writers outside these PHP models need their own integration to populate the added columns. Validate this in the actual issue-record pipeline before relying on historical issue snapshots; no historical schedule backfill is attempted.

Additional PDIMS files changed for this follow-up:

- `app/Services/Pharmacy/UdddsTransactionMetadata.php`
- `app/Models/Pharmacy/Dispensing/DrugOrderIssue.php`
- `app/Models/Pharmacy/Dispensing/DrugOrderReturn.php`
- `app/Livewire/Pharmacy/Dispensing/DispensingEncounter.php`
- `database/migrations/2026_10_08_000002_add_uddds_fields_to_hrxo_transactions.php`
- `tests/Standalone/UdddsTransactionMetadataTest.php`
- `docs/uddds-interval-rollout.md`

All changes have a satellite counterpart; the satellite return writer is `app/Http/Livewire/Pharmacy/Dispensing/EncounterTransactionView.php`. There are no satellite-only changes in this follow-up.

Run `php tests/Standalone/UdddsTransactionMetadataTest.php` independently in each application, in addition to the existing recurrence runner. The metadata runner only fires in-memory model creation events, with database/schema and order lookup fixtures; it never saves a model, starts Laravel, loads configuration or connects to a database. Human verification must cover real issue insertion, return insertion, snapshot retention after schedule changes, and integration with whichever system actually writes hrxoissue.
