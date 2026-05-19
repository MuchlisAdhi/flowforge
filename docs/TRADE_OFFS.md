# Trade-Offs — FlowForge MVP

## 1. Shared Database vs Database-per-Service

**Chosen**: Shared PostgreSQL database dengan tenant_id isolation.

**Why**: MVP timeline (4 hari) tidak memungkinkan operational complexity dari multiple databases. Shared DB memungkinkan foreign key constraints dan simpler transactions.

**Risk**: Coupling antar service di data layer.

**Migration path**: Implement strangler fig pattern — introduce CDC (Debezium) dan migrate service satu per satu ke dedicated database.

## 2. SSE vs WebSocket

**Chosen**: Server-Sent Events (SSE).

**Why**: 
- Unidirectional push cukup untuk monitoring dashboard
- Simpler infrastructure (standard HTTP)
- Built-in reconnection
- Easier to scale behind load balancers

**Trade-off**: Tidak bisa client→server via same connection. Untuk bidirectional (real-time collaboration), akan butuh WebSocket di masa depan.

## 3. Monorepo vs Polyrepo

**Chosen**: Monorepo.

**Why**:
- Single `docker-compose up` untuk development
- Shared CI configuration
- Atomic commits across services
- Easier code review

**Trade-off**: Build times grow, solved dengan path-based CI triggers.

## 4. UUID vs Auto-Increment ID

**Chosen**: UUID v4 (GUID).

**Why**:
- Globally unique tanpa coordination
- Security (unpredictable IDs)
- Preparation untuk distributed database

**Trade-off**: 16 bytes vs 8 bytes, slightly larger index. Mitigated: PostgreSQL handles UUID indexes well.

## 5. Execution Logs di PostgreSQL vs Dedicated Log Store

**Chosen**: Append-only table di PostgreSQL untuk MVP.

**Why**:
- Satu operational dependency
- Simpler development
- Sufficient untuk MVP volume

**Trade-off**: Akan lambat pada high volume (>100k logs/day per tenant).

**Migration path**: 
- Short-term: Partition table by date (monthly)
- Medium-term: Migrate ke ClickHouse/TimescaleDB
- Long-term: Stream ke OpenSearch untuk full-text search

## 6. Synchronous HTTP vs Event-Driven

**Chosen**: Hybrid — synchronous untuk read operations, async (RabbitMQ) untuk workflow execution.

**Why**: 
- CRUD operations benefit dari immediate response
- Workflow execution naturally async dan long-running
- Queue ensures reliable execution even under load

**Trade-off**: Mixed consistency model. Mitigated: clear documentation yang mana sync vs async.

## 7. Mock AI Provider sebagai Default

**Chosen**: MockAIProvider untuk development, Groq sebagai production provider.

**Why**:
- Development tidak bergantung pada external API
- Tests berjalan tanpa API key
- Consistent responses untuk testing

**Trade-off**: Mock responses static, tidak merepresentasikan real AI behavior. Solved: integration tests terpisah yang bisa run dengan real provider.

## 8. Rate Limiting di Nginx + Application Layer

**Chosen**: Dual-layer rate limiting.

**Why**:
- Nginx: coarse protection (DDoS level)
- Application: fine-grained per-tenant/per-user limits

**Trade-off**: Configuration di dua tempat. Mitigated: Nginx handles raw request floods, app handles business logic limits.

## 9. Simplified Script Execution

**Chosen**: Predefined script commands only (no arbitrary code execution).

**Why**:
- Security: arbitrary code execution is dangerous
- Sandboxing properly membutuhkan lebih dari 4 hari (containers, gVisor, etc.)
- Predefined commands cover 80% use cases

**Trade-off**: Less flexible. Future: Implement WebAssembly sandbox atau OCI container-based execution.

## 10. JWT tanpa Refresh Token Flow (MVP)

**Chosen**: Simple JWT dengan configurable TTL.

**Why**: Faster implementation, sufficient untuk MVP demo.

**Trade-off**: Token rotation dan revocation limited.

**Migration path**: Implement refresh token endpoint, token blacklist via Redis, sliding window expiry.

---

## 11. Microservices MVP vs Production Microservices

### MVP Architecture (Current)

```
┌─────────────────────────────────────────────────────────────────┐
│ Monorepo with 5 Laravel apps, shared PostgreSQL, docker-compose │
└─────────────────────────────────────────────────────────────────┘
```

**What we have:**
- 5 Laravel services (identity, workflow, execution, AI, gateway)
- Shared PostgreSQL database (logical separation by table ownership)
- RabbitMQ for async workflow execution dispatch
- Redis for rate limiting, caching, and SSE pub/sub
- Synchronous HTTP inter-service communication via gateway
- Single docker-compose for local development
- Shared JWT secret across services for token validation

**What makes this "microservices-lite":**
- Clear bounded contexts (identity ≠ workflow ≠ execution)
- Independent deployable units (each has own Dockerfile)
- Service-to-service communication via HTTP (not shared memory)
- Async execution decoupled via queue
- Each service has own route definitions and middleware stack

**What's NOT production microservices:**
- Shared database (no data ownership boundary)
- No service mesh / service discovery
- No distributed tracing built-in
- No circuit breaker patterns
- JWT validated locally (no token introspection endpoint)
- No API versioning strategy
- No saga pattern for distributed transactions

### Production Architecture (Target)

```
┌──────────────────────────────────────────────────────────────────────────┐
│ Separate repos, database-per-service, service mesh, event sourcing       │
└──────────────────────────────────────────────────────────────────────────┘
```

**Migration roadmap from MVP → Production:**

| Step | Change | Risk | Timeline |
|------|--------|------|----------|
| 1 | Add distributed tracing (OpenTelemetry) | Low | Week 1 |
| 2 | Extract identity-service DB | Medium | Week 2-3 |
| 3 | Replace HTTP calls with async events where possible | Medium | Week 3-4 |
| 4 | Add circuit breakers (resilience4php or custom) | Low | Week 4 |
| 5 | Token introspection endpoint (replace shared JWT secret) | Medium | Week 5 |
| 6 | Database-per-service for workflow + execution | High | Week 6-8 |
| 7 | Service mesh (Istio/Linkerd) for observability | Medium | Week 8-10 |
| 8 | Event sourcing for execution logs | High | Week 10-12 |

### Key Differences Explained

| Aspect | MVP | Production |
|--------|-----|------------|
| **Data ownership** | Shared DB, FK constraints | DB-per-service, eventual consistency |
| **Communication** | Sync HTTP + RabbitMQ | Event-driven (AMQP/Kafka) + gRPC |
| **Consistency** | Strong (single DB transaction) | Eventual (saga pattern) |
| **Service discovery** | Hardcoded URLs in ENV | Consul/Kubernetes DNS |
| **Auth propagation** | JWT decoded locally | Token introspection + mTLS |
| **Failure handling** | Try/catch + queue retry | Circuit breaker + dead letter + alerts |
| **Observability** | Application logs | Distributed traces + metrics + logs |
| **Deployment** | docker-compose | Kubernetes / ECS with rolling updates |
| **Schema migration** | `artisan migrate` all services | Per-service migration with backward compat |

### Why This MVP Approach is Correct for 4 Days

1. **Proves the architecture** — boundaries are drawn correctly, upgrading to true microservices is incremental, not rewrite
2. **Local development** — `docker compose up` just works, no complex service mesh setup
3. **Testing** — integration tests can assert across boundaries easily
4. **Shared understanding** — team can see all code in one place
5. **No premature optimization** — we don't know traffic patterns yet, so we don't over-engineer

### Anti-Patterns We Avoided

- ❌ **Distributed monolith**: Services don't share models/code (each has own Model classes)
- ❌ **Chatty services**: Gateway batches/forwards, services don't call each other directly (except AI analysis)
- ❌ **Shared state**: Each service maintains its own in-memory state
- ❌ **Anemic services**: Each service has meaningful business logic, not just CRUD proxies

---

## Summary Matrix

| Decision | Speed | Security | Scalability | Maintainability |
|----------|-------|----------|-------------|-----------------|
| Shared DB | ✅ | ⚠️ | ⚠️ | ✅ |
| SSE | ✅ | ✅ | ✅ | ✅ |
| Monorepo | ✅ | ✅ | ⚠️ | ✅ |
| UUID | ⚠️ | ✅ | ✅ | ✅ |
| PG Logs | ✅ | ✅ | ⚠️ | ✅ |
| Hybrid Sync/Async | ✅ | ✅ | ✅ | ⚠️ |
| Mock AI | ✅ | ✅ | ✅ | ✅ |

✅ = Good for this aspect | ⚠️ = Acceptable trade-off with known migration path
