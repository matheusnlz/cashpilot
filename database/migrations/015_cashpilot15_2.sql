-- CashPilot 15.2 — sessão versionada
ALTER TABLE usuarios
    ADD COLUMN sessao_versao INT UNSIGNED NOT NULL DEFAULT 1 AFTER is_admin;
