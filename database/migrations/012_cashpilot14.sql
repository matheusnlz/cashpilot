-- ============================================================
-- CashPilot 14 - investimentos, planejamento mensal avançado
-- e integração patrimonial / projeção de caixa.
--
-- Execute UMA VEZ após a migration 011.
-- ============================================================

USE cashpilot;

CREATE TABLE IF NOT EXISTS investimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    meta_id INT NULL,
    nome VARCHAR(140) NOT NULL,
    classe ENUM(
        'renda_fixa',
        'tesouro',
        'acoes',
        'fiis',
        'etfs',
        'fundos',
        'cripto',
        'poupanca',
        'outros'
    ) NOT NULL DEFAULT 'outros',
    subtipo VARCHAR(100) NULL,
    instituicao VARCHAR(120) NULL,
    quantidade DECIMAL(18,8) NULL,
    preco_medio DECIMAL(14,4) NULL,
    valor_aplicado DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    valor_atual DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    data_inicio DATE NULL,
    observacao VARCHAR(500) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (meta_id) REFERENCES metas(id) ON DELETE SET NULL,
    INDEX idx_investimentos_usuario (usuario_id, ativo, classe),
    INDEX idx_investimentos_meta (meta_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS investimento_movimentacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    investimento_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo ENUM('aporte','retirada','ajuste') NOT NULL,
    valor DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    quantidade DECIMAL(18,8) NULL,
    observacao VARCHAR(255) NULL,
    data_movimentacao DATE NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (investimento_id)
        REFERENCES investimentos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_invest_mov_usuario_data
        (usuario_id, data_movimentacao),
    INDEX idx_invest_mov_ativo
        (investimento_id, data_movimentacao)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS planejamento_categoria_mensal (
    usuario_id INT NOT NULL,
    competencia CHAR(7) NOT NULL,
    categoria_id INT NOT NULL,
    valor_limite DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, competencia, categoria_id),
    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id)
        REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS planejamento_destino_mensal (
    usuario_id INT NOT NULL,
    competencia CHAR(7) NOT NULL,
    tipo ENUM('investimentos','reserva') NOT NULL,
    valor_planejado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, competencia, tipo),
    FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;
