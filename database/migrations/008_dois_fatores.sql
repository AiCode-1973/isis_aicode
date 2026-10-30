-- ÍSIS 1.3: verificação em duas etapas (TOTP, RFC 6238) com códigos de recuperação.
-- O segredo fica criptografado (Crypto, contexto "user:<id>"); os códigos de recuperação, só como hash.

ALTER TABLE users
    ADD COLUMN mfa_secret_enc VARCHAR(255) NULL AFTER session_epoch,
    ADD COLUMN mfa_enabled_at DATETIME NULL AFTER mfa_secret_enc,
    ADD COLUMN mfa_last_step BIGINT UNSIGNED NULL AFTER mfa_enabled_at;

-- Política da empresa: opcional, obrigatória para administradores e encarregados, ou para todos.
ALTER TABLE tenants
    ADD COLUMN mfa_policy ENUM('opcional','gestao','todos') NOT NULL DEFAULT 'opcional' AFTER privacy_email;

CREATE TABLE user_recovery_codes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    code_hash CHAR(64) NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_recovery_user (tenant_id, user_id),
    CONSTRAINT fk_recovery_user FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
