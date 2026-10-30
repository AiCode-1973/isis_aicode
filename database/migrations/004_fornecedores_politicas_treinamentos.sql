-- ÍSIS - Fase 4: fornecedores, políticas e documentos, treinamentos com certificado.

CREATE TABLE vendors (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    seq INT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    cnpj VARCHAR(14) NULL,
    service VARCHAR(190) NULL,
    role ENUM('operador','controlador','co_controlador','outro') NOT NULL DEFAULT 'operador',
    data_access TINYINT(1) NOT NULL DEFAULT 1,
    sensitive_data TINYINT(1) NOT NULL DEFAULT 0,
    international TINYINT(1) NOT NULL DEFAULT 0,
    country VARCHAR(80) NULL,
    contact_name VARCHAR(150) NULL,
    contact_email VARCHAR(190) NULL,
    contact_phone VARCHAR(40) NULL,
    status ENUM('em_avaliacao','ativo','suspenso','encerrado') NOT NULL DEFAULT 'em_avaliacao',
    risk_level ENUM('baixo','medio','alto') NULL,
    last_score TINYINT UNSIGNED NULL,
    last_assessment_at DATETIME NULL,
    next_review_date DATE NULL,
    owner_user_id INT UNSIGNED NULL,
    department_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vendors_tenant_id (tenant_id, id),
    UNIQUE KEY uq_vendors_seq (tenant_id, seq),
    KEY ix_vendors_status (tenant_id, status),
    CONSTRAINT fk_vendors_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    CONSTRAINT fk_vendors_owner FOREIGN KEY (tenant_id, owner_user_id) REFERENCES users (tenant_id, id),
    CONSTRAINT fk_vendors_department FOREIGN KEY (tenant_id, department_id) REFERENCES departments (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vendor_activities (
    tenant_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED NOT NULL,
    activity_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (tenant_id, vendor_id, activity_id),
    CONSTRAINT fk_va_vendor FOREIGN KEY (tenant_id, vendor_id) REFERENCES vendors (tenant_id, id) ON DELETE CASCADE,
    CONSTRAINT fk_va_activity FOREIGN KEY (tenant_id, activity_id) REFERENCES processing_activities (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vendor_assessments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED NOT NULL,
    answers LONGTEXT NOT NULL,
    score TINYINT UNSIGNED NOT NULL,
    risk_level ENUM('baixo','medio','alto') NOT NULL,
    notes TEXT NULL,
    assessed_by INT UNSIGNED NULL,
    assessed_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY ix_vendor_assessments (tenant_id, vendor_id),
    CONSTRAINT fk_vass_vendor FOREIGN KEY (tenant_id, vendor_id) REFERENCES vendors (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vendor_contracts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    reference VARCHAR(80) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    has_data_clauses TINYINT(1) NOT NULL DEFAULT 0,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_vendor_contracts_tenant_id (tenant_id, id),
    KEY ix_vendor_contracts (tenant_id, vendor_id),
    CONSTRAINT fk_vcon_vendor FOREIGN KEY (tenant_id, vendor_id) REFERENCES vendors (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE policies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    seq INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'outro',
    owner_user_id INT UNSIGNED NULL,
    review_months TINYINT UNSIGNED NOT NULL DEFAULT 12,
    requires_ack TINYINT(1) NOT NULL DEFAULT 1,
    current_version_id INT UNSIGNED NULL,
    archived_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_policies_tenant_id (tenant_id, id),
    UNIQUE KEY uq_policies_seq (tenant_id, seq),
    CONSTRAINT fk_policies_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    CONSTRAINT fk_policies_owner FOREIGN KEY (tenant_id, owner_user_id) REFERENCES users (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE policy_versions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    policy_id INT UNSIGNED NOT NULL,
    version_number SMALLINT UNSIGNED NOT NULL,
    content LONGTEXT NOT NULL,
    change_summary VARCHAR(400) NULL,
    status ENUM('rascunho','em_aprovacao','aprovada','rejeitada','substituida') NOT NULL DEFAULT 'rascunho',
    created_by INT UNSIGNED NULL,
    submitted_at DATETIME NULL,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    decision_notes VARCHAR(500) NULL,
    valid_until DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_policy_versions_tenant_id (tenant_id, id),
    UNIQUE KEY uq_policy_versions_number (tenant_id, policy_id, version_number),
    CONSTRAINT fk_pv_policy FOREIGN KEY (tenant_id, policy_id) REFERENCES policies (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE policy_acknowledgements (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    version_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    acknowledged_at DATETIME NOT NULL,
    ip VARCHAR(45) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_policy_ack (tenant_id, version_id, user_id),
    CONSTRAINT fk_pack_version FOREIGN KEY (tenant_id, version_id) REFERENCES policy_versions (tenant_id, id) ON DELETE CASCADE,
    CONSTRAINT fk_pack_user FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE training_modules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    seq INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    summary VARCHAR(400) NULL,
    content LONGTEXT NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 20,
    passing_score TINYINT UNSIGNED NOT NULL DEFAULT 70,
    certificate_months TINYINT UNSIGNED NOT NULL DEFAULT 12,
    status ENUM('rascunho','publicado','arquivado') NOT NULL DEFAULT 'rascunho',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_modules_tenant_id (tenant_id, id),
    UNIQUE KEY uq_training_modules_seq (tenant_id, seq),
    CONSTRAINT fk_tm_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE training_questions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    position SMALLINT UNSIGNED NOT NULL,
    question VARCHAR(500) NOT NULL,
    options LONGTEXT NOT NULL,
    correct_index TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY ix_tq_module (tenant_id, module_id, position),
    CONSTRAINT fk_tq_module FOREIGN KEY (tenant_id, module_id) REFERENCES training_modules (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE training_enrollments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NULL,
    assigned_at DATETIME NOT NULL,
    due_date DATE NULL,
    status ENUM('pendente','em_andamento','concluido','reprovado') NOT NULL DEFAULT 'pendente',
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    best_score TINYINT UNSIGNED NULL,
    certificate_code CHAR(14) NULL,
    certificate_valid_until DATE NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_enrollment (tenant_id, module_id, user_id),
    UNIQUE KEY uq_certificate (certificate_code),
    KEY ix_enrollment_user (tenant_id, user_id, status),
    CONSTRAINT fk_te_module FOREIGN KEY (tenant_id, module_id) REFERENCES training_modules (tenant_id, id) ON DELETE CASCADE,
    CONSTRAINT fk_te_user FOREIGN KEY (tenant_id, user_id) REFERENCES users (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
