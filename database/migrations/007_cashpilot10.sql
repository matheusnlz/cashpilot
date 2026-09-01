-- ============================================================
-- CashPilot 10 - Aprender, estoque avançado, fornecedor-produto
-- Execute UMA VEZ após a migration 006 do CashPilot 9.
-- ============================================================
USE cashpilot;

ALTER TABLE usuarios
    ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER onboarding_concluido;

ALTER TABLE produtos_servicos
    ADD COLUMN fornecedor_id INT NULL AFTER controlar_estoque,
    ADD INDEX idx_produto_fornecedor (usuario_id, fornecedor_id),
    ADD CONSTRAINT fk_produto_fornecedor
        FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id)
        ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS movimentacoes_estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    produto_id INT NOT NULL,
    fornecedor_id INT NULL,
    tipo ENUM('entrada','venda','ajuste') NOT NULL,
    quantidade INT NOT NULL,
    custo_unitario DECIMAL(12,2) NULL,
    referencia VARCHAR(180) NULL,
    venda_id INT NULL,
    data_movimentacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES produtos_servicos(id) ON DELETE CASCADE,
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL,
    FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE SET NULL,
    INDEX idx_estoque_produto_data (produto_id, data_movimentacao)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS aprender_trilhas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(140) NOT NULL,
    descricao VARCHAR(255) NULL,
    perfil ENUM('pessoa_fisica','mei','ambos') NOT NULL DEFAULT 'ambos',
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS aprender_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(180) NOT NULL,
    descricao VARCHAR(500) NULL,
    youtube_video_id VARCHAR(32) NOT NULL,
    categoria VARCHAR(80) NOT NULL DEFAULT 'Geral',
    perfil ENUM('pessoa_fisica','mei','ambos') NOT NULL DEFAULT 'ambos',
    tags VARCHAR(500) NULL,
    duracao_segundos INT NULL,
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_aprender_youtube (youtube_video_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS aprender_trilha_videos (
    trilha_id INT NOT NULL,
    video_id INT NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    PRIMARY KEY (trilha_id, video_id),
    FOREIGN KEY (trilha_id) REFERENCES aprender_trilhas(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES aprender_videos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS aprender_progresso (
    usuario_id INT NOT NULL,
    video_id INT NOT NULL,
    segundos_assistidos INT NOT NULL DEFAULT 0,
    percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    concluido TINYINT(1) NOT NULL DEFAULT 0,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, video_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES aprender_videos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Para habilitar o gerenciador de vídeos em uma conta específica:
-- UPDATE usuarios SET is_admin = 1 WHERE username = 'seu_usuario';
