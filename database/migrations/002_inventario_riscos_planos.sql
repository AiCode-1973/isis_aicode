-- ÍSIS - Fase 2: inventário de operações de tratamento, riscos, planos de ação e evidências.

-- Hipóteses legais da LGPD (catálogo global). is_sensitive = 1 para as do art. 11 (dados sensíveis).
-- ("SENSITIVE" é palavra reservada no MySQL/MariaDB, por isso o prefixo is_.)
CREATE TABLE legal_bases (
    code VARCHAR(20) NOT NULL,
    article VARCHAR(30) NOT NULL,
    name VARCHAR(160) NOT NULL,
    description VARCHAR(400) NULL,
    is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catálogos da empresa
CREATE TABLE data_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data_categories_tenant_id (tenant_id, id),
    UNIQUE KEY uq_data_categories_name (tenant_id, name),
    CONSTRAINT fk_data_categories_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subject_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    vulnerable TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subject_categories_tenant_id (tenant_id, id),
    UNIQUE KEY uq_subject_categories_name (tenant_id, name),
    CONSTRAINT fk_subject_categories_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE systems (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    hosting ENUM('local','nuvem','terceiro','papel') NOT NULL DEFAULT 'nuvem',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_systems_tenant_id (tenant_id, id),
    UNIQUE KEY uq_systems_name (tenant_id, name),
    CONSTRAINT fk_systems_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Operações de tratamento (registro do art. 37)
CREATE TABLE processing_activities (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    seq INT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    description TEXT NULL,
    purpose TEXT NULL,
    controller_role ENUM('controlador','operador','co-controlador') NOT NULL DEFAULT 'controlador',
    legal_basis_code VARCHAR(20) NULL,
    sensitive_legal_basis_code VARCHAR(20) NULL,
    legal_basis_notes TEXT NULL,
    department_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    data_source ENUM('titular','terceiros','publica','gerado','misto') NULL,
    source_details VARCHAR(400) NULL,
    subjects_volume ENUM('ate_100','ate_1000','ate_10000','ate_100000','acima') NULL,
    retention_period VARCHAR(255) NULL,
    disposal_method VARCHAR(255) NULL,
    international_transfer TINYINT(1) NOT NULL DEFAULT 0,
    international_details VARCHAR(400) NULL,
    security_measures TEXT NULL,
    status ENUM('rascunho','ativa','em_revisao','descontinuada') NOT NULL DEFAULT 'rascunho',
    review_due_date DATE NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activities_tenant_id (tenant_id, id),
    UNIQUE KEY uq_activities_seq (tenant_id, seq),
    KEY ix_activities_status (tenant_id, status),
    CONSTRAINT fk_activities_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    CONSTRAINT fk_activities_basis FOREIGN KEY (legal_basis_code) REFERENCES legal_bases (code),
    CONSTRAINT fk_activities_sbasis FOREIGN KEY (sensitive_legal_basis_code) REFERENCES legal_bases (code),
    CONSTRAINT fk_activities_department FOREIGN KEY (tenant_id, department_id) REFERENCES departments (tenant_id, id),
    CONSTRAINT fk_activities_owner FOREIGN KEY (tenant_id, owner_user_id) REFERENCES users (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_data_categories (
    tenant_id INT UNSIGNED NOT NULL,
    activity_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (tenant_id, activity_id, category_id),
    CONSTRAINT fk_adc_activity FOREIGN KEY (tenant_id, activity_id) REFERENCES processing_activities (tenant_id, id) ON DELETE CASCADE,
    CONSTRAINT fk_adc_category FOREIGN KEY (tenant_id, category_id) REFERENCES data_categories (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_subject_categories (
    tenant_id INT UNSIGNED NOT NULL,
    activity_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (tenant_id, activity_id, category_id),
    CONSTRAINT fk_asc_activity FOREIGN KEY (tenant_id, activity_id) REFERENCES processing_activities (tenant_id, id) ON DELETE CASCADE,
    CONSTRAINT fk_asc_category FOREIGN KEY (tenant_id, category_id) REFERENCES subject_categories (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_systems (
    tenant_id INT UNSIGNED NOT NULL,
    activity_id INT UNSIGNED NOT NULL,
    system_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (tenant_id, activity_id, system_id),
    CONSTRAINT fk_asys_activity FOREIGN KEY (tenant_id, activity_id) REFERENCES processing_activities (tenant_id, id) ON DELETE CASCADE,
    CONSTRAINT fk_asys_system FOREIGN KEY (tenant_id, system_id) REFERENCES systems (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_departments (
    tenant_id INT UNSIGNED NOT NULL,
    activity_id INT UNSIGNED NOT NULL,
    department_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (tenant_id, activity_id, department_id),
    CONSTRAINT fk_adep_activity FOREIGN KEY (tenant_id, activity_id) REFERENCES processing_activities (tenant_id, id) ON DELETE CASCADE,
    CONSTRAINT fk_adep_department FOREIGN KEY (tenant_id, department_id) REFERENCES departments (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compartilhamentos (destinatários). vendor_id será ligado ao módulo de fornecedores na fase 4.
CREATE TABLE activity_sharings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    activity_id INT UNSIGNED NOT NULL,
    recipient VARCHAR(190) NOT NULL,
    recipient_type ENUM('operador','controlador','orgao_publico','grupo_economico','outro') NOT NULL DEFAULT 'operador',
    purpose VARCHAR(400) NULL,
    international TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sharings_tenant_id (tenant_id, id),
    CONSTRAINT fk_sharings_activity FOREIGN KEY (tenant_id, activity_id) REFERENCES processing_activities (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Riscos (probabilidade x impacto, escala 1 a 5)
CREATE TABLE risks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    seq INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    category ENUM('seguranca','conformidade','direitos_titulares','terceiros','retencao','transparencia','outro') NOT NULL DEFAULT 'seguranca',
    activity_id INT UNSIGNED NULL,
    department_id INT UNSIGNED NULL,
    cause TEXT NULL,
    consequence TEXT NULL,
    likelihood TINYINT UNSIGNED NOT NULL,
    impact TINYINT UNSIGNED NOT NULL,
    residual_likelihood TINYINT UNSIGNED NULL,
    residual_impact TINYINT UNSIGNED NULL,
    treatment ENUM('mitigar','aceitar','transferir','evitar') NOT NULL DEFAULT 'mitigar',
    treatment_notes TEXT NULL,
    status ENUM('identificado','em_tratamento','mitigado','aceito','encerrado') NOT NULL DEFAULT 'identificado',
    owner_user_id INT UNSIGNED NULL,
    review_date DATE NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_risks_tenant_id (tenant_id, id),
    UNIQUE KEY uq_risks_seq (tenant_id, seq),
    KEY ix_risks_status (tenant_id, status),
    CONSTRAINT fk_risks_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    CONSTRAINT fk_risks_activity FOREIGN KEY (tenant_id, activity_id) REFERENCES processing_activities (tenant_id, id),
    CONSTRAINT fk_risks_department FOREIGN KEY (tenant_id, department_id) REFERENCES departments (tenant_id, id),
    CONSTRAINT fk_risks_owner FOREIGN KEY (tenant_id, owner_user_id) REFERENCES users (tenant_id, id),
    CONSTRAINT ck_risks_scale CHECK (likelihood BETWEEN 1 AND 5 AND impact BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ações dos planos de ação (medidas mitigadoras e demais tarefas de conformidade)
CREATE TABLE actions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    seq INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    risk_id INT UNSIGNED NULL,
    activity_id INT UNSIGNED NULL,
    department_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    priority ENUM('baixa','media','alta') NOT NULL DEFAULT 'media',
    status ENUM('pendente','em_andamento','concluida','cancelada') NOT NULL DEFAULT 'pendente',
    due_date DATE NULL,
    completed_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_actions_tenant_id (tenant_id, id),
    UNIQUE KEY uq_actions_seq (tenant_id, seq),
    KEY ix_actions_due (tenant_id, status, due_date),
    KEY ix_actions_owner (tenant_id, owner_user_id),
    CONSTRAINT fk_actions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    CONSTRAINT fk_actions_risk FOREIGN KEY (tenant_id, risk_id) REFERENCES risks (tenant_id, id),
    CONSTRAINT fk_actions_activity FOREIGN KEY (tenant_id, activity_id) REFERENCES processing_activities (tenant_id, id),
    CONSTRAINT fk_actions_department FOREIGN KEY (tenant_id, department_id) REFERENCES departments (tenant_id, id),
    CONSTRAINT fk_actions_owner FOREIGN KEY (tenant_id, owner_user_id) REFERENCES users (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE action_updates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    action_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    status_from VARCHAR(20) NULL,
    status_to VARCHAR(20) NULL,
    note TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_action_updates (tenant_id, action_id),
    CONSTRAINT fk_action_updates_action FOREIGN KEY (tenant_id, action_id) REFERENCES actions (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evidências (anexos) de qualquer módulo. Arquivos ficam em storage/uploads, fora da raiz pública.
CREATE TABLE attachments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    stored_name CHAR(40) NOT NULL,
    mime VARCHAR(100) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    description VARCHAR(255) NULL,
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_attachments_stored (stored_name),
    KEY ix_attachments_entity (tenant_id, entity_type, entity_id),
    CONSTRAINT fk_attachments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
