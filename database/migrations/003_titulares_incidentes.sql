-- ÍSIS - Fase 3: solicitações de titulares (art. 18) e incidentes de segurança (art. 48).
-- Dados pessoais do titular e detalhes de incidentes ficam criptografados (colunas *_enc).

CREATE TABLE dsar_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    seq INT UNSIGNED NOT NULL,
    protocol CHAR(9) NOT NULL,
    right_type VARCHAR(40) NOT NULL,
    channel ENUM('portal','email','telefone','presencial','outro') NOT NULL DEFAULT 'portal',
    requester_relation ENUM('titular','representante') NOT NULL DEFAULT 'titular',
    requester_name_enc TEXT NOT NULL,
    requester_email_enc TEXT NULL,
    requester_email_index CHAR(64) NULL,
    requester_phone_enc TEXT NULL,
    requester_document_enc TEXT NULL,
    description_enc TEXT NULL,
    identity_verified TINYINT(1) NOT NULL DEFAULT 0,
    identity_notes VARCHAR(400) NULL,
    status ENUM('recebida','em_verificacao','em_atendimento','respondida','indeferida','cancelada') NOT NULL DEFAULT 'recebida',
    assigned_user_id INT UNSIGNED NULL,
    activity_id INT UNSIGNED NULL,
    received_at DATETIME NOT NULL,
    due_date DATE NOT NULL,
    response_enc TEXT NULL,
    responded_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dsar_tenant_id (tenant_id, id),
    UNIQUE KEY uq_dsar_seq (tenant_id, seq),
    UNIQUE KEY uq_dsar_protocol (protocol),
    KEY ix_dsar_status (tenant_id, status, due_date),
    KEY ix_dsar_email (tenant_id, requester_email_index),
    CONSTRAINT fk_dsar_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    CONSTRAINT fk_dsar_assigned FOREIGN KEY (tenant_id, assigned_user_id) REFERENCES users (tenant_id, id),
    CONSTRAINT fk_dsar_activity FOREIGN KEY (tenant_id, activity_id) REFERENCES processing_activities (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dsar_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    request_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    event_type ENUM('criada','status','nota','resposta','verificacao','atribuicao') NOT NULL,
    status_from VARCHAR(20) NULL,
    status_to VARCHAR(20) NULL,
    note_enc TEXT NULL,
    visible_to_requester TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_dsar_events (tenant_id, request_id),
    CONSTRAINT fk_dsar_events_request FOREIGN KEY (tenant_id, request_id) REFERENCES dsar_requests (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE incidents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    seq INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    description_enc TEXT NULL,
    category VARCHAR(30) NOT NULL DEFAULT 'outro',
    severity ENUM('baixa','media','alta','critica') NULL,
    status ENUM('comunicado','em_analise','contido','resolvido','encerrado') NOT NULL DEFAULT 'comunicado',
    occurred_at DATETIME NULL,
    detected_at DATETIME NOT NULL,
    personal_data TINYINT(1) NULL,
    sensitive_data TINYINT(1) NOT NULL DEFAULT 0,
    subjects_affected INT UNSIGNED NULL,
    risk_to_subjects ENUM('indeterminado','baixo','relevante') NOT NULL DEFAULT 'indeterminado',
    activity_id INT UNSIGNED NULL,
    department_id INT UNSIGNED NULL,
    owner_user_id INT UNSIGNED NULL,
    reported_by INT UNSIGNED NULL,
    containment_enc TEXT NULL,
    root_cause_enc TEXT NULL,
    lessons_enc TEXT NULL,
    anpd_deadline DATE NULL,
    anpd_notified_at DATETIME NULL,
    anpd_protocol VARCHAR(80) NULL,
    subjects_notified_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_incidents_tenant_id (tenant_id, id),
    UNIQUE KEY uq_incidents_seq (tenant_id, seq),
    KEY ix_incidents_status (tenant_id, status),
    CONSTRAINT fk_incidents_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    CONSTRAINT fk_incidents_activity FOREIGN KEY (tenant_id, activity_id) REFERENCES processing_activities (tenant_id, id),
    CONSTRAINT fk_incidents_department FOREIGN KEY (tenant_id, department_id) REFERENCES departments (tenant_id, id),
    CONSTRAINT fk_incidents_owner FOREIGN KEY (tenant_id, owner_user_id) REFERENCES users (tenant_id, id),
    CONSTRAINT fk_incidents_reporter FOREIGN KEY (tenant_id, reported_by) REFERENCES users (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE incident_events (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    incident_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    event_type ENUM('registro','status','providencia','comunicacao','classificacao','nota') NOT NULL,
    status_from VARCHAR(20) NULL,
    status_to VARCHAR(20) NULL,
    note_enc TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ix_incident_events (tenant_id, incident_id),
    CONSTRAINT fk_incident_events_incident FOREIGN KEY (tenant_id, incident_id) REFERENCES incidents (tenant_id, id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ações de plano também podem nascer de um incidente
ALTER TABLE actions ADD COLUMN incident_id INT UNSIGNED NULL AFTER activity_id;
ALTER TABLE actions ADD CONSTRAINT fk_actions_incident FOREIGN KEY (tenant_id, incident_id) REFERENCES incidents (tenant_id, id);
