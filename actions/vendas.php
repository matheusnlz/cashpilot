<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/vendas_service.php';

exigirLogin();
exigirPost();
validarCsrf();
if (usuarioLogadoTipo() !== 'mei') {
    header('Location: ../pages/dashboard.php');
    exit();
}
$pdo = conectar();
try {
    if (!cp17IniciarFormulario($pdo, (int) usuarioLogadoId(), 'vendas')) {
        $_SESSION['mensagem_venda'] = 'Esta venda já foi processada. Confira a lista.';
        header('Location: ../pages/vendas.php');
        exit();
    }
    $itens = is_array($_POST['itens'] ?? null) ? $_POST['itens'] : [];
    cpRegistrarVenda(
        conectar(),
        (int) usuarioLogadoId(),
        $itens,
        (string) ($_POST['data_venda'] ?? date('Y-m-d')),
        !empty($_POST['conta_id']) ? (int) $_POST['conta_id'] : null,
        trim((string) ($_POST['observacao'] ?? '')) ?: null
    );
    $pdo->commit();
    $_SESSION['mensagem_venda'] = 'Venda registrada e estoque atualizado.';
} catch (InvalidArgumentException $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['mensagem_venda'] = $erro->getMessage();
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    cp17Log('Vendas', $erro);
    $_SESSION['mensagem_venda'] =
        'Não foi possível registrar a venda. Nenhuma alteração foi salva.';
}
header('Location: ../pages/vendas.php');
exit();
