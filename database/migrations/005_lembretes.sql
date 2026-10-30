-- ÍSIS - Fase 5: controle dos lembretes diários por e-mail (no máximo um por pessoa por dia).

CREATE TABLE reminder_log (
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    sent_on DATE NOT NULL,
    items SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id, user_id, sent_on),
    CONSTRAINT fk_reminder_user FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
