-- CashPilot 17: aplicar uma vez pelo phpMyAdmin, no banco EXISTENTE.
-- Não altera nem apaga lançamentos anteriores. InnoDB é obrigatório.
CREATE TABLE IF NOT EXISTS cp_operacoes (
    usuario_id INT NOT NULL,
    chave CHAR(64) NOT NULL,
    contexto VARCHAR(40) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, chave),
    CONSTRAINT fk_cp17_operacao_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_limites (
    chave CHAR(64) NOT NULL PRIMARY KEY,
    quantidade INT UNSIGNED NOT NULL DEFAULT 0,
    expira_em BIGINT NOT NULL,
    INDEX idx_cp17_limite_expira (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cp_privacidade (
    usuario_id INT NOT NULL PRIMARY KEY,
    versao_aviso VARCHAR(20) NOT NULL,
    ia_externa TINYINT(1) NOT NULL DEFAULT 0,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cp17_privacidade_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
