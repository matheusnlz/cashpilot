<?php
/**
 * CashPilot - Conexão PDO.
 *
 * Local/XAMPP:
 *   DB_HOST=localhost, DB_NAME=cashpilot, DB_USER=root, DB_PASS=""
 *
 * Railway:
 *   aceita diretamente MYSQLHOST, MYSQLPORT, MYSQLDATABASE,
 *   MYSQLUSER e MYSQLPASSWORD fornecidas pelo serviço MySQL.
 */

function cashpilotEnvPrimeiro(array $nomes, string $padrao = ''): string
{
    foreach ($nomes as $nome) {
        $valor = getenv($nome);
        if (is_string($valor) && trim($valor) !== '') {
            return trim($valor);
        }
    }

    return $padrao;
}

function conectar(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = cashpilotEnvPrimeiro(['DB_HOST', 'MYSQLHOST'], 'localhost');
    $porta = cashpilotEnvPrimeiro(['DB_PORT', 'MYSQLPORT'], '3306');
    $nome = cashpilotEnvPrimeiro(['DB_NAME', 'MYSQLDATABASE'], 'cashpilot');
    $usuario = cashpilotEnvPrimeiro(['DB_USER', 'MYSQLUSER'], 'root');
    $senha = cashpilotEnvPrimeiro(['DB_PASS', 'MYSQLPASSWORD'], '');

    try {
        $dsn = 'mysql:host=' . $host
            . ';port=' . (int) $porta
            . ';dbname=' . $nome
            . ';charset=utf8mb4';

        $pdo = new PDO($dsn, $usuario, $senha, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);

        return $pdo;
    } catch (PDOException $e) {
        error_log('CashPilot/DB: ' . $e->getMessage());
        http_response_code(500);
        exit('Não foi possível conectar ao CashPilot neste momento.');
    }
}
