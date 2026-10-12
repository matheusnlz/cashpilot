<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/pluggy_repository.php';

header('Content-Type: application/json; charset=utf-8');
exigirLogin();
exigirPost();
validarCsrf();

try {
    $pdo = conectar();
    $uid = (int) usuarioLogadoId();
    $itemId = trim((string) ($_POST['item_id'] ?? ''));
    if (!pluggyItemPertenceAoUsuario($pdo, $uid, $itemId)) {
        throw new RuntimeException('Conexão bancária não encontrada.');
    }

    // A atualização pode ter limite de frequência no plano. Se a Pluggy aceitar,
    // iniciamos a execução e depois buscamos o estado/dados disponíveis.
    $atualizacaoSolicitada = false;
    $avisoAtualizacao = '';
    try {
        pluggyAtualizarItem($itemId);
        $atualizacaoSolicitada = true;
    } catch (Throwable $e) {
        // Um limite de frequência não impede a leitura do último dado disponível.
        $avisoAtualizacao = pluggyMensagemPublica($e);
        $requestId = $e instanceof PluggyApiException ? $e->requestId() : null;
        cp17Log('PluggyUpdate', $e);
    }

    $resultado = pluggySalvarItemEFinanceiro($pdo, $uid, $itemId);
    $estado = is_array($resultado['estado'] ?? null) ? $resultado['estado'] : [];
    if ($avisoAtualizacao !== '') {
        $mensagem = 'Os últimos dados disponíveis foram relidos. ' . $avisoAtualizacao;
    } elseif (($estado['classe'] ?? '') === 'processing') {
        $mensagem = 'A atualização foi solicitada e continua sendo processada pela instituição.';
    } elseif ($atualizacaoSolicitada) {
        $mensagem = 'Atualização solicitada e dados disponíveis carregados.';
    } else {
        $mensagem = 'Últimos dados disponíveis carregados.';
    }

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
    cp17Log('PluggySync', $e);
    http_response_code(422);
    echo json_encode(
        ['ok' => false, 'message' => pluggyMensagemPublica($e)],
        JSON_UNESCAPED_UNICODE
    );
}
