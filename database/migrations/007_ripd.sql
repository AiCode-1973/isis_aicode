-- ÍSIS 1.2: Relatório de Impacto à Proteção de Dados Pessoais (RIPD), art. 5º, XVII, e art. 38 da LGPD.
-- O RIPD cobre uma ou mais operações do inventário e usa os riscos já registrados para elas.
-- Cada aprovação guarda uma cópia fechada (snapshot) do relatório, com hash, para comprovar o que foi aprovado.

CREATE TABLE dpias (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    seq INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('rascunho','em_aprovacao','aprovado') NOT NULL DEFAULT 'rascunho',
    triggers TEXT NULL,
    need_justification TEXT NULL,
    nature TEXT NULL,
    scope TEXT NULL,
    context TEXT NULL,
    purpose TEXT NULL,
    stakeholders TEXT NULL,
    necessity TEXT NULL,
    safeguards TEXT NULL,
    conclusion ENUM('aceitavel','aceitavel_com_medidas','nao_aceitavel') NULL,
    conclusion_notes TEXT NULL,
    dpo_opinion TEXT NULL,
    owner_user_id INT UNSIGNED NULL,
    review_due_date DATE NULL,
    submitted_at DATETIME NULL,
    returned_notes VARCHAR(500) NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    approval_notes VARCHAR(500) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dpias_tenant_id (tenant_id, id),
    UNIQUE KEY uq_dpias_seq (tenant_id, seq),
    KEY ix_dpias_status (tenant_id, status),
    CONSTRAINT fk_dpias_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    CONSTRAINT fk_dpias_owner FOREIGN KEY (tenant_id, owner_user_id) REFERENCES users (tenant_id, id),
    CONSTRAINT fk_dpias_approver FOREIGN KEY (tenant_id, approved_by) REFERENCES users (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dpia_activities (
    tenant_id INT UNSIGNED NOT NULL,
    dpia_id INT UNSIGNED NOT NULL,
    activity_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (tenant_id, dpia_id, activity_id),
    KEY ix_dpia_activities_activity (tenant_id, activity_id),
    CONSTRAINT fk_dpia_act_dpia FOREIGN KEY (tenant_id, dpia_id) REFERENCES dpias (tenant_id, id) ON DELETE CASCADE,
    CONSTRAINT fk_dpia_act_activity FOREIGN KEY (tenant_id, activity_id) REFERENCES processing_activities (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Versões aprovadas: conteúdo completo do relatório no momento da aprovação (não muda depois).
CREATE TABLE dpia_versions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    dpia_id INT UNSIGNED NOT NULL,
    version SMALLINT UNSIGNED NOT NULL,
    snapshot LONGTEXT NOT NULL,
    sha256 CHAR(64) NOT NULL,
    approved_by INT UNSIGNED NULL,
    approved_by_name VARCHAR(150) NOT NULL,
    approved_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dpia_versions (tenant_id, dpia_id, version),
    CONSTRAINT fk_dpia_versions_dpia FOREIGN KEY (tenant_id, dpia_id) REFERENCES dpias (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
