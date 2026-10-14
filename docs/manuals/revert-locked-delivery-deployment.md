# Deployment and manual verification

Database commands below are for a human operator. Agents did not run migrations, query the database, boot Laravel, or submit application requests.

From `C:\laragon\www\emr2\pdims`, apply only the new shared hospital audit-table migration:

```powershell
php artisan migrate --path=database/migrations/2026_10_12_000001_create_pharm_delivery_audits_table.php --force
```

Deploy the synchronized source in both applications together. The migration targets the `hospital` connection explicitly, creates `pharm_delivery_audits` only when absent, and preserves it on rollback. The table must exist before opening delivery details or locking/reverting deliveries. Run the migration from one application only because the table is shared. Confirm the hospital connection points to the intended inventory database before execution.

On a human-operated test environment, verify:

- Open the reversal modal, review its warning, and verify Confirm Reversal remains disabled until confirmation is checked. Cancel and reopen it to verify the reason and confirmation reset. Submit without confirmation through a direct request and verify server validation rejects it.
- Lock a pending delivery, revert it with a reason and confirmation, then re-lock it. Stock, beginning balance, purchase receipts and receipt cards should return to their expected values, with three audit entries.
- Include multiple delivery lines that share an inventory batch. Their combined quantity must be available; a shortage on any line leaves the entire delivery unchanged.
- Reduce a batch below its delivery quantity and attempt reversal. Verify the error includes the available/required quantities and no partial reversal is saved.
- Verify consumption/issue/pull-out totals do not increase. Purchase receipt totals should decrease by the reversed quantity.
- Attempt a repeated lock/revert, a concurrent edit, an item from a different delivery and access from another location. Verify duplicate posting and unauthorized mutations are rejected.
- Verify historical deliveries with unique original posting records; ambiguous records and closed reporting periods must be rejected.
- Verify the audit remains visible after editing pending items and includes the actor, timestamp, reason, original IDs, lot, expiry, quantities and before/after stock balances.

Runtime SQL Server locking, migration deployment, Livewire rendering and report output remain unverified until these checks are performed.

Database-free regression scenarios were executed with `php tests/Isolated/delivery_reversal.php`; they use in-memory substitutes only and do not validate actual SQL Server behavior.
