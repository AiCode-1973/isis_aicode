-- ISIS - Fase 1: núcleo multiempresa, acesso e auditoria
-- Compatível com MySQL 8.0+ e MariaDB 10.4+. Toda tabela institucional tem tenant_id
-- e chaves estrangeiras compostas (tenant_id, id), para o banco impedir vínculos entre empresas.

CREATE TABLE segments (
    code VARCHAR(30) NOT NULL,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    template LONGTEXT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tenants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(80) NOT NULL,
    legal_name VARCHAR(190) NOT NULL,
    trade_name VARCHAR(190) NULL,
    cnpj CHAR(14) NOT NULL,
    segment_code VARCHAR(30) NOT NULL,
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    dpo_name VARCHAR(150) NULL,
    dpo_email VARCHAR(190) NULL,
    privacy_email VARCHAR(190) NULL,
    onboarding_completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tenants_slug (slug),
    UNIQUE KEY uq_tenants_cnpj (cnpj),
    CONSTRAINT fk_tenants_segment FOREIGN KEY (segment_code) REFERENCES segments (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE departments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_departments_tenant_id (tenant_id, id),
    UNIQUE KEY uq_departments_name (tenant_id, name),
    CONSTRAINT fk_departments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','encarregado','gestor','colaborador','auditor') NOT NULL DEFAULT 'colaborador',
    department_id INT UNSIGNED NULL,
    job_title VARCHAR(120) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    last_login_at DATETIME NULL,
    session_epoch INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_tenant_id (tenant_id, id),
    KEY ix_users_tenant_role (tenant_id, role, status),
    CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    CONSTRAINT fk_users_department FOREIGN KEY (tenant_id, department_id) REFERENCES departments (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invitations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    email VARCHAR(190) NOT NULL,
    name VARCHAR(150) NOT NULL,
    role ENUM('admin','encarregado','gestor','colaborador','auditor') NOT NULL,
    department_id INT UNSIGNED NULL,
    token_hash CHAR(64) NOT NULL,
    invited_by INT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invitations_token (token_hash),
    KEY ix_invitations_tenant_email (tenant_id, email),
    CONSTRAINT fk_invitations_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    CONSTRAINT fk_invitations_department FOREIGN KEY (tenant_id, department_id) REFERENCES departments (tenant_id, id),
    CONSTRAINT fk_invitations_inviter FOREIGN KEY (tenant_id, invited_by) REFERENCES users (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('verify_email','password_reset') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_tokens_hash (token_hash),
    KEY ix_user_tokens_user (user_id, type),
    CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limit_hits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket VARCHAR(30) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_rate_limit (bucket, key_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tenant_settings (
    tenant_id INT UNSIGNED NOT NULL,
    setting_key VARCHAR(60) NOT NULL,
    setting_value TEXT NULL,
    PRIMARY KEY (tenant_id, setting_key),
    CONSTRAINT fk_tenant_settings_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trilha de auditoria: somente inclusão. Cada registro guarda o hash do anterior (cadeia por empresa).
CREATE TABLE audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    user_name VARCHAR(150) NULL,
    action VARCHAR(60) NOT NULL,
    entity_type VARCHAR(60) NULL,
    entity_id BIGINT UNSIGNED NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    changes LONGTEXT NULL,
    created_at DATETIME(6) NOT NULL,
    prev_hash CHAR(64) NOT NULL,
    hash CHAR(64) NOT NULL,
    PRIMARY KEY (id),
    KEY ix_audit_tenant_date (tenant_id, created_at),
    KEY ix_audit_tenant_action (tenant_id, action),
    KEY ix_audit_tenant_user (tenant_id, user_id),
    CONSTRAINT fk_audit_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_chain (
    chain_key INT UNSIGNED NOT NULL,
    last_hash CHAR(64) NOT NULL,
    PRIMARY KEY (chain_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
