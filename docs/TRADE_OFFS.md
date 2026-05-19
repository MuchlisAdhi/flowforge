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
