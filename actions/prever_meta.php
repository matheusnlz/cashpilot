<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/python_helper.php';

exigirLogin();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$metaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$usuarioId = (int) usuarioLogadoId();
if ($metaId <= 0) {
    http_response_code(400);
    echo json_encode(['erro' => 'Meta inválida'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = conectar();
$stmt = $pdo->prepare('SELECT 1 FROM metas WHERE id=:id AND usuario_id=:uid LIMIT 1');
$stmt->execute(['id' => $metaId, 'uid' => $usuarioId]);
if (!$stmt->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['erro' => 'Meta não encontrada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$script = __DIR__ . '/../python/previsao.py';
if (!is_file($script)) {
    http_response_code(503);
    echo json_encode(['erro' => 'A previsão não está disponível no momento.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$processo = executarProcessoCashPilot(
    [caminhoPythonCashPilot(), $script, (string) $usuarioId, (string) $metaId],
    __DIR__ . '/../python',
    null,
    15
);

if (empty($processo['ok'])) {
    error_log(
        'CashPilot/Previsao Python: ' .
        mb_substr((string) ($processo['stderr'] ?? 'erro local'), 0, 800)
    );
    http_response_code(503);
    echo json_encode(['erro' => 'A previsão não está disponível no momento.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$saida = (string) ($processo['stdout'] ?? '');
$dados = json_decode((string) $saida, true);
if (!is_array($dados)) {
    http_response_code(503);
    echo json_encode(['erro' => 'A previsão não está disponível no momento.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode($dados, JSON_UNESCAPED_UNICODE);
