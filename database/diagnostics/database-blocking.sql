/* SQL Server blocking diagnostics. Run in SSMS with a DBA account.
   Read-only: this script does NOT kill sessions or change database settings.
   Server-wide visibility requires VIEW SERVER STATE (SQL Server 2019 and older)
   or VIEW SERVER PERFORMANCE STATE (SQL Server 2022+), or equivalent DBA access.
   Run during the incident, then repeat to distinguish a brief wait from a stall.
*/
SET NOCOUNT ON;

-- Blocked requests and their immediate blockers, including sleeping blockers.
SELECT
    r.session_id AS blocked_session_id,
    r.blocking_session_id,
    DB_NAME(r.database_id) AS request_database,
    r.wait_type, r.wait_time AS wait_ms, r.wait_resource,
    s.host_name AS blocked_host, s.program_name AS blocked_program,
    b.status AS blocker_status, b.login_name AS blocker_login,
    b.host_name AS blocker_host, b.program_name AS blocker_program,
    b.open_transaction_count AS blocker_open_transactions,
    br.blocking_session_id AS blocker_is_waiting_on,
    blocked_sql.text AS blocked_batch,
    blocker_sql.text AS blocker_current_or_last_batch
FROM sys.dm_exec_requests AS r
JOIN sys.dm_exec_sessions AS s ON s.session_id = r.session_id
LEFT JOIN sys.dm_exec_sessions AS b ON b.session_id = r.blocking_session_id
LEFT JOIN sys.dm_exec_requests AS br ON br.session_id = b.session_id
LEFT JOIN sys.dm_exec_connections AS bc ON bc.session_id = b.session_id
OUTER APPLY sys.dm_exec_sql_text(r.sql_handle) AS blocked_sql
OUTER APPLY sys.dm_exec_sql_text(COALESCE(br.sql_handle, bc.most_recent_sql_handle)) AS blocker_sql
WHERE r.blocking_session_id > 0
ORDER BY r.wait_time DESC;

-- Open user transactions, including idle connections with no active request.
SELECT s.session_id, s.status, s.login_name, s.host_name, s.program_name,
       s.open_transaction_count, t.transaction_begin_time,
       DATEDIFF(SECOND, t.transaction_begin_time, GETDATE()) AS transaction_age_seconds,
       sql_text.text AS current_or_last_batch
FROM sys.dm_exec_sessions AS s
JOIN sys.dm_tran_session_transactions AS st ON st.session_id = s.session_id
JOIN sys.dm_tran_active_transactions AS t ON t.transaction_id = st.transaction_id
LEFT JOIN sys.dm_exec_requests AS r ON r.session_id = s.session_id
LEFT JOIN sys.dm_exec_connections AS c ON c.session_id = s.session_id
OUTER APPLY sys.dm_exec_sql_text(COALESCE(r.sql_handle, c.most_recent_sql_handle)) AS sql_text
WHERE s.is_user_process = 1 AND s.session_id <> @@SPID
ORDER BY t.transaction_begin_time;

-- Pharmacy application locks: GRANT is the owner, WAIT is a waiter.
-- Resources are scoped by database/principal; compare database_id too.
SELECT request_session_id, DB_NAME(resource_database_id) AS database_name,
       resource_description, request_mode, request_status, request_owner_type
FROM sys.dm_tran_locks
WHERE resource_type = 'APPLICATION'
ORDER BY resource_database_id, resource_description, request_status;

/* Incident resolution (manual, after reviewing the results above):
   1. Follow blocking_session_id to the head blocker. Verify host, login,
      program, transaction age and SQL; record the output before intervening.
   2. Prefer letting legitimate work finish. For an abandoned transaction,
      have the owning application/connection roll it back. ROLLBACK in a new
      SSMS window cannot roll back a different session's transaction.
   3. Only if terminating that confirmed session is acceptable, replace 57
      with its freshly verified session ID and execute the next command alone.
      KILL disconnects it and rolls back its uncommitted work. Locks may remain
      while rollback runs. Do not bulk-kill sessions or restart SQL Server.

      KILL 57;

   4. Monitor an already-killed session with the following command. Do not
      repeatedly issue plain KILL: session IDs can be reused after completion.

      KILL 57 WITH STATUSONLY;

   5. Rerun diagnostics and refresh affected pharmacy screens before retrying.
      Do not blindly resubmit a dispensing operation with an unknown result.

   Prevention: preserve inventory/code-allocation correctness locks. Consider
   READ_COMMITTED_SNAPSHOT only after a DBA review and testing across the shared
   hospital/EMR applications; it does not remove writer or application locks.
   Check actual execution plans and existing indexes before adding indexes.

   References:
   https://learn.microsoft.com/en-us/troubleshoot/sql/database-engine/performance/understand-resolve-blocking
   https://learn.microsoft.com/en-us/sql/t-sql/language-elements/kill-transact-sql
*/
