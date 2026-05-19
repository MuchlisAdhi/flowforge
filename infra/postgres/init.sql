-- FlowForge PostgreSQL Initialization
-- This script runs on first container start

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Create schemas for logical separation (all in same DB for MVP)
-- In production, consider separate databases per service

-- Grant permissions
GRANT ALL PRIVILEGES ON DATABASE flowforge TO flowforge;
