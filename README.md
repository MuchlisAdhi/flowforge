# FlowForge — Real-Time Multi-Tenant Workflow Orchestration Engine

[![CI](https://github.com/your-org/flowforge/actions/workflows/ci.yml/badge.svg)](https://github.com/your-org/flowforge/actions/workflows/ci.yml)

## Overview

FlowForge adalah platform workflow orchestration yang memungkinkan tim mendefinisikan, mengeksekusi, memonitor, dan berkolaborasi pada automated workflows secara real-time. Platform ini dirancang sebagai microservices architecture dalam monorepo yang mudah dijalankan secara lokal.

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         NGINX (Reverse Proxy)                    │
│                         Port 80 → routing                        │
└──────────┬──────────────────┬───────────────────┬───────────────┘
           │                  │                   │
    ┌──────▼──────┐   ┌──────▼──────┐    ┌──────▼──────┐
    │ API Gateway │   │  Frontend   │    │  WebSocket  │
    │  Port 8000  │   │  Port 5173  │    │    /SSE     │
    └──────┬──────┘   └─────────────┘    └─────────────┘
           │
    ┌──────▼──────────────────────────────────────────┐
    │              Internal Services                    │
    ├─────────────┬──────────────┬───────────────────┤
    │  Identity   │  Workflow    │   Execution        │
    │  Service    │  Service     │   Service          │
    │  Port 8001  │  Port 8002  │   Port 8003        │
    ├─────────────┴──────────────┴───────────────────┤
    │              AI Service (Port 8004)              │
    └─────────────────────────────────────────────────┘
           │              │              │
    ┌──────▼──────┐ ┌────▼────┐ ┌──────▼──────┐
    │ PostgreSQL  │ │  Redis  │ │  RabbitMQ   │
    │  Port 5432  │ │  6379   │ │    5672     │
    └─────────────┘ └─────────┘ └─────────────┘
```

### Services

| Service | Responsibility |
|---------|---------------|
| **api-gateway** | Entrypoint REST API, rate limiting, routing, auth forwarding |
| **identity-service** | Tenant management, user auth (JWT), RBAC |
| **workflow-service** | Workflow CRUD, versioning, DAG validation |
| **execution-service** | Workflow runs, DAG execution, retry, logs, event publishing |
| **ai-service** | Natural language workflow builder, failure analysis |
| **frontend** | Vue.js 3 dashboard dengan real-time monitoring |

### Tech Stack

- **Backend**: Laravel 12, PHP 8.3
- **Frontend**: Vue.js 3 + TypeScript + Vite + Tailwind CSS
- **Database**: PostgreSQL 16
- **Cache/Pub-Sub**: Redis 7
- **Message Broker**: RabbitMQ 3.13
- **Containerization**: Docker + docker-compose
- **Testing**: Pest (PHP), Vitest (Frontend), Playwright (E2E)
- **AI**: Groq API (configurable via ENV)

## Quick Start

### Prerequisites

- Docker & Docker Compose v2
- Git

### Setup

```bash
# Clone repository
git clone https://github.com/your-org/flowforge.git
cd flowforge

# Copy environment files
cp .env.example .env

# Start all services
docker compose up -d

# Run migrations (first time)
docker compose exec identity-service php artisan migrate --seed
docker compose exec workflow-service php artisan migrate --seed
docker compose exec execution-service php artisan migrate --seed

# Access the application
# Frontend: http://localhost:8080
# API: http://localhost:8080/api
# RabbitMQ Management: http://localhost:15672
```

### Default Credentials

```
Admin: admin@flowforge.local / password
Editor: editor@flowforge.local / password
Viewer: viewer@flowforge.local / password
```

## Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `DB_HOST` | PostgreSQL host | `postgres` |
| `DB_PORT` | PostgreSQL port | `5432` |
| `DB_DATABASE` | Database name | `flowforge` |
| `DB_USERNAME` | Database user | `flowforge` |
| `DB_PASSWORD` | Database password | `secret` |
| `REDIS_HOST` | Redis host | `redis` |
| `RABBITMQ_HOST` | RabbitMQ host | `rabbitmq` |
| `JWT_SECRET` | JWT signing secret | (generate) |
| `AI_PROVIDER` | AI provider (groq/mock) | `mock` |
| `AI_BASE_URL` | AI API base URL | `https://api.groq.com/openai/v1` |
| `AI_API_KEY` | AI API key | (empty) |
| `AI_MODEL` | AI model name | `llama-3.3-70b-versatile` |

## Running Tests

```bash
# Backend unit & integration tests
docker compose exec identity-service php artisan test
docker compose exec workflow-service php artisan test
docker compose exec execution-service php artisan test

# Frontend tests
docker compose exec frontend npm run test

# E2E tests
docker compose exec frontend npx playwright test
```

## API Examples

### Login
```bash
curl -X POST http://localhost/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "admin@flowforge.local", "password": "password"}'
```

### Create Workflow
```bash
curl -X POST http://localhost/api/workflows \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Daily Student Sync",
    "description": "Sync student data every morning",
    "timeout_seconds": 600,
    "steps": [...]
  }'
```

### Trigger Workflow
```bash
curl -X POST http://localhost/api/workflows/{id}/trigger \
  -H "Authorization: Bearer <token>"
```

## Trade-offs

See [docs/TRADE_OFFS.md](docs/TRADE_OFFS.md) for detailed trade-off analysis.

## Improvement Plan

With more time, we would:
1. Implement full event sourcing for workflow runs
2. Add GraphQL endpoint alongside REST
3. Migrate execution logs to ClickHouse for analytics at scale
4. Add distributed tracing (OpenTelemetry)
5. Implement workflow templates marketplace
6. Add approval gates and human-in-the-loop steps
7. WebSocket scaling with Redis cluster
8. Blue/green deployment strategy

## License

MIT
