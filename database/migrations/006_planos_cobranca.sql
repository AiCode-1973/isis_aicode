-- ÍSIS - Planos, assinaturas, faturas (cobrança manual) e administradores da plataforma (AiCode).

CREATE TABLE plans (
    code VARCHAR(30) NOT NULL,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(300) NULL,
    price_monthly_cents INT UNSIGNED NULL,
    price_yearly_cents INT UNSIGNED NULL,
    max_users SMALLINT UNSIGNED NULL,
    max_vendors SMALLINT UNSIGNED NULL,
    max_trainings SMALLINT UNSIGNED NULL,
    contact_only TINYINT(1) NOT NULL DEFAULT 0,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    highlights LONGTEXT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    updated_at DATETIME NULL,
    PRIMARY KEY (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO plans (code, name, description, price_monthly_cents, price_yearly_cents, max_users, max_vendors, max_trainings, contact_only, highlights, sort_order) VALUES
('essencial', 'Essencial', 'Para micro e pequenas empresas organizarem a LGPD do zero.', 19900, 199000, 5, 10, 2, 0,
 '["Até 5 usuários","Todos os módulos de conformidade","Canal on-line do titular e incidentes","Até 10 fornecedores e 2 treinamentos","Relatório de conformidade"]', 1),
('profissional', 'Profissional', 'Para pequenas e médias empresas com equipe envolvida na privacidade.', 54900, 549000, 25, NULL, NULL, 0,
 '["Até 25 usuários","Fornecedores e treinamentos ilimitados","Lembretes diários por e-mail","Exportações em planilha","Suporte por e-mail"]', 2),
('empresarial', 'Empresarial', 'Para empresas maiores, com várias unidades ou necessidades específicas.', NULL, NULL, NULL, NULL, NULL, 1,
 '["Usuários ilimitados","Tudo do Profissional","Suporte prioritário","Implantação assistida sob consulta","Condições comerciais personalizadas"]', 3);

CREATE TABLE subscriptions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    plan_code VARCHAR(30) NOT NULL,
    billing_cycle ENUM('mensal','anual') NOT NULL DEFAULT 'mensal',
    status ENUM('teste','ativa','suspensa','cancelada') NOT NULL DEFAULT 'teste',
    trial_ends_at DATE NULL,
    current_period_start DATE NULL,
    current_period_end DATE NULL,
    custom_price_cents INT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    requested_plan_code VARCHAR(30) NULL,
    requested_cycle ENUM('mensal','anual') NULL,
    requested_message VARCHAR(500) NULL,
    requested_at DATETIME NULL,
    last_notice VARCHAR(60) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_subscriptions_tenant (tenant_id),
    UNIQUE KEY uq_subscriptions_tenant_id (tenant_id, id),
    CONSTRAINT fk_subscriptions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id),
    CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_code) REFERENCES plans (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NOT NULL,
    number VARCHAR(20) NOT NULL,
    description VARCHAR(200) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    amount_cents INT UNSIGNED NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('aberta','paga','cancelada') NOT NULL DEFAULT 'aberta',
    paid_at DATETIME NULL,
    payment_method VARCHAR(30) NULL,
    payment_reference VARCHAR(120) NULL,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_invoices_number (number),
    KEY ix_invoices_tenant (tenant_id, status, due_date),
    CONSTRAINT fk_invoices_subscription FOREIGN KEY (tenant_id, subscription_id) REFERENCES subscriptions (tenant_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_admins (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_platform_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE platform_settings (
    name VARCHAR(60) NOT NULL,
    value TEXT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_settings (name, value) VALUES
('payment_instructions', 'Pagamento por Pix ou boleto. Após o pagamento, envie o comprovante para o e-mail de cobrança informando o número da fatura.'),
('billing_email', NULL);

-- Empresas já existentes começam com 30 dias de teste do plano Profissional.
INSERT INTO subscriptions (tenant_id, plan_code, billing_cycle, status, trial_ends_at, created_at)
SELECT id, 'profissional', 'mensal', 'teste', CURDATE() + INTERVAL 30 DAY, NOW() FROM tenants;
