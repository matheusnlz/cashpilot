-- ============================================================
-- CashPilot 11 - Inteligência financeira, ações e desempenho
-- Execute UMA VEZ após a migration 007 do CashPilot 10.
-- ============================================================
USE cashpilot;

CREATE TABLE IF NOT EXISTS reserva_emergencia (
    usuario_id INT PRIMARY KEY,
    valor_atual DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    meses_objetivo TINYINT UNSIGNED NOT NULL DEFAULT 6,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS desafios_economia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(140) NOT NULL,
    valor_objetivo DECIMAL(12,2) NOT NULL,
    valor_economizado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    status ENUM('ativo','concluido','cancelado') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_desafio_usuario_status (usuario_id, status, data_fim)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS planos_acao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(160) NOT NULL,
    descricao VARCHAR(500) NULL,
    origem VARCHAR(50) NOT NULL DEFAULT 'manual',
    status ENUM('ativo','concluido','arquivado') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_plano_usuario_status (usuario_id, status, atualizado_em)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS plano_acao_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plano_id INT NOT NULL,
    descricao VARCHAR(240) NOT NULL,
    concluido TINYINT(1) NOT NULL DEFAULT 0,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plano_id) REFERENCES planos_acao(id) ON DELETE CASCADE,
    INDEX idx_plano_item (plano_id, ordem, id)
) ENGINE=InnoDB;

ALTER TABLE custos_negocio
    ADD COLUMN natureza ENUM('fixo','variavel') NOT NULL DEFAULT 'fixo' AFTER recorrente;


-- Categoria específica para custos variáveis dos perfis empreendedores já existentes.
INSERT INTO categorias (usuario_id,nome,tipo,cor,padrao)
SELECT u.id,'Custos variáveis','despesa','#7A6A53',1
FROM usuarios u
WHERE u.tipo_perfil='mei'
AND NOT EXISTS (
    SELECT 1 FROM categorias c
    WHERE c.usuario_id=u.id AND c.tipo='despesa' AND c.nome='Custos variáveis'
);
