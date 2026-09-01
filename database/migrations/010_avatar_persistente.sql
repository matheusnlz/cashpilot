-- CashPilot 13.1
-- Execute uma única vez no banco cashpilot.

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS avatar_blob LONGBLOB NULL AFTER avatar_path,
    ADD COLUMN IF NOT EXISTS avatar_mime VARCHAR(50) NULL AFTER avatar_blob,
    ADD COLUMN IF NOT EXISTS avatar_atualizado_em DATETIME NULL AFTER avatar_mime;
