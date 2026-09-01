-- CashPilot 7 - integração financeira PF e Empreendedor
-- Execute UMA VEZ no phpMyAdmin antes de usar as novas funções.
USE cashpilot;

ALTER TABLE despesas
    ADD COLUMN origem_tipo ENUM('manual','funcionario','fornecedor','custo_fixo') NOT NULL DEFAULT 'manual' AFTER status,
    ADD COLUMN origem_id INT NULL AFTER origem_tipo,
    ADD COLUMN competencia CHAR(7) NULL AFTER origem_id,
    ADD INDEX idx_despesas_origem (usuario_id, origem_tipo, origem_id, competencia);

ALTER TABLE receitas
    ADD COLUMN venda_id INT NULL AFTER importacao_id;

ALTER TABLE funcionarios
    ADD COLUMN dia_pagamento TINYINT UNSIGNED NOT NULL DEFAULT 5 AFTER outros_custos;

CREATE TABLE IF NOT EXISTS fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(140) NOT NULL,
    descricao VARCHAR(180) NULL,
    valor_padrao DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    recorrente TINYINT(1) NOT NULL DEFAULT 0,
    dia_vencimento TINYINT UNSIGNED NOT NULL DEFAULT 10,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS custos_negocio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    descricao VARCHAR(160) NOT NULL,
    valor DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    recorrente TINYINT(1) NOT NULL DEFAULT 1,
    dia_vencimento TINYINT UNSIGNED NOT NULL DEFAULT 10,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vendas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    receita_id INT NULL,
    data_venda DATE NOT NULL,
    valor_bruto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    custo_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    observacao VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (receita_id) REFERENCES receitas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS venda_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venda_id INT NOT NULL,
    produto_servico_id INT NULL,
    nome_item VARCHAR(140) NOT NULL,
    tipo ENUM('produto','servico') NOT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    preco_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    custo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_servico_id) REFERENCES produtos_servicos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE receitas
    ADD CONSTRAINT fk_receitas_venda FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS meta_movimentacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    meta_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo ENUM('aporte','retirada') NOT NULL,
    valor DECIMAL(12,2) NOT NULL,
    observacao VARCHAR(180) NULL,
    data_movimentacao DATE NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meta_id) REFERENCES metas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_meta_mov_data (meta_id, data_movimentacao)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS classificacao_regras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    termo VARCHAR(120) NOT NULL,
    tipo ENUM('receita','despesa') NOT NULL,
    categoria_id INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_regra_usuario_termo_tipo (usuario_id, termo, tipo),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Categorias empresariais complementares para contas MEI existentes.
INSERT INTO categorias (usuario_id,nome,tipo,cor,padrao)
SELECT u.id, x.nome, x.tipo, x.cor, 1
FROM usuarios u
JOIN (
    SELECT 'Vendas' nome,'receita' tipo,'#3B6E71' cor UNION ALL
    SELECT 'Serviços','receita','#4B5945' UNION ALL
    SELECT 'Outras entradas','receita','#8A8A8A' UNION ALL
    SELECT 'Funcionários','despesa','#7A3B3B' UNION ALL
    SELECT 'Fornecedores','despesa','#6B7280' UNION ALL
    SELECT 'Estoque e insumos','despesa','#8A7B5A' UNION ALL
    SELECT 'Aluguel comercial','despesa','#4B5563' UNION ALL
    SELECT 'Marketing','despesa','#3B6E71' UNION ALL
    SELECT 'Impostos','despesa','#3F3F3F' UNION ALL
    SELECT 'Tecnologia','despesa','#4B5945' UNION ALL
    SELECT 'Logística','despesa','#7A6A53' UNION ALL
    SELECT 'Custos fixos','despesa','#5A5A5A' UNION ALL
    SELECT 'Outros','despesa','#8A8A8A'
) x
WHERE u.tipo_perfil='mei'
AND NOT EXISTS (SELECT 1 FROM categorias c WHERE c.usuario_id=u.id AND c.tipo=x.tipo AND c.nome=x.nome);

-- Categorias PF complementares para contas existentes.
INSERT INTO categorias (usuario_id,nome,tipo,cor,padrao)
SELECT u.id, x.nome, x.tipo, x.cor, 1
FROM usuarios u
JOIN (
    SELECT 'Salário' nome,'receita' tipo,'#4B5945' cor UNION ALL
    SELECT 'Renda extra','receita','#3B6E71' UNION ALL
    SELECT 'Investimentos','receita','#5A6E5D' UNION ALL
    SELECT 'Outros','receita','#8A8A8A' UNION ALL
    SELECT 'Alimentação','despesa','#B5654A' UNION ALL
    SELECT 'Mercado','despesa','#A8734D' UNION ALL
    SELECT 'Transporte','despesa','#6B7280' UNION ALL
    SELECT 'Moradia','despesa','#4B5563' UNION ALL
    SELECT 'Saúde','despesa','#7A3B3B' UNION ALL
    SELECT 'Educação','despesa','#3B6E71' UNION ALL
    SELECT 'Lazer','despesa','#8A7B5A' UNION ALL
    SELECT 'Compras','despesa','#765D69' UNION ALL
    SELECT 'Assinaturas','despesa','#5A6E5D' UNION ALL
    SELECT 'Contas e serviços','despesa','#4B5945' UNION ALL
    SELECT 'Outros','despesa','#8A8A8A'
) x
WHERE u.tipo_perfil='pessoa_fisica'
AND NOT EXISTS (SELECT 1 FROM categorias c WHERE c.usuario_id=u.id AND c.tipo=x.tipo AND c.nome=x.nome);
