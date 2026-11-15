-- PowerDNS-Admin-PHP v0.4.0 Hardening & Feature Expansion Migration
-- Strictly additive & backward-compatible with v0.3.0 and prior databases.
-- Charset: utf8mb4. Engine: InnoDB.

-- 1. Cluster nodes PowerDNS daemon version telemetry
ALTER TABLE pdns_servers ADD COLUMN IF NOT EXISTS version VARCHAR(64) NULL AFTER latency_ms;

-- 2. PowerDNS Catalog Zones tracking (RFC 9432)
ALTER TABLE zones ADD COLUMN IF NOT EXISTS catalog VARCHAR(255) NOT NULL DEFAULT '' AFTER serial;
