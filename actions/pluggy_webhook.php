<?php
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/pluggy_webhook_service.php';

header('Content-Type: application/json; charset=utf-8');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false]);
    exit();
}

$esperado = pluggyEnv('PLUGGY_WEBHOOK_TOKEN');
$recebido = trim((string) ($_SERVER['HTTP_X_CASHPILOT_WEBHOOK_TOKEN'] ?? ''));
if ($esperado === '' || $recebido === '' || !hash_equals($esperado, $recebido)) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit();
}

$tamanhoDeclarado = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($tamanhoDeclarado > 1048576) {
    http_response_code(413);
    echo json_encode(['ok' => false]);
    exit();
}

$corpo = (string) file_get_contents('php://input');
if ($corpo === '' || strlen($corpo) > 1048576) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit();
}
$payload = json_decode($corpo, true);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit();
}

try {
    $pdo = conectar();
    $eventoId = pluggyWebhookRegistrar($pdo, $payload, $corpo);

    http_response_code(202);
    echo json_encode(['ok' => true]);

    // Em PHP-FPM, conclui a resposta antes de iniciar o trabalho mais pesado.
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        try {
            pluggyWebhookProcessarEvento($pdo, $eventoId);
        } catch (Throwable $erro) {
            error_log('CashPilot/PluggyWebhookAsync: ' . $erro->getMessage());
        }
    }
} catch (Throwable $erro) {
    error_log('CashPilot/PluggyWebhook: ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}

