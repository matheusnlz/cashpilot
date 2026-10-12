<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/pluggy_repository.php';

header('Content-Type: application/json; charset=utf-8');
exigirLogin();
exigirPost();
validarCsrf();

try {
    $uid = (int) usuarioLogadoId();
    $itemId = trim((string) ($_POST['item_id'] ?? ''));
    $perfil = trim((string) ($_POST['perfil'] ?? ''));
    if (!preg_match('/^[a-f0-9-]{20,50}$/i', $itemId)) {
        throw new RuntimeException('Item da Pluggy inválido.');
    }

    $pdo = conectar();
    $resultado = pluggySalvarItemEFinanceiro($pdo, $uid, $itemId, $perfil);
    $estado = is_array($resultado['estado'] ?? null) ? $resultado['estado'] : [];
    $mensagem =
        ($estado['classe'] ?? '') === 'processing'
            ? 'Conexão recebida. A instituição ainda está preparando os dados.'
            : 'Conta conectada e dados disponíveis carregados.';

    echo json_encode(
        [
            'ok' => true,
            'message' => $mensagem,
            'contas' => $resultado['contas'],
            'transacoes' => $resultado['transacoes_processadas'],
            'estado' => $estado['rotulo'] ?? null
        ],
        JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {
    $requestId = $e instanceof PluggyApiException ? $e->requestId() : null;
    cp17Log('PluggySave', $e);
    http_response_code(422);
    echo json_encode(
        ['ok' => false, 'message' => pluggyMensagemPublica($e)],
        JSON_UNESCAPED_UNICODE
    );
}
