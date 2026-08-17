-- CashPilot 5 — perfil pessoal/empresarial e gestão do negócio
-- Execute este arquivo UMA VEZ no phpMyAdmin, no banco cashpilot.

ALTER TABLE usuarios
    ADD COLUMN telefone VARCHAR(30) NULL AFTER email,
    ADD COLUMN onboarding_concluido TINYINT(1) NOT NULL DEFAULT 1 AFTER avatar_path,
    ADD COLUMN limite_gastos_mensal DECIMAL(12,2) NULL AFTER renda_mensal;

CREATE TABLE IF NOT EXISTS perfil_negocio (
    usuario_id INT PRIMARY KEY,
    nome_negocio VARCHAR(150) NULL,
    oferta ENUM('produtos','servicos','ambos') NOT NULL DEFAULT 'servicos',
    operacao ENUM('presencial','online','hibrido') NOT NULL DEFAULT 'presencial',
    publico_alvo VARCHAR(150) NULL,
    canal_principal VARCHAR(100) NULL,
    objetivo_principal VARCHAR(100) NULL,
    observacoes VARCHAR(255) NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_perfil_negocio_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS produtos_servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(140) NOT NULL,
    tipo ENUM('produto','servico') NOT NULL,
    preco_venda DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    custo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_produto_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_produto_usuario (usuario_id, ativo)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(140) NOT NULL,
    cargo VARCHAR(100) NULL,
    salario_base DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    outros_custos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_funcionario_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_funcionario_usuario (usuario_id, ativo)
) ENGINE=InnoDB;
