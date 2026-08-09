-- ============================================================
-- CashPilot - Migração 001
-- Evolução do schema para suportar Importação de Extrato:
-- tabelas `contas` e `importacoes`, e novos campos em
-- receitas/despesas (conta_id, importacao_id, observacao, status).
--
-- Seguro para rodar em um banco já existente (não apaga dados).
-- Se você está instalando o CashPilot pela primeira vez, ignore
-- este arquivo e use apenas database/banco.sql.
-- ============================================================

USE cashpilot;

-- ------------------------------------------------------------
-- Tabela: contas
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
-- Novas colunas em receitas / despesas
-- (rode cada bloco uma única vez; se a coluna já existir, o
-- MySQL vai acusar erro "Duplicate column name" — pode ignorar)
-- ------------------------------------------------------------
ALTER TABLE receitas
    ADD COLUMN conta_id INT NULL AFTER categoria_id,
    ADD COLUMN importacao_id INT NULL AFTER conta_id,
    ADD COLUMN observacao VARCHAR(255) NULL AFTER descricao,
    ADD COLUMN status ENUM('efetivado', 'pendente') NOT NULL DEFAULT 'efetivado' AFTER observacao,
    ADD FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (importacao_id) REFERENCES importacoes(id) ON DELETE SET NULL;

ALTER TABLE despesas
    ADD COLUMN conta_id INT NULL AFTER categoria_id,
    ADD COLUMN importacao_id INT NULL AFTER conta_id,
    ADD COLUMN observacao VARCHAR(255) NULL AFTER descricao,
    ADD COLUMN status ENUM('efetivado', 'pendente') NOT NULL DEFAULT 'efetivado' AFTER observacao,
    ADD FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (importacao_id) REFERENCES importacoes(id) ON DELETE SET NULL;

-- ------------------------------------------------------------
-- Cria uma conta "Conta Principal" para cada usuário já existente
-- ------------------------------------------------------------
INSERT INTO contas (usuario_id, nome, tipo, padrao)
SELECT u.id, 'Conta Principal', IF(u.tipo_perfil = 'mei', 'empresarial', 'corrente'), 1
FROM usuarios u
WHERE NOT EXISTS (SELECT 1 FROM contas c WHERE c.usuario_id = u.id AND c.padrao = 1);

-- ------------------------------------------------------------
-- Trigger: garante conta padrão para novos cadastros
-- ------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_conta_padrao;
DELIMITER $$
CREATE TRIGGER trg_conta_padrao
AFTER INSERT ON usuarios
FOR EACH ROW
BEGIN
    INSERT INTO contas (usuario_id, nome, tipo, padrao) VALUES
    (NEW.id, 'Conta Principal', IF(NEW.tipo_perfil = 'mei', 'empresarial', 'corrente'), 1);
END$$
DELIMITER ;
