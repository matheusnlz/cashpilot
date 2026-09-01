-- ============================================================
-- CashPilot 11.1 - Preferências, onboarding e personalização
-- Execute UMA VEZ após a migration 008 do CashPilot 11.
-- ============================================================

USE cashpilot;

ALTER TABLE usuarios
    ADD COLUMN tema_preferido ENUM('light','dark') NOT NULL DEFAULT 'light' AFTER is_admin,
    ADD COLUMN objetivo_pessoal VARCHAR(80) NULL AFTER nicho;

CREATE TABLE IF NOT EXISTS usuario_apresentacoes (
    usuario_id INT NOT NULL,
    chave VARCHAR(80) NOT NULL,
    visto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, chave),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE aprender_videos
    ADD COLUMN nichos VARCHAR(500) NULL AFTER tags,
    ADD COLUMN objetivos VARCHAR(500) NULL AFTER nichos;

ALTER TABLE perfil_negocio
    MODIFY COLUMN operacao ENUM('presencial','online','hibrido','domicilio') NOT NULL DEFAULT 'presencial';
