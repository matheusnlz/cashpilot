-- CashPilot 16.5 — fila idempotente de eventos da Pluggy
-- Execute uma única vez no mesmo banco de dados atual do CashPilot.

CREATE TABLE IF NOT EXISTS pluggy_webhook_eventos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    item_id VARCHAR(64) NULL,
    evento VARCHAR(100) NOT NULL,
    evento_hash CHAR(64) NOT NULL,
    payload_json JSON NOT NULL,
    status ENUM('pendente','processando','processado','ignorado','erro')
        NOT NULL DEFAULT 'pendente',
    tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
    erro_mensagem VARCHAR(255) NULL,
    recebido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processado_em DATETIME NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pluggy_webhook_hash (evento_hash),
    INDEX idx_pluggy_webhook_fila (status, recebido_em),
    INDEX idx_pluggy_webhook_usuario (usuario_id, status),
    INDEX idx_pluggy_webhook_item (item_id, status),
    CONSTRAINT fk_pluggy_webhook_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

