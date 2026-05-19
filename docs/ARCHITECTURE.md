# Architecture Document — FlowForge

## Overview

FlowForge menggunakan **Microservices Architecture** dalam satu monorepo. Setiap service adalah Laravel application independen yang berkomunikasi via HTTP internal dan RabbitMQ untuk async messaging.

## Architecture Decision Records

### ADR-001: Monorepo dengan Multiple Laravel Apps

**Decision**: Menggunakan monorepo dengan service Laravel terpisah, bukan monolith atau fully distributed microservices.

**Rationale**:
- MVP dalam 4 hari membutuhkan shared tooling dan developer experience yang cepat
- Monorepo memungkinkan shared packages tanpa private registry
- Docker Compose menjalankan semua service sekaligus untuk development
- Tetap memiliki clear bounded context dan isolation

**Trade-off**: Deployment coupling di monorepo, solved dengan CI per-service path filters.

### ADR-002: PostgreSQL sebagai Single Database

**Decision**: Satu PostgreSQL instance dengan logical schema separation untuk MVP.

**Rationale**:
- Mengurangi operational complexity untuk development
- Foreign key constraints tetap bisa digunakan cross-service (shared DB)
- Migration ke separate databases bisa dilakukan incremental
- Tenant isolation via application-level scoping (tenant_id)

**Production path**: Migrasi ke database-per-service menggunakan Change Data Capture (CDC).

### ADR-003: SSE untuk Real-Time Updates

**Decision**: Server-Sent Events (SSE) sebagai pengganti WebSocket untuk real-time monitoring.

**Rationale**:
- SSE lebih sederhana untuk unidirectional server→client push
- Native HTTP — tidak perlu upgrade protocol
- Auto-reconnect built-in di browser
- Redis Pub/Sub sebagai backbone antar service
- Lebih mudah scale di belakang load balancer

**Trade-off**: Tidak bisa bidirectional, tetapi use case monitoring hanya butuh server push.

### ADR-004: RabbitMQ untuk Inter-Service Messaging

**Decision**: RabbitMQ sebagai message broker untuk workflow execution dan inter-service communication.

**Rationale**:
- Reliable message delivery dengan acknowledgment
- Dead letter queues untuk failed messages
- Laravel memiliki built-in queue driver support
- Exchange/routing flexibility untuk event routing

### ADR-005: GUID/UUID sebagai Primary Key

**Decision**: UUID v4 untuk semua primary keys.

**Rationale**:
- Globally unique — aman untuk distributed systems
- Tidak expose sequential ordering (security)
- Merge-friendly antar databases
- PostgreSQL `uuid-ossp` extension performant

**Trade-off**: Sedikit lebih besar storage vs bigint, solved dengan proper indexing.

## Service Communication

```
┌──────────────┐     HTTP      ┌──────────────────┐
│ API Gateway  │──────────────▶│ Identity Service │
│              │               └──────────────────┘
│              │     HTTP      ┌──────────────────┐
│              │──────────────▶│ Workflow Service  │
│              │               └──────────────────┘
│              │     HTTP      ┌──────────────────┐
│              │──────────────▶│ Execution Service│
│              │               └──────────────────┘
│              │     HTTP      ┌──────────────────┐
│              │──────────────▶│ AI Service       │
└──────────────┘               └──────────────────┘
                                        │
                               RabbitMQ  │  Redis Pub/Sub
                                        ▼
                               ┌──────────────────┐
                               │ Execution Worker │
                               └──────────────────┘
```

### Synchronous (HTTP)
- API Gateway → semua service (request forwarding)
- Execution Service → AI Service (failure analysis)

### Asynchronous (RabbitMQ)
- Workflow Service → Execution Service (trigger workflow run)
- Execution Service → Redis (step status events)
- Execution Worker: konsumsi jobs dari queue

### Real-Time (Redis Pub/Sub → SSE)
- Execution Service publish step status ke Redis channel
- API Gateway subscribe dan stream via SSE ke frontend

## Data Flow

### Workflow Trigger Flow
```
1. Client POST /api/workflows/{id}/trigger
2. API Gateway validates JWT, forwards to Workflow Service
3. Workflow Service validates workflow, publishes to RabbitMQ
4. Execution Service creates workflow_run record
5. Execution Worker picks up job, resolves DAG
6. Worker executes steps respecting dependencies
7. Each step completion publishes event to Redis
8. API Gateway streams events via SSE to frontend
```

## Security Model

- **Authentication**: JWT tokens issued by Identity Service
- **Authorization**: RBAC (Admin, Editor, Viewer) enforced at API Gateway
- **Tenant Isolation**: All queries scoped by tenant_id from JWT claims
- **Rate Limiting**: Redis-based sliding window at API Gateway and Nginx
- **Input Validation**: Request validation di setiap service endpoint

## Scalability Considerations

| Component | Horizontal Scale Strategy |
|-----------|--------------------------|
| API Gateway | Multiple instances behind LB |
| Identity Service | Stateless, scale horizontally |
| Workflow Service | Stateless, scale horizontally |
| Execution Service | Scale workers independently |
| PostgreSQL | Read replicas, then sharding by tenant |
| Redis | Redis Cluster for pub/sub scale |
| RabbitMQ | Clustered with quorum queues |
