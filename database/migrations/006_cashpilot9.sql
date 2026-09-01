-- ============================================================
-- CashPilot 9 - evolução PF, username, estoque e Copiloto
-- Execute UMA VEZ depois das migrations anteriores.
-- ============================================================
USE cashpilot;

ALTER TABLE usuarios
    ADD COLUMN username VARCHAR(24) NULL AFTER nome,
    ADD COLUMN username_alterado_em DATETIME NULL AFTER username,
    ADD UNIQUE KEY uq_usuarios_username (username);

ALTER TABLE produtos_servicos
    ADD COLUMN estoque_atual INT NOT NULL DEFAULT 0 AFTER custo_unitario,
    ADD COLUMN estoque_minimo INT NOT NULL DEFAULT 0 AFTER estoque_atual,
    ADD COLUMN controlar_estoque TINYINT(1) NOT NULL DEFAULT 0 AFTER estoque_minimo;

CREATE TABLE IF NOT EXISTS recorrencias_pf (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(140) NOT NULL,
    categoria_id INT NULL,
    valor DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tipo ENUM('despesa','assinatura') NOT NULL DEFAULT 'despesa',
    periodicidade ENUM('semanal','quinzenal','mensal','anual','outro') NOT NULL DEFAULT 'mensal',
    intervalo_dias SMALLINT UNSIGNED NULL,
    dia_vencimento TINYINT UNSIGNED NOT NULL DEFAULT 10,
    proxima_data DATE NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    INDEX idx_rec_pf_usuario (usuario_id, ativo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS planejamento_mensal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    competencia CHAR(7) NOT NULL,
    receita_esperada DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    gastos_fixos_estimados DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    valor_metas DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    observacao VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_planejamento_usuario_comp (usuario_id, competencia)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS financiamentos_simulados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(140) NOT NULL,
    valor_bem DECIMAL(12,2) NOT NULL,
    entrada DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    taxa_mensal DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    parcelas INT NOT NULL,
    valor_financiado DECIMAL(12,2) NOT NULL,
    valor_parcela DECIMAL(12,2) NOT NULL,
    total_pago DECIMAL(12,2) NOT NULL,
    total_juros DECIMAL(12,2) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_financiamento_usuario (usuario_id, criado_em)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS copiloto_conversas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(120) NOT NULL DEFAULT 'Nova conversa',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_copiloto_conversa_usuario (usuario_id, atualizado_em)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS copiloto_mensagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversa_id INT NOT NULL,
    usuario_id INT NOT NULL,
    papel ENUM('usuario','assistente') NOT NULL,
    mensagem TEXT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (conversa_id) REFERENCES copiloto_conversas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_copiloto_mensagem_conversa (conversa_id, id)
) ENGINE=InnoDB;

-- Permite rastrear recorrências PF geradas automaticamente em despesas.
ALTER TABLE despesas
    MODIFY origem_tipo VARCHAR(32) NOT NULL DEFAULT 'manual';

-- Gera usernames iniciais válidos para contas antigas.
-- Eles podem ser alterados imediatamente no Perfil.
UPDATE usuarios
SET username = CONCAT('user', id),
    username_alterado_em = NULL
WHERE username IS NULL OR username = '';
