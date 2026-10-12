<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/pluggy_service.php';

header('Content-Type: application/json; charset=utf-8');
exigirLogin();
exigirPost();
validarCsrf();

try {
    $pdo = conectar();
    $uid = (int) usuarioLogadoId();
    $itemId = trim((string) ($_POST['item_id'] ?? ''));

    if ($itemId !== '') {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM pluggy_items WHERE usuario_id = :uid AND pluggy_item_id = :item LIMIT 1'
        );
        $stmt->execute(['uid' => $uid, 'item' => $itemId]);
        if (!$stmt->fetchColumn()) {
            throw new RuntimeException('Conexão bancária não encontrada.');
        }
    }

    $token = pluggyCriarConnectToken($uid, $itemId !== '' ? $itemId : null);
    echo json_encode(
        [
            'ok' => true,
            'accessToken' => (string) ($token['accessToken'] ?? ''),
            'includeSandbox' => pluggySandboxAtivo()
        ],
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {
    cp17Log('PluggyConnectToken', $e);
    http_response_code(422);
    echo json_encode(
        ['ok' => false, 'message' => pluggyMensagemPublica($e)],
        JSON_UNESCAPED_UNICODE
    );
}
