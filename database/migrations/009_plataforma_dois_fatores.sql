-- ÍSIS 1.6: verificação em duas etapas obrigatória para a equipe AiCode no painel da plataforma.
-- Mesmo esquema das empresas: segredo criptografado (contexto "platform_admin:<id>") e códigos de recuperação só como hash.

ALTER TABLE platform_admins
    ADD COLUMN mfa_secret_enc VARCHAR(255) NULL AFTER status,
    ADD COLUMN mfa_enabled_at DATETIME NULL AFTER mfa_secret_enc,
    ADD COLUMN mfa_last_step BIGINT UNSIGNED NULL AFTER mfa_enabled_at;

CREATE TABLE platform_admin_recovery_codes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id INT UNSIGNED NOT NULL,
    code_hash CHAR(64) NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_platform_recovery_admin (admin_id),
    CONSTRAINT fk_platform_recovery_admin FOREIGN KEY (admin_id) REFERENCES platform_admins (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
