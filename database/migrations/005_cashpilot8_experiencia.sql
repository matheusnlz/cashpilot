-- CashPilot 8 - experiência, orçamento por categoria e recorrência de fornecedores
-- Execute UMA VEZ após as migrations anteriores.
USE cashpilot;

ALTER TABLE categorias
    ADD COLUMN limite_mensal DECIMAL(12,2) NULL AFTER cor;

ALTER TABLE fornecedores
    ADD COLUMN periodicidade ENUM('pontual','semanal','quinzenal','mensal','outro') NOT NULL DEFAULT 'mensal' AFTER recorrente,
    ADD COLUMN intervalo_dias SMALLINT UNSIGNED NULL AFTER periodicidade,
    ADD COLUMN data_inicio DATE NULL AFTER dia_vencimento;

UPDATE fornecedores
SET periodicidade = CASE WHEN recorrente = 1 THEN 'mensal' ELSE 'pontual' END;

ALTER TABLE despesas
    ADD COLUMN origem_referencia DATE NULL AFTER competencia,
    ADD INDEX idx_despesas_origem_ref (usuario_id, origem_tipo, origem_id, origem_referencia);
