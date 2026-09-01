-- CashPilot 13.2 — conta, acesso e importação
-- Execute uma única vez depois das migrations anteriores.

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS email_verificado TINYINT(1) NOT NULL DEFAULT 1 AFTER email,
    ADD COLUMN IF NOT EXISTS email_verificado_em DATETIME NULL AFTER email_verificado,
    ADD COLUMN IF NOT EXISTS email_pendente VARCHAR(150) NULL AFTER email_verificado_em;

CREATE TABLE IF NOT EXISTS codigos_verificacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    destino VARCHAR(150) NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL,
    tentativas INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_codigo_usuario_tipo (usuario_id, tipo, usado_em, expira_em)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS usuario_oauth (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    provider VARCHAR(30) NOT NULL,
    provider_user_id VARCHAR(190) NOT NULL,
    email_provider VARCHAR(150) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uk_oauth_provider_user (provider, provider_user_id),
    UNIQUE KEY uk_oauth_usuario_provider (usuario_id, provider)
) ENGINE=InnoDB;
