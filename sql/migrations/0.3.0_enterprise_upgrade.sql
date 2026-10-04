-- PowerDNS-Admin-PHP v0.3.0 Enterprise Upgrade Schema Migration
-- Strictly additive & backward-compatible with v0.2.1 databases.
-- Charset: utf8mb4. Engine: InnoDB.

CREATE TABLE IF NOT EXISTS pdns_servers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL,
  api_url VARCHAR(255) NOT NULL,
  api_key_encrypted TEXT NOT NULL,
  server_id VARCHAR(64) NOT NULL DEFAULT 'localhost',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  latency_ms INT NULL,
  last_check_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pdns_server_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS webhooks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL,
  url VARCHAR(512) NOT NULL,
  secret VARCHAR(128) NOT NULL,
  events VARCHAR(255) NOT NULL DEFAULT 'zone.created,zone.deleted,record.updated',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_status_code INT NULL,
  last_error VARCHAR(255) NULL,
  last_triggered_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dyndns_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(128) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  hostname VARCHAR(255) NOT NULL,
  record_type ENUM('A','AAAA','BOTH') NOT NULL DEFAULT 'BOTH',
  user_id INT UNSIGNED NOT NULL,
  last_ip VARCHAR(64) NULL,
  last_update_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dyndns_token (token_hash),
  KEY idx_dyndns_host (hostname),
  KEY idx_dyndns_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
