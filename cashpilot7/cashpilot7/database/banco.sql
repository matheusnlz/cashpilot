-- ============================================================
-- CashPilot - Schema do Banco de Dados
-- ============================================================

CREATE DATABASE IF NOT EXISTS cashpilot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cashpilot;

-- ------------------------------------------------------------
-- Tabela: usuarios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    telefone VARCHAR(30) NULL,
    senha_hash VARCHAR(255) NOT NULL,
    tipo_perfil ENUM('pessoa_fisica', 'mei') NOT NULL DEFAULT 'pessoa_fisica',
    nicho VARCHAR(80) NULL,
    avatar_path VARCHAR(255) NULL,
    onboarding_concluido TINYINT(1) NOT NULL DEFAULT 1,
    renda_mensal DECIMAL(12,2) DEFAULT 0.00,
    limite_gastos_mensal DECIMAL(12,2) NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ------------------------------------------------------------
-- Perfil complementar do negócio
-- ------------------------------------------------------------
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
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
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
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
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
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabela: categorias
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(80) NOT NULL,
    tipo ENUM('receita', 'despesa') NOT NULL,
    cor VARCHAR(7) DEFAULT '#4B5945',
    padrao TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabela: contas
-- Representa uma conta/carteira do usuário (ex: "Conta Corrente",
-- "Nubank", "Caixa da empresa"). Toda movimentação e importação
-- fica vinculada a uma conta.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(80) NOT NULL,
    tipo ENUM('corrente', 'poupanca', 'carteira', 'empresarial', 'outra') NOT NULL DEFAULT 'corrente',
    saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    padrao TINYINT(1) NOT NULL DEFAULT 0,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabela: importacoes
-- Registra o histórico de extratos importados via CSV.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS importacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    conta_id INT NULL,
    nome_arquivo VARCHAR(180) NOT NULL,
    hash_arquivo VARCHAR(64) NOT NULL,
    quantidade_linhas INT NOT NULL DEFAULT 0,
    quantidade_importadas INT NOT NULL DEFAULT 0,
    quantidade_ignoradas INT NOT NULL DEFAULT 0,
    status ENUM('processando', 'concluida', 'erro') NOT NULL DEFAULT 'processando',
    mensagem_erro VARCHAR(255) NULL,
    data_importacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE SET NULL,
    INDEX idx_usuario_hash (usuario_id, hash_arquivo)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabela: receitas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS receitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    categoria_id INT NULL,
    conta_id INT NULL,
    importacao_id INT NULL,
    valor DECIMAL(12,2) NOT NULL,
    descricao VARCHAR(180) NOT NULL,
    observacao VARCHAR(255) NULL,
    status ENUM('efetivado', 'pendente') NOT NULL DEFAULT 'efetivado',
    data_receita DATE NOT NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE SET NULL,
    FOREIGN KEY (importacao_id) REFERENCES importacoes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabela: despesas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    categoria_id INT NULL,
    conta_id INT NULL,
    importacao_id INT NULL,
    valor DECIMAL(12,2) NOT NULL,
    descricao VARCHAR(180) NOT NULL,
    observacao VARCHAR(255) NULL,
    status ENUM('efetivado', 'pendente') NOT NULL DEFAULT 'efetivado',
    data_despesa DATE NOT NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE SET NULL,
    FOREIGN KEY (importacao_id) REFERENCES importacoes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Tabela: metas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS metas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(120) NOT NULL,
    valor_meta DECIMAL(12,2) NOT NULL,
    valor_atual DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    prazo DATE NULL,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    concluida TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- Trigger: cria categorias padrão automaticamente para todo
-- novo usuário cadastrado
-- ============================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS trg_categorias_padrao
AFTER INSERT ON usuarios
FOR EACH ROW
BEGIN
    IF NEW.tipo_perfil = 'mei' THEN
        INSERT INTO categorias (usuario_id,nome,tipo,cor,padrao) VALUES
        (NEW.id,'Vendas','receita','#3B6E71',1),(NEW.id,'Serviços','receita','#4B5945',1),(NEW.id,'Outras entradas','receita','#8A8A8A',1),
        (NEW.id,'Funcionários','despesa','#7A3B3B',1),(NEW.id,'Fornecedores','despesa','#6B7280',1),(NEW.id,'Estoque e insumos','despesa','#8A7B5A',1),(NEW.id,'Aluguel comercial','despesa','#4B5563',1),(NEW.id,'Marketing','despesa','#3B6E71',1),(NEW.id,'Impostos','despesa','#3F3F3F',1),(NEW.id,'Tecnologia','despesa','#4B5945',1),(NEW.id,'Logística','despesa','#7A6A53',1),(NEW.id,'Custos fixos','despesa','#5A5A5A',1),(NEW.id,'Outros','despesa','#8A8A8A',1);
    ELSE
        INSERT INTO categorias (usuario_id,nome,tipo,cor,padrao) VALUES
        (NEW.id,'Salário','receita','#4B5945',1),(NEW.id,'Renda extra','receita','#3B6E71',1),(NEW.id,'Investimentos','receita','#5A6E5D',1),(NEW.id,'Outros','receita','#8A8A8A',1),
        (NEW.id,'Alimentação','despesa','#B5654A',1),(NEW.id,'Mercado','despesa','#A8734D',1),(NEW.id,'Transporte','despesa','#6B7280',1),(NEW.id,'Moradia','despesa','#4B5563',1),(NEW.id,'Saúde','despesa','#7A3B3B',1),(NEW.id,'Educação','despesa','#3B6E71',1),(NEW.id,'Lazer','despesa','#8A7B5A',1),(NEW.id,'Compras','despesa','#765D69',1),(NEW.id,'Assinaturas','despesa','#5A6E5D',1),(NEW.id,'Contas e serviços','despesa','#4B5945',1),(NEW.id,'Outros','despesa','#8A8A8A',1);
    END IF;
END$$
DELIMITER ;

-- ============================================================
-- Trigger: cria uma conta padrão ("Conta Principal") para todo
-- novo usuário, para que ele já tenha onde registrar/importar
-- movimentações sem precisar de uma etapa extra.
-- ============================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS trg_conta_padrao
AFTER INSERT ON usuarios
FOR EACH ROW
BEGIN
    INSERT INTO contas (usuario_id, nome, tipo, padrao) VALUES
    (NEW.id, 'Conta Principal', IF(NEW.tipo_perfil = 'mei', 'empresarial', 'corrente'), 1);
END$$
DELIMITER ;


-- CashPilot 7: para instalações novas, execute também database/migrations/004_cashpilot7_integracao_financeira.sql após este schema.
