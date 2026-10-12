<?php
/** Executar pelo terminal. Sem rota web e sem eliminar lançamentos financeiros. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';

$aplicar = in_array('--aplicar', $argv, true);
$pdo = conectar();
$config = cp17Configuracao()['retencao'];
$agora = time();
$conversasAte = date('Y-m-d H:i:s', $agora - $config['conversas_dias'] * 86400);
$webhookAte = date('Y-m-d H:i:s', $agora - $config['payload_webhook_dias'] * 86400);
$alvos = [
    ['cp_limites', 'expira_em < ?', [$agora - 86400]],
    ['codigos_verificacao', 'expira_em < ?', [date('Y-m-d H:i:s', $agora - 86400)]],
    ['copiloto_conversas', 'atualizado_em < ?', [$conversasAte]]
];

try {
    foreach ($alvos as [$tabela, $where, $params]) {
        $s = $pdo->prepare('SELECT COUNT(*) FROM `' . $tabela . '` WHERE ' . $where);
        $s->execute($params);
        echo $tabela . ': ' . (int) $s->fetchColumn() . ' registro(s) elegível(is).' . PHP_EOL;
    }
    $s = $pdo->prepare('SELECT COUNT(*) FROM pluggy_webhook_eventos
        WHERE status IN ("processado", "ignorado") AND recebido_em < ? AND payload_json <> "{}"');
    $s->execute([$webhookAte]);
    echo 'Payloads de webhook: ' . (int) $s->fetchColumn() . ' elegível(is).' . PHP_EOL;
    if (!$aplicar) {
        echo 'Simulação apenas. Use --aplicar para executar o descarte acima.' . PHP_EOL;
        exit();
    }
    $pdo->beginTransaction();
    foreach ($alvos as [$tabela, $where, $params]) {
        $s = $pdo->prepare('DELETE FROM `' . $tabela . '` WHERE ' . $where);
        $s->execute($params);
    }
    // Mantém hash/evento/status para deduplicação; apaga apenas corpo e erro textual.
    $s = $pdo->prepare('UPDATE pluggy_webhook_eventos SET payload_json = "{}", erro_mensagem = NULL
        WHERE status IN ("processado", "ignorado") AND recebido_em < ?');
    $s->execute([$webhookAte]);
    $pdo->commit();
    echo 'Manutenção aplicada. Conversas antigas excluídas não são recuperáveis sem backup.' .
        PHP_EOL;
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    cp17Log('Manutencao', $erro);
    fwrite(STDERR, 'Falha na manutenção. Nenhuma transação parcial foi confirmada.' . PHP_EOL);
    exit(1);
}
