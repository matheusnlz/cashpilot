<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/pluggy_webhook_service.php';

header('Content-Type: application/json; charset=utf-8');
exigirLogin();
exigirPost();
validarCsrf();

try {
    $resultado = pluggyWebhookProcessarPendentes(
        conectar(),
        (int) usuarioLogadoId(),
        3
    );

    echo json_encode(
        ['ok' => true] + $resultado,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $erro) {
    error_log('CashPilot/PluggyWebhookQueue: ' . $erro->getMessage());
    http_response_code(422);
    echo json_encode(
        ['ok' => false, 'message' => 'Não foi possível processar as atualizações bancárias.'],
        JSON_UNESCAPED_UNICODE
    );
}

