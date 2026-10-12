-- CashPilot 15.0 — consolidação, admin de teste, investimentos educacionais e importação
USE cashpilot;

CREATE TABLE IF NOT EXISTS perfil_investidor (
    usuario_id INT PRIMARY KEY,
    objetivo ENUM('reserva','viagem','casa','aposentadoria','patrimonio','outro') NOT NULL DEFAULT 'patrimonio',
    prazo ENUM('curto','medio','longo') NOT NULL DEFAULT 'medio',
    risco TINYINT UNSIGNED NOT NULL DEFAULT 2,
    reserva_emergencia TINYINT(1) NOT NULL DEFAULT 0,
    perfil ENUM('conservador','moderado','arrojado') NOT NULL DEFAULT 'moderado',
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_perfil_investidor_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB;

ALTER TABLE importacoes
    ADD COLUMN formato VARCHAR(10) NULL AFTER nome_arquivo,
    ADD COLUMN confianca_media TINYINT UNSIGNED NULL AFTER quantidade_ignoradas,
    ADD COLUMN precisa_revisao TINYINT(1) NOT NULL DEFAULT 0 AFTER confianca_media;

ALTER TABLE usuarios
    MODIFY email_verificado TINYINT(1) NOT NULL DEFAULT 0;
