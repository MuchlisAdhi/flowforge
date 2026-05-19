# Production Deployment — FlowForge

## Target Cloud: AWS (applicable ke GCP dengan service equivalents)

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                              Route 53 (DNS)                              │
└──────────────────────────────────┬──────────────────────────────────────┘
                                   │
┌──────────────────────────────────▼──────────────────────────────────────┐
│                     CloudFront (CDN + WAF)                                │
│                 - Static assets caching                                   │
│                 - WAF rules (OWASP Top 10)                               │
│                 - DDoS protection                                         │
└──────────┬──────────────────────────────────────────┬───────────────────┘
           │ /api/*                                    │ /*
┌──────────▼──────────┐                    ┌──────────▼──────────┐
│  ALB (API)          │                    │  S3 + CloudFront    │
│  - SSL termination  │                    │  (Frontend SPA)     │
│  - Health checks    │                    └─────────────────────┘
│  - Path routing     │
└──────────┬──────────┘
           │
┌──────────▼──────────────────────────────────────────────────────────────┐
│                        ECS Fargate Cluster                                │
│                                                                          │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐       │
│  │API Gateway │  │  Identity  │  │  Workflow   │  │ Execution  │       │
│  │ Service    │  │  Service   │  │  Service    │  │  Service   │       │
│  │ 2-4 tasks  │  │ 2-3 tasks  │  │ 2-3 tasks  │  │ 3-10 tasks │       │
│  └────────────┘  └────────────┘  └────────────┘  └────────────┘       │
│                                                                          │
│  ┌────────────┐  ┌────────────────────────────────────────────┐        │
│  │AI Service  │  │  Execution Workers (auto-scaling)          │        │
│  │ 1-2 tasks  │  │  Scales based on queue depth               │        │
│  └────────────┘  └────────────────────────────────────────────┘        │
└──────────────────────────────────────────────────────────────────────────┘
           │              │                │
┌──────────▼──────┐ ┌────▼────────┐ ┌────▼─────────────┐
│ RDS PostgreSQL  │ │ ElastiCache │ │ Amazon MQ        │
│ Multi-AZ        │ │ Redis       │ │ (RabbitMQ)       │
│ - Primary       │ │ Cluster     │ │ - Multi-AZ       │
│ - Read Replica  │ │ - 3 nodes   │ │ - Durable queues │
└─────────────────┘ └─────────────┘ └──────────────────┘
```

## Service Breakdown

### Compute: ECS Fargate

**Why Fargate**:
- Serverless containers — no EC2 management
- Per-second billing
- Automatic patching
- Native Docker support

**Service configuration**:
| Service | CPU | Memory | Min Tasks | Max Tasks | Scaling Metric |
|---------|-----|--------|-----------|-----------|----------------|
| API Gateway | 512 | 1024MB | 2 | 8 | Request count |
| Identity | 256 | 512MB | 2 | 4 | CPU utilization |
| Workflow | 256 | 512MB | 2 | 4 | CPU utilization |
| Execution | 512 | 1024MB | 2 | 6 | CPU + queue depth |
| Workers | 1024 | 2048MB | 3 | 20 | Queue depth |
| AI Service | 256 | 512MB | 1 | 3 | Request count |

### Database: RDS PostgreSQL 16

- **Instance**: db.r6g.large (2 vCPU, 16GB RAM)
- **Storage**: gp3 with PIOPS for consistent latency
- **Multi-AZ**: Enabled for high availability
- **Read Replica**: 1 read replica for dashboard queries
- **Backup**: Automated daily snapshots, 7 day retention
- **Encryption**: At rest (KMS) and in transit (SSL)

### Cache: ElastiCache Redis

- **Node type**: cache.r6g.large
- **Cluster mode**: Enabled, 3 shards
- **Use cases**: Session cache, rate limiting, pub/sub, query cache
- **Encryption**: In transit and at rest

### Message Broker: Amazon MQ (RabbitMQ)

- **Instance**: mq.m5.large
- **Multi-AZ**: Active/standby
- **Storage**: EBS with encryption
- **Queues**: Durable, with dead letter configuration

### Frontend: S3 + CloudFront

- **S3**: Static hosting for built SPA
- **CloudFront**: Global CDN distribution
- **Cache**: Immutable assets dengan long TTL, index.html dengan short TTL
- **Invalidation**: CI/CD invalidates on deploy

## Networking

```
┌─────────────────────────────────────────────┐
│                    VPC                        │
│  CIDR: 10.0.0.0/16                          │
│                                              │
│  ┌──────────────────────────────────┐       │
│  │ Public Subnets (10.0.1.0/24,    │       │
│  │                  10.0.2.0/24)     │       │
│  │ - ALB                             │       │
│  │ - NAT Gateway                     │       │
│  └──────────────────────────────────┘       │
│                                              │
│  ┌──────────────────────────────────┐       │
│  │ Private Subnets (10.0.10.0/24,   │       │
│  │                   10.0.11.0/24)   │       │
│  │ - ECS Tasks                       │       │
│  │ - RDS                             │       │
│  │ - ElastiCache                     │       │
│  │ - Amazon MQ                       │       │
│  └──────────────────────────────────┘       │
└─────────────────────────────────────────────┘
```

- Services di private subnet — tidak direct internet access
- NAT Gateway untuk outbound (AI API calls)
- Security groups: minimal necessary ports
- VPC Flow Logs enabled

## Auto-Scaling Strategy

### Application Auto-Scaling (ECS)

```yaml
# Target tracking policies
API Gateway:
  metric: ALBRequestCountPerTarget
  target: 1000 requests/minute
  scale_in_cooldown: 300s
  scale_out_cooldown: 60s

Execution Workers:
  metric: Custom (RabbitMQ queue depth)
  target: 10 messages per worker
  scale_in_cooldown: 600s
  scale_out_cooldown: 30s
```

### Database Auto-Scaling
- Storage auto-scaling enabled
- Read replica scaling based on CPU (>70% → add replica)

## Secrets Management

- **AWS Secrets Manager**: Database credentials, JWT secret, AI API keys
- **SSM Parameter Store**: Non-sensitive configuration
- **ECS Task Role**: IAM role per service (least privilege)
- **No secrets in environment variables** — injected at runtime from Secrets Manager

## Observability

### Logging
- **CloudWatch Logs**: All container stdout/stderr
- **Structured logging**: JSON format with correlation IDs
- **Log retention**: 30 days hot, archive to S3 Glacier

### Metrics
- **CloudWatch Metrics**: Infrastructure metrics
- **Custom metrics**: Workflow execution times, success rates, queue depths
- **Dashboards**: Per-service health, execution engine metrics

### Tracing
- **AWS X-Ray**: Distributed tracing across services
- **Correlation ID**: Propagated through all service calls

### Alerting
- **CloudWatch Alarms**:
  - 5xx error rate > 1%
  - P99 latency > 5s
  - Queue depth > 100 (sustained 5 min)
  - Database CPU > 80%
  - Disk space < 20%

## CI/CD Pipeline

```
GitHub Push → GitHub Actions → Build & Test → ECR Push → ECS Deploy
                                                              │
                                               ┌──────────────┴──────────┐
                                               │  Blue/Green Deployment  │
                                               │  - Health check gate    │
                                               │  - Auto rollback        │
                                               └─────────────────────────┘
```

## Disaster Recovery

- **RPO**: 1 hour (point-in-time recovery dari RDS)
- **RTO**: 15 minutes (automated failover)
- **Multi-AZ**: Semua critical services
- **Cross-region**: Optional backup replication ke region lain

## Cost Estimate (Monthly)

| Component | Estimated Cost |
|-----------|---------------|
| ECS Fargate | $200-400 |
| RDS PostgreSQL | $150-300 |
| ElastiCache Redis | $100-200 |
| Amazon MQ | $100-150 |
| ALB | $30-50 |
| CloudFront + S3 | $20-50 |
| CloudWatch | $30-50 |
| NAT Gateway | $50-100 |
| **Total** | **$680-1300/month** |

*Scaling up significantly increases compute costs. Reserved Instances reduce by ~30%.
