<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';

exigirLogin();
exigirPost();
validarCsrf();

$pdo = conectar();
$uid = (int) usuarioLogadoId();
$acao = (string) ($_POST['acao'] ?? 'importar');
$selecionadas = array_keys(is_array($_POST['selecionar'] ?? null) ? $_POST['selecionar'] : []);
sort($selecionadas);
$tipos = is_array($_POST['tipo'] ?? null) ? $_POST['tipo'] : [];
$confirmarDuplicadas = is_array($_POST['confirmar_duplicada'] ?? null)
    ? $_POST['confirmar_duplicada']
    : [];

if (!$selecionadas) {
    $_SESSION['open_finance_erro'] = 'Selecione pelo menos uma movimentação.';
    header('Location: ../pages/open_finance.php#movimentacoes');
    exit();
}

try {
    $pdo->beginTransaction();
    $quantidade = 0;
    $duplicadasBloqueadas = 0;

    foreach ($selecionadas as $txId) {
        if (!is_string($txId) || !preg_match('/^[a-f0-9-]{20,50}$/i', $txId)) {
            continue;
        }

        $stmt = $pdo->prepare(
            'SELECT
                    pt.*,
                    pa.conta_id
                FROM
                    pluggy_transactions pt
                    INNER JOIN pluggy_accounts pa ON pa.pluggy_account_id = pt.pluggy_account_id
                    AND pa.usuario_id = pt.usuario_id
                WHERE
                    pt.usuario_id = :uid
                    AND pt.pluggy_transaction_id = :tx
                    AND pt.importada = 0
                LIMIT
                    1 FOR
                UPDATE'
        );
        $stmt->execute(['uid' => $uid, 'tx' => $txId]);
        $tx = $stmt->fetch();
        if (!$tx) {
            continue;
        }

        if ($acao === 'ignorar') {
            $stmt = $pdo->prepare(
                'UPDATE pluggy_transactions SET ignorada = 1 WHERE id = :id AND usuario_id = :uid'
            );
            $stmt->execute(['id' => $tx['id'], 'uid' => $uid]);
            $quantidade++;
            continue;
        }

        $tipo = (string) ($tipos[$txId] ?? $tx['tipo_sugerido']);
        if (!in_array($tipo, ['receita', 'despesa'], true)) {
            $tipo = (string) $tx['tipo_sugerido'];
        }

        $valor = abs((float) $tx['valor']);
        if ($valor <= 0) {
            continue;
        }

        $tabelaDuplicada = $tipo === 'receita' ? 'receitas' : 'despesas';
        $dataDuplicada = $tipo === 'receita' ? 'data_receita' : 'data_despesa';
        $stmtDuplicada = $pdo->prepare(
            "SELECT 1
                FROM {$tabelaDuplicada}
                WHERE usuario_id = :uid
                    AND {$dataDuplicada} = :data
                    AND ABS(valor - :valor) < 0.01
                    AND (conta_id = :conta OR (:conta_nula = 1 AND conta_id IS NULL))
                LIMIT 1"
        );
        $stmtDuplicada->execute([
            'uid' => $uid,
            'data' => $tx['data_transacao'],
            'valor' => $valor,
            'conta' => $tx['conta_id'] ? (int) $tx['conta_id'] : null,
            'conta_nula' => $tx['conta_id'] ? 0 : 1
        ]);
        if ($stmtDuplicada->fetchColumn() && empty($confirmarDuplicadas[$txId])) {
            $duplicadasBloqueadas++;
            continue;
        }

        $status =
            strtoupper((string) $tx['status_externo']) === 'POSTED' ? 'efetivado' : 'pendente';
        $descricao = mb_substr((string) $tx['descricao'], 0, 180);
        $observacao = 'Importado via Open Finance · Pluggy';
        $contaId = $tx['conta_id'] ? (int) $tx['conta_id'] : null;

        if ($tipo === 'receita') {
            $stmt = $pdo->prepare(
                'INSERT INTO
                        receitas (
                            usuario_id,
                            categoria_id,
                            conta_id,
                            valor,
                            descricao,
                            observacao,
                            status,
                            data_receita
                        )
                    VALUES
                        (
                            :uid,
                            NULL,
                            :conta,
                            :valor,
                            :descricao,
                            :observacao,
                            :status,
                            :data
                        )'
            );
            $stmt->execute([
                'uid' => $uid,
                'conta' => $contaId,
                'valor' => $valor,
                'descricao' => $descricao,
                'observacao' => $observacao,
                'status' => $status,
                'data' => $tx['data_transacao']
            ]);
            $receitaId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare(
                'UPDATE pluggy_transactions
                    SET
                        importada = 1,
                        ignorada = 0,
                        tipo_sugerido = :tipo,
                        receita_id = :rid,
                        despesa_id = NULL
                    WHERE
                        id = :id
                        AND usuario_id = :uid'
            );
            $stmt->execute([
                'tipo' => $tipo,
                'rid' => $receitaId,
                'id' => $tx['id'],
                'uid' => $uid
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO
                        despesas (
                            usuario_id,
                            categoria_id,
                            conta_id,
                            valor,
                            descricao,
                            observacao,
                            status,
                            origem_tipo,
                            origem_id,
                            data_despesa
                        )
                    VALUES
                        (
                            :uid,
                            NULL,
                            :conta,
                            :valor,
                            :descricao,
                            :observacao,
                            :status,
                            "open_finance",
                            :origem_id,
                            :data
                        )'
            );
            $stmt->execute([
                'uid' => $uid,
                'conta' => $contaId,
                'valor' => $valor,
                'descricao' => $descricao,
                'observacao' => $observacao,
                'status' => $status,
                'origem_id' => (int) $tx['id'],
                'data' => $tx['data_transacao']
            ]);
            $despesaId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare(
                'UPDATE pluggy_transactions
                    SET
                        importada = 1,
                        ignorada = 0,
                        tipo_sugerido = :tipo,
                        despesa_id = :did,
                        receita_id = NULL
                    WHERE
                        id = :id
                        AND usuario_id = :uid'
            );
            $stmt->execute([
                'tipo' => $tipo,
                'did' => $despesaId,
                'id' => $tx['id'],
                'uid' => $uid
            ]);
        }

        $quantidade++;
    }

    $pdo->commit();
    $_SESSION['open_finance_ok'] =
        $acao === 'ignorar'
            ? $quantidade . ' movimentação(ões) ignorada(s).'
            : $quantidade .
                ' movimentação(ões) importada(s) para o CashPilot.' .
                ($duplicadasBloqueadas > 0
                    ? ' ' .
                        $duplicadasBloqueadas .
                        ' possível(is) duplicidade(s) foi(ram) bloqueada(s).'
                    : '');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    cp17Log('PluggyImport', $e);
    $_SESSION['open_finance_erro'] = 'Não foi possível concluir a importação.';
}

header('Location: ../pages/open_finance.php#movimentacoes');
exit();
