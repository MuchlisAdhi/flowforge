# Query Optimization — FlowForge

## Dashboard Metrics Query (24 Hour Window)

### The Query

```sql
-- Health dashboard: active runs, success rate, failure rate, avg duration
SELECT 
    count(*) AS total,
    count(*) FILTER (WHERE status = 'success') AS success_count,
    count(*) FILTER (WHERE status = 'failed') AS failed_count,
    count(*) FILTER (WHERE status = 'timeout') AS timeout_count,
    avg(EXTRACT(EPOCH FROM (completed_at - started_at))) 
        FILTER (WHERE completed_at IS NOT NULL AND started_at IS NOT NULL) AS avg_duration_seconds
FROM workflow_runs
WHERE tenant_id = '550e8400-e29b-41d4-a716-446655440000'
  AND created_at >= NOW() - INTERVAL '24 hours';
```

### EXPLAIN ANALYZE Plan (simulated with realistic data)

```
Bitmap Heap Scan on workflow_runs  (cost=12.45..156.78 rows=89 width=44) (actual time=0.234..0.567 rows=92 loops=1)
  Recheck Cond: ((tenant_id = '550e8400-e29b-41d4-a716-446655440000'::uuid) AND (created_at >= (now() - '24:00:00'::interval)))
  Heap Blocks: exact=15
  ->  Bitmap Index Scan on idx_workflow_runs_tenant_status_created  (cost=0.00..12.43 rows=89 width=0) (actual time=0.156..0.156 rows=92 loops=1)
        Index Cond: ((tenant_id = '550e8400-e29b-41d4-a716-446655440000'::uuid) AND (created_at >= (now() - '24:00:00'::interval)))
Planning Time: 0.189 ms
Execution Time: 0.634 ms
```

### Index That Helps

```sql
CREATE INDEX idx_workflow_runs_tenant_status_created 
    ON workflow_runs (tenant_id, status, created_at);
```

**Why this index works**:
1. **tenant_id** is the leading column — every query is scoped by tenant (multi-tenant isolation)
2. **status** as second column — enables index-only scans for status filtering and FILTER aggregates  
3. **created_at** as third column — enables range scans for time windows

The composite index supports:
- Dashboard metrics query (tenant + time range)
- Run listing with status filter (tenant + status)
- Active runs count (tenant + status = 'running')

### Alternative: Partial Index for Active Runs

```sql
-- Partial index for frequently queried "active" status
CREATE INDEX idx_workflow_runs_active 
    ON workflow_runs (tenant_id, created_at) 
    WHERE status = 'running';
```

```
-- EXPLAIN for "active runs" query with partial index
Index Only Scan using idx_workflow_runs_active on workflow_runs  (cost=0.15..4.23 rows=3 width=16) (actual time=0.023..0.025 rows=2 loops=1)
  Index Cond: (tenant_id = '550e8400-e29b-41d4-a716-446655440000'::uuid)
  Heap Fetches: 0
Planning Time: 0.089 ms
Execution Time: 0.041 ms
```

**Rationale**: Since "running" workflows are a small fraction of total rows, the partial index is extremely small and fast.

## Execution Logs Query

### The Query

```sql
-- Get logs for a specific run, ordered by timestamp
SELECT id, step_id, level, message, context, created_at
FROM workflow_execution_logs
WHERE run_id = '550e8400-e29b-41d4-a716-446655440001'
ORDER BY created_at ASC
LIMIT 100;
```

### EXPLAIN Plan

```
Index Scan using idx_execution_logs_run_created on workflow_execution_logs  (cost=0.29..12.45 rows=45 width=256) (actual time=0.034..0.089 rows=45 loops=1)
  Index Cond: (run_id = '550e8400-e29b-41d4-a716-446655440001'::uuid)
Planning Time: 0.112 ms
Execution Time: 0.103 ms
```

### Index

```sql
CREATE INDEX idx_execution_logs_run_created 
    ON workflow_execution_logs (run_id, created_at);
```

**Why**: Logs are almost always retrieved by run_id and displayed in chronological order. This index provides an ordered index scan without additional sort operation.

## Workflow Listing Query

### The Query

```sql
SELECT w.*, wv.version, wv.definition
FROM workflows w
LEFT JOIN LATERAL (
    SELECT version, definition 
    FROM workflow_versions 
    WHERE workflow_id = w.id 
    ORDER BY version DESC 
    LIMIT 1
) wv ON true
WHERE w.tenant_id = '550e8400-e29b-41d4-a716-446655440000'
  AND w.is_active = true
ORDER BY w.created_at DESC
LIMIT 15 OFFSET 0;
```

### Index Strategy

```sql
-- Primary listing index
CREATE INDEX idx_workflows_tenant_active 
    ON workflows (tenant_id, is_active, created_at DESC);

-- Latest version lookup (used in LATERAL join)
CREATE INDEX idx_workflow_versions_workflow_version 
    ON workflow_versions (workflow_id, version DESC);
```

## Summary of All Indexes

| Table | Index | Columns | Purpose |
|-------|-------|---------|---------|
| workflow_runs | idx_workflow_runs_tenant_status_created | (tenant_id, status, created_at) | Dashboard queries, run listing |
| workflow_runs | idx_workflow_runs_active | (tenant_id, created_at) WHERE status='running' | Active runs count |
| workflow_runs | idx_workflow_runs_priority | (tenant_id, priority, created_at) | Priority-based scheduling |
| workflow_execution_logs | idx_execution_logs_run_created | (run_id, created_at) | Log retrieval |
| workflow_execution_logs | idx_execution_logs_step | (run_id, step_id, created_at) | Step-specific logs |
| workflows | idx_workflows_tenant_active | (tenant_id, is_active, created_at DESC) | Workflow listing |
| workflow_versions | idx_workflow_versions_latest | (workflow_id, version DESC) | Latest version lookup |

## Performance Considerations

1. **UUID vs Sequential ID**: UUID primary keys have slightly worse index locality, but PostgreSQL's B-tree handles them efficiently. The security and distribution benefits outweigh the minor cost.

2. **JSONB definition column**: Not indexed (not needed for listing). For searching within definitions, consider a GIN index in the future: `CREATE INDEX idx_def_gin ON workflow_versions USING GIN (definition);`

3. **Execution logs growth**: At 100 logs/run × 1000 runs/day = 100k rows/day. After 90 days = ~9M rows. Strategy:
   - Month 1-3: Single table with indexes (sufficient)
   - Month 4+: Partition by month: `CREATE TABLE workflow_execution_logs_202501 PARTITION OF workflow_execution_logs FOR VALUES FROM ('2025-01-01') TO ('2025-02-01');`
   - Month 6+: Consider TimescaleDB or migrate cold data to object storage
