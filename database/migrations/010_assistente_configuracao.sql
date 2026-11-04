-- ÍSIS 1.7: assistente de configuração inicial em etapas.
-- As respostas (empresa, áreas, dados e responsáveis) ficam em JSON para retomar o roteiro e recalcular o plano sugerido.

ALTER TABLE tenants
    ADD COLUMN onboarding_answers MEDIUMTEXT NULL AFTER onboarding_completed_at;
