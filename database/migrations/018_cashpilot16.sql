-- CashPilot16: aplicar no banco existente, após a migration 017.
-- Não cria banco, não apaga tabelas e não altera valores financeiros existentes.
-- Pode ser reaplicada: verifica a coluna antes do ALTER (MySQL e MariaDB).
SET @cp16_coluna_existe = (
    SELECT COUNT(*) FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'investimentos' AND column_name = 'incluido_no_saldo'
);
SET @cp16_sql = IF(@cp16_coluna_existe = 0,
    'ALTER TABLE investimentos ADD COLUMN incluido_no_saldo TINYINT(1) NOT NULL DEFAULT 1 AFTER valor_atual',
    'SELECT 1');
PREPARE cp16_stmt FROM @cp16_sql;
EXECUTE cp16_stmt;
DEALLOCATE PREPARE cp16_stmt;

-- Recupera vínculos inequívocos que a antiga ação de vendas não preenchia.
UPDATE receitas r
JOIN (
    SELECT receita_id, usuario_id, MIN(id) AS venda_id
    FROM vendas WHERE receita_id IS NOT NULL
    GROUP BY receita_id, usuario_id HAVING COUNT(*) = 1
) v ON v.receita_id = r.id AND v.usuario_id = r.usuario_id
SET r.venda_id = v.venda_id
WHERE r.venda_id IS NULL;
