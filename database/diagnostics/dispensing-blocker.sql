-- Read-only incident capture. Run while the dispensing block is happening.
-- Replace these IDs if the blocking chain has changed. Do not kill by old IDs.
DECLARE @owner int = 119, @reader int = 85;

SELECT s.session_id, s.status AS session_status, s.open_transaction_count,
       s.host_name, s.program_name, s.host_process_id,
       s.last_request_start_time, s.last_request_end_time,
       r.status AS request_status, r.command, r.blocking_session_id,
       r.wait_type, r.wait_time AS wait_ms, r.wait_resource,
       r.cpu_time, r.logical_reads, r.total_elapsed_time,
       tx.transaction_begin_time,
       sql_text.text AS current_or_last_batch
FROM sys.dm_exec_sessions AS s
LEFT JOIN sys.dm_exec_requests AS r ON r.session_id = s.session_id
LEFT JOIN sys.dm_exec_connections AS c ON c.session_id = s.session_id
LEFT JOIN sys.dm_tran_session_transactions AS st ON st.session_id = s.session_id
LEFT JOIN sys.dm_tran_active_transactions AS tx ON tx.transaction_id = st.transaction_id
OUTER APPLY sys.dm_exec_sql_text(COALESCE(r.sql_handle, c.most_recent_sql_handle)) AS sql_text
WHERE s.session_id IN (@owner, @reader)
   OR r.blocking_session_id IN (@owner, @reader);

SELECT l.request_session_id, DB_NAME(l.resource_database_id) AS database_name,
       l.resource_type, l.resource_description, l.resource_associated_entity_id,
       l.request_mode, l.request_status, l.request_owner_type
FROM sys.dm_tran_locks AS l
WHERE l.request_session_id IN (@owner, @reader);

-- Check actual column types before changing parameter binding or adding casts.
SELECT c.name, t.name AS type_name, c.max_length, c.collation_name
FROM hospital.sys.columns AS c
JOIN hospital.sys.types AS t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID(N'hospital.dbo.hrxo')
  AND c.name IN ('docointkey', 'estatus', 'qtyissued', 'tx_type', 'dodtepost', 'dotmepost', 'deptcode');

-- Existing key indexes; do not create a duplicate index based on a screenshot.
SELECT i.name, i.type_desc, i.is_unique, i.is_disabled, i.filter_definition,
       ic.key_ordinal, ic.is_included_column, c.name AS column_name
FROM hospital.sys.indexes AS i
JOIN hospital.sys.index_columns AS ic ON ic.object_id = i.object_id AND ic.index_id = i.index_id
JOIN hospital.sys.columns AS c ON c.object_id = ic.object_id AND c.column_id = ic.column_id
WHERE i.object_id = OBJECT_ID(N'hospital.dbo.hrxo')
ORDER BY i.index_id, ic.index_column_id;

-- Trigger work executes as part of the UPDATE's transaction.
SELECT tr.name, tr.is_disabled, sm.definition
FROM hospital.sys.triggers AS tr
LEFT JOIN hospital.sys.sql_modules AS sm ON sm.object_id = tr.object_id
WHERE tr.parent_id = OBJECT_ID(N'hospital.dbo.hrxo');
