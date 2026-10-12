-- ============================================================
-- CashPilot 16 — Schema consolidado para instalação nova
-- MySQL 8+ / MariaDB compatível com XAMPP
-- ============================================================

CREATE DATABASE IF NOT EXISTS cashpilot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cashpilot;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    username VARCHAR(24) NULL UNIQUE,
    username_alterado_em DATETIME NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    email_verificado TINYINT(1) NOT NULL DEFAULT 0,
    email_verificado_em DATETIME NULL,
    email_pendente VARCHAR(150) NULL,
    telefone VARCHAR(30) NULL,
    senha_hash VARCHAR(255) NOT NULL,
    tipo_perfil ENUM('pessoa_fisica','mei') NOT NULL DEFAULT 'pessoa_fisica',
    nicho VARCHAR(80) NULL,
    objetivo_pessoal VARCHAR(80) NULL,
    avatar_path VARCHAR(255) NULL,
    avatar_blob LONGBLOB NULL,
    avatar_mime VARCHAR(50) NULL,
    avatar_atualizado_em DATETIME NULL,
    onboarding_concluido TINYINT(1) NOT NULL DEFAULT 0,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    sessao_versao INT UNSIGNED NOT NULL DEFAULT 1,
    tema_preferido ENUM('light','dark') NOT NULL DEFAULT 'light',
    renda_mensal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    limite_gastos_mensal DECIMAL(12,2) NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE tentativas_login (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    identificador_hash CHAR(64) NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    primeira_tentativa DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_tentativa DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    bloqueado_ate DATETIME NULL,
    UNIQUE KEY uk_tentativa_login (identificador_hash, ip_hash),
    INDEX idx_tentativa_bloqueio (bloqueado_ate),
    INDEX idx_tentativa_ultima (ultima_tentativa)
) ENGINE=InnoDB;

CREATE TABLE codigos_verificacao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    destino VARCHAR(150) NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    expira_em DATETIME NOT NULL,
    usado_em DATETIME NULL,
    tentativas INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_codigo_usuario_tipo (usuario_id, tipo, usado_em, expira_em)
) ENGINE=InnoDB;

CREATE TABLE usuario_oauth (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    provider VARCHAR(30) NOT NULL,
    provider_user_id VARCHAR(190) NOT NULL,
    email_provider VARCHAR(150) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uk_oauth_provider_user (provider, provider_user_id),
    UNIQUE KEY uk_oauth_usuario_provider (usuario_id, provider)
) ENGINE=InnoDB;

CREATE TABLE usuario_apresentacoes (
    usuario_id INT NOT NULL,
    chave VARCHAR(80) NOT NULL,
    visto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, chave),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE perfil_negocio (
    usuario_id INT PRIMARY KEY,
    nome_negocio VARCHAR(150) NULL,
    oferta ENUM('produtos','servicos','ambos') NOT NULL DEFAULT 'servicos',
    operacao ENUM('presencial','online','hibrido','domicilio') NOT NULL DEFAULT 'presencial',
    publico_alvo VARCHAR(150) NULL,
    canal_principal VARCHAR(100) NULL,
    objetivo_principal VARCHAR(100) NULL,
    observacoes VARCHAR(255) NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(80) NOT NULL,
    tipo ENUM('receita','despesa') NOT NULL,
    cor VARCHAR(7) DEFAULT '#4B5945',
    limite_mensal DECIMAL(12,2) NULL,
    padrao TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE contas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(80) NOT NULL,
    tipo ENUM('corrente','poupanca','carteira','empresarial','outra') NOT NULL DEFAULT 'corrente',
    saldo_inicial DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    padrao TINYINT(1) NOT NULL DEFAULT 0,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE fornecedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(140) NOT NULL,
    descricao VARCHAR(180) NULL,
    valor_padrao DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    recorrente TINYINT(1) NOT NULL DEFAULT 0,
    periodicidade ENUM('pontual','semanal','quinzenal','mensal','outro') NOT NULL DEFAULT 'mensal',
    intervalo_dias SMALLINT UNSIGNED NULL,
    dia_vencimento TINYINT UNSIGNED NOT NULL DEFAULT 10,
    data_inicio DATE NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE produtos_servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(140) NOT NULL,
    tipo ENUM('produto','servico') NOT NULL,
    preco_venda DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    custo_unitario DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    estoque_atual INT NOT NULL DEFAULT 0,
    estoque_minimo INT NOT NULL DEFAULT 0,
    controlar_estoque TINYINT(1) NOT NULL DEFAULT 0,
    fornecedor_id INT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id) ON DELETE SET NULL,
    INDEX idx_produto_fornecedor (usuario_id, fornecedor_id)
) ENGINE=InnoDB;

CREATE TABLE funcionarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(140) NOT NULL,
    cargo VARCHAR(100) NULL,
    salario_base DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    outros_custos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    dia_pagamento TINYINT UNSIGNED NOT NULL DEFAULT 5,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE custos_negocio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    descricao VARCHAR(160) NOT NULL,
    valor DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    recorrente TINYINT(1) NOT NULL DEFAULT 1,
    natureza ENUM('fixo','variavel') NOT NULL DEFAULT 'fixo',
    dia_vencimento TINYINT UNSIGNED NOT NULL DEFAULT 10,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE importacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    conta_id INT NULL,
    nome_arquivo VARCHAR(180) NOT NULL,
    formato VARCHAR(10) NULL,
    hash_arquivo VARCHAR(64) NOT NULL,
    quantidade_linhas INT NOT NULL DEFAULT 0,
    quantidade_importadas INT NOT NULL DEFAULT 0,
    quantidade_ignoradas INT NOT NULL DEFAULT 0,
    confianca_media TINYINT UNSIGNED NULL,
    precisa_revisao TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('processando','concluida','erro') NOT NULL DEFAULT 'processando',
    mensagem_erro VARCHAR(255) NULL,
    data_importacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE SET NULL,
    INDEX idx_usuario_hash (usuario_id, hash_arquivo)
) ENGINE=InnoDB;

CREATE TABLE receitas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    categoria_id INT NULL,
    conta_id INT NULL,
    importacao_id INT NULL,
    venda_id INT NULL,
    valor DECIMAL(12,2) NOT NULL,
    descricao VARCHAR(180) NOT NULL,
    observacao VARCHAR(255) NULL,
    status ENUM('efetivado','pendente') NOT NULL DEFAULT 'efetivado',
    data_receita DATE NOT NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE SET NULL,
    FOREIGN KEY (importacao_id) REFERENCES importacoes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE despesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    categoria_id INT NULL,
    conta_id INT NULL,
    importacao_id INT NULL,
    valor DECIMAL(12,2) NOT NULL,
    descricao VARCHAR(180) NOT NULL,
    observacao VARCHAR(255) NULL,
    status ENUM('efetivado','pendente') NOT NULL DEFAULT 'efetivado',
    origem_tipo VARCHAR(32) NOT NULL DEFAULT 'manual',
    origem_id INT NULL,
    competencia CHAR(7) NULL,
    origem_referencia DATE NULL,
    data_despesa DATE NOT NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE SET NULL,
    FOREIGN KEY (importacao_id) REFERENCES importacoes(id) ON DELETE SET NULL,
    INDEX idx_despesas_origem (usuario_id, origem_tipo, origem_id, competencia),
    INDEX idx_despesas_origem_ref (usuario_id, origem_tipo, origem_id, origem_referencia)
) ENGINE=InnoDB;

CREATE TABLE metas (
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

CREATE TABLE meta_movimentacoes (
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

CREATE TABLE recorrencias_pf (
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

CREATE TABLE planejamento_mensal (
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

CREATE TABLE financiamentos_simulados (
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

CREATE TABLE vendas (
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

ALTER TABLE receitas ADD CONSTRAINT fk_receitas_venda FOREIGN KEY (venda_id) REFERENCES vendas(id) ON DELETE SET NULL;

CREATE TABLE venda_itens (
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

CREATE TABLE movimentacoes_estoque (
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

CREATE TABLE classificacao_regras (
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

CREATE TABLE copiloto_conversas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(120) NOT NULL DEFAULT 'Nova conversa',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_copiloto_conversa_usuario (usuario_id, atualizado_em)
) ENGINE=InnoDB;

CREATE TABLE copiloto_mensagens (
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

CREATE TABLE aprender_trilhas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(140) NOT NULL,
    descricao VARCHAR(255) NULL,
    perfil ENUM('pessoa_fisica','mei','ambos') NOT NULL DEFAULT 'ambos',
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE aprender_videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(180) NOT NULL,
    descricao VARCHAR(500) NULL,
    youtube_video_id VARCHAR(32) NOT NULL,
    categoria VARCHAR(80) NOT NULL DEFAULT 'Geral',
    perfil ENUM('pessoa_fisica','mei','ambos') NOT NULL DEFAULT 'ambos',
    tags VARCHAR(500) NULL,
    nichos VARCHAR(500) NULL,
    objetivos VARCHAR(500) NULL,
    duracao_segundos INT NULL,
    ordem INT NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_aprender_youtube (youtube_video_id)
) ENGINE=InnoDB;

CREATE TABLE aprender_trilha_videos (
    trilha_id INT NOT NULL,
    video_id INT NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    PRIMARY KEY (trilha_id, video_id),
    FOREIGN KEY (trilha_id) REFERENCES aprender_trilhas(id) ON DELETE CASCADE,
    FOREIGN KEY (video_id) REFERENCES aprender_videos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE aprender_progresso (
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

CREATE TABLE reserva_emergencia (
    usuario_id INT PRIMARY KEY,
    valor_atual DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    meses_objetivo TINYINT UNSIGNED NOT NULL DEFAULT 6,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE desafios_economia (
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

CREATE TABLE planos_acao (
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

CREATE TABLE plano_acao_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plano_id INT NOT NULL,
    descricao VARCHAR(240) NOT NULL,
    concluido TINYINT(1) NOT NULL DEFAULT 0,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (plano_id) REFERENCES planos_acao(id) ON DELETE CASCADE,
    INDEX idx_plano_item (plano_id, ordem, id)
) ENGINE=InnoDB;

CREATE TABLE investimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    meta_id INT NULL,
    nome VARCHAR(140) NOT NULL,
    classe ENUM('renda_fixa','tesouro','acoes','fiis','etfs','fundos','cripto','poupanca','outros') NOT NULL DEFAULT 'outros',
    subtipo VARCHAR(100) NULL,
    instituicao VARCHAR(120) NULL,
    quantidade DECIMAL(18,8) NULL,
    preco_medio DECIMAL(14,4) NULL,
    valor_aplicado DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    valor_atual DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    incluido_no_saldo TINYINT(1) NOT NULL DEFAULT 1,
    data_inicio DATE NULL,
    observacao VARCHAR(500) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (meta_id) REFERENCES metas(id) ON DELETE SET NULL,
    INDEX idx_investimentos_usuario (usuario_id, ativo, classe),
    INDEX idx_investimentos_meta (meta_id)
) ENGINE=InnoDB;

CREATE TABLE investimento_movimentacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    investimento_id INT NOT NULL,
    usuario_id INT NOT NULL,
    tipo ENUM('aporte','retirada','ajuste') NOT NULL,
    valor DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    quantidade DECIMAL(18,8) NULL,
    observacao VARCHAR(255) NULL,
    data_movimentacao DATE NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (investimento_id) REFERENCES investimentos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_invest_mov_usuario_data (usuario_id, data_movimentacao),
    INDEX idx_invest_mov_ativo (investimento_id, data_movimentacao)
) ENGINE=InnoDB;

CREATE TABLE planejamento_categoria_mensal (
    usuario_id INT NOT NULL,
    competencia CHAR(7) NOT NULL,
    categoria_id INT NOT NULL,
    valor_limite DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, competencia, categoria_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE planejamento_destino_mensal (
    usuario_id INT NOT NULL,
    competencia CHAR(7) NOT NULL,
    tipo ENUM('investimentos','reserva') NOT NULL,
    valor_planejado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, competencia, tipo),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE perfil_investidor (
    usuario_id INT PRIMARY KEY,
    objetivo ENUM('reserva','viagem','casa','aposentadoria','patrimonio','outro') NOT NULL DEFAULT 'patrimonio',
    prazo ENUM('curto','medio','longo') NOT NULL DEFAULT 'medio',
    risco TINYINT UNSIGNED NOT NULL DEFAULT 2,
    reserva_emergencia TINYINT(1) NOT NULL DEFAULT 0,
    perfil ENUM('conservador','moderado','arrojado') NOT NULL DEFAULT 'moderado',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TRIGGER IF EXISTS trg_categorias_padrao;
DELIMITER $$
CREATE TRIGGER trg_categorias_padrao
AFTER INSERT ON usuarios
FOR EACH ROW
BEGIN
    IF NEW.tipo_perfil = 'mei' THEN
        INSERT INTO categorias (usuario_id,nome,tipo,cor,padrao) VALUES
        (NEW.id,'Vendas','receita','#3B6E71',1),(NEW.id,'Serviços','receita','#4B5945',1),(NEW.id,'PIX e transferências recebidas','receita','#3B6E71',1),(NEW.id,'Comissões','receita','#5A6E5D',1),(NEW.id,'Reembolsos','receita','#6B7280',1),(NEW.id,'Outras entradas','receita','#8A8A8A',1),
        (NEW.id,'Funcionários','despesa','#7A3B3B',1),(NEW.id,'Contabilidade','despesa','#4B5563',1),(NEW.id,'Taxas bancárias','despesa','#5A5A5A',1),(NEW.id,'Maquininha e meios de pagamento','despesa','#3B6E71',1),(NEW.id,'Software e assinaturas','despesa','#4B5945',1),(NEW.id,'Manutenção','despesa','#7A6A53',1),(NEW.id,'Fretes e entregas','despesa','#8A7B5A',1),(NEW.id,'Pró-labore','despesa','#765D69',1),(NEW.id,'Fornecedores','despesa','#6B7280',1),(NEW.id,'Estoque e insumos','despesa','#8A7B5A',1),(NEW.id,'Aluguel comercial','despesa','#4B5563',1),(NEW.id,'Marketing','despesa','#3B6E71',1),(NEW.id,'Impostos','despesa','#3F3F3F',1),(NEW.id,'Tecnologia','despesa','#4B5945',1),(NEW.id,'Logística','despesa','#7A6A53',1),(NEW.id,'Custos fixos','despesa','#5A5A5A',1),(NEW.id,'Custos variáveis','despesa','#7A6A53',1),(NEW.id,'Outros','despesa','#8A8A8A',1);
    ELSE
        INSERT INTO categorias (usuario_id,nome,tipo,cor,padrao) VALUES
        (NEW.id,'Salário','receita','#4B5945',1),(NEW.id,'Renda extra','receita','#3B6E71',1),(NEW.id,'Freelance','receita','#3B6E71',1),(NEW.id,'Benefícios','receita','#5A6E5D',1),(NEW.id,'Reembolsos','receita','#6B7280',1),(NEW.id,'Investimentos','receita','#5A6E5D',1),(NEW.id,'Outros','receita','#8A8A8A',1),
        (NEW.id,'Alimentação','despesa','#B5654A',1),(NEW.id,'Restaurantes e delivery','despesa','#B5654A',1),(NEW.id,'Mercado','despesa','#A8734D',1),(NEW.id,'Transporte','despesa','#6B7280',1),(NEW.id,'Combustível','despesa','#7A6A53',1),(NEW.id,'Aplicativos e mobilidade','despesa','#6B7280',1),(NEW.id,'Moradia','despesa','#4B5563',1),(NEW.id,'Saúde','despesa','#7A3B3B',1),(NEW.id,'Energia e água','despesa','#4B5945',1),(NEW.id,'Internet e telefone','despesa','#3B6E71',1),(NEW.id,'Seguros','despesa','#4B5563',1),(NEW.id,'Cuidados pessoais','despesa','#765D69',1),(NEW.id,'Pets','despesa','#8A7B5A',1),(NEW.id,'Educação','despesa','#3B6E71',1),(NEW.id,'Lazer','despesa','#8A7B5A',1),(NEW.id,'Viagens','despesa','#3B6E71',1),(NEW.id,'Presentes e doações','despesa','#8A7B5A',1),(NEW.id,'Taxas e tarifas','despesa','#5A5A5A',1),(NEW.id,'Compras','despesa','#765D69',1),(NEW.id,'Assinaturas','despesa','#5A6E5D',1),(NEW.id,'Contas e serviços','despesa','#4B5945',1),(NEW.id,'Outros','despesa','#8A8A8A',1);
    END IF;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS trg_conta_padrao;
DELIMITER $$
CREATE TRIGGER trg_conta_padrao
AFTER INSERT ON usuarios
FOR EACH ROW
BEGIN
    INSERT INTO contas (usuario_id, nome, tipo, padrao)
    VALUES (NEW.id, 'Conta Principal', IF(NEW.tipo_perfil='mei','empresarial','corrente'), 1);
END$$
DELIMITER ;


-- ============================================================
-- CashPilot 15.3 — Open Finance / Pluggy
-- ============================================================
-- CashPilot 15.3 — Pluggy / Open Finance
CREATE TABLE IF NOT EXISTS pluggy_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    pluggy_item_id CHAR(36) NOT NULL,
    connector_id INT NULL,
    instituicao_nome VARCHAR(150) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'CREATED',
    execution_status VARCHAR(50) NULL,
    perfil ENUM('pessoa_fisica','mei') NOT NULL DEFAULT 'pessoa_fisica',
    erro_codigo VARCHAR(80) NULL,
    erro_mensagem VARCHAR(255) NULL,
    ultima_sincronizacao DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pluggy_item (pluggy_item_id),
    INDEX idx_pluggy_item_usuario (usuario_id, status),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pluggy_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    pluggy_item_id CHAR(36) NOT NULL,
    pluggy_account_id CHAR(36) NOT NULL,
    conta_id INT NULL,
    nome VARCHAR(180) NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    subtipo VARCHAR(60) NULL,
    numero_mascarado VARCHAR(80) NULL,
    saldo DECIMAL(15,2) NULL,
    moeda CHAR(3) NOT NULL DEFAULT 'BRL',
    dados_credito_json JSON NULL,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pluggy_account (pluggy_account_id),
    INDEX idx_pluggy_account_usuario (usuario_id, pluggy_item_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (conta_id) REFERENCES contas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS pluggy_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    pluggy_item_id CHAR(36) NOT NULL,
    pluggy_account_id CHAR(36) NOT NULL,
    pluggy_transaction_id CHAR(36) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data_transacao DATE NOT NULL,
    tipo_externo VARCHAR(30) NULL,
    tipo_sugerido ENUM('receita','despesa') NOT NULL,
    categoria_externa VARCHAR(120) NULL,
    categoria_externa_id VARCHAR(40) NULL,
    status_externo VARCHAR(30) NULL,
    importada TINYINT(1) NOT NULL DEFAULT 0,
    ignorada TINYINT(1) NOT NULL DEFAULT 0,
    receita_id INT NULL,
    despesa_id INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pluggy_transaction (pluggy_transaction_id),
    INDEX idx_pluggy_tx_usuario_data (usuario_id, data_transacao),
    INDEX idx_pluggy_tx_importacao (usuario_id, importada, ignorada),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (receita_id) REFERENCES receitas(id) ON DELETE SET NULL,
    FOREIGN KEY (despesa_id) REFERENCES despesas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- CashPilot 16.5 — fila idempotente de eventos da Pluggy
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
