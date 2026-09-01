-- CashPilot 14.9 - proteção contra força bruta no login
CREATE TABLE IF NOT EXISTS tentativas_login (
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
