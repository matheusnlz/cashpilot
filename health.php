<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function cpHealthEnv(array $nomes, string $padrao = ''): string
{
    foreach ($nomes as $nome) {
        $valor = getenv($nome);
        if (is_string($valor) && trim($valor) !== '') {
            return trim($valor);
        }
    }

    return $padrao;
}

$extensoes = ['pdo_mysql', 'curl', 'fileinfo', 'mbstring'];

foreach ($extensoes as $extensao) {
    if (!extension_loaded($extensao)) {
        http_response_code(503);
        echo json_encode(['status' => 'unavailable']);
        exit;
    }
}

$host = cpHealthEnv(['DB_HOST', 'MYSQLHOST'], 'localhost');
$porta = cpHealthEnv(['DB_PORT', 'MYSQLPORT'], '3306');
$banco = cpHealthEnv(['DB_NAME', 'MYSQLDATABASE'], 'cashpilot');
$usuario = cpHealthEnv(['DB_USER', 'MYSQLUSER'], 'root');
$senha = cpHealthEnv(['DB_PASS', 'MYSQLPASSWORD'], '');

try {
    $pdo = new PDO(
        'mysql:host=' . $host
        . ';port=' . (int) $porta
        . ';dbname=' . $banco
        . ';charset=utf8mb4',
        $usuario,
        $senha,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3,
        ]
    );

    $pdo->query('SELECT 1');

    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    error_log('CashPilot/Health: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['status' => 'unavailable']);
}
