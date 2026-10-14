<?php
require_once __DIR__ . '/../includes/ambiente.php';

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
        $dsn = 'mysql:host=' . $host .
            ';port=' . (int) $porta .
            ';dbname=' . $nome .
            ';charset=utf8mb4';

        $opcoes = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false
        ];

        if (cpPerfAtivo()) {
            $opcoes[PDO::ATTR_STATEMENT_CLASS] = [CashPilotPerfStatement::class];
            $pdo = cpPerfMedir('conexao', fn() => new CashPilotPerfPDO($dsn, $usuario, $senha, $opcoes));
        } else {
            $pdo = new PDO($dsn, $usuario, $senha, $opcoes);
        }

        $pdo->exec("SET time_zone = '-03:00'");

        return $pdo;
    } catch (PDOException $e) {
        error_log('CashPilot/DB: ' . $e->getMessage());
        http_response_code(500);
        exit('Não foi possível conectar ao CashPilot neste momento.');
    }
}
