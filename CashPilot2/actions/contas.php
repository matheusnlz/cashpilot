<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();
}

$pdo = conectar();
$uid = usuarioLogadoId();
$acao = $_POST['acao'] ?? '';

if ($acao === 'criar') {
    $nome = trim($_POST['nome'] ?? '');
    $tipo = in_array($_POST['tipo'] ?? '', ['corrente', 'poupanca', 'carteira', 'empresarial', 'outra'], true)
        ? $_POST['tipo']
        : 'corrente';
    $saldo = (float) str_replace(',', '.', $_POST['saldo_inicial'] ?? '0');

    if ($nome !== '' && $saldo >= 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO contas (usuario_id, nome, tipo, saldo_inicial, padrao)
             VALUES (:uid, :nome, :tipo, :saldo,
                     IF((SELECT COUNT(*) FROM contas c WHERE c.usuario_id = :uid2) = 0, 1, 0))'
        );
        $stmt->execute([
            'uid' => $uid,
            'uid2' => $uid,
            'nome' => $nome,
            'tipo' => $tipo,
            'saldo' => $saldo,
        ]);
    }
}

if ($acao === 'excluir') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT padrao FROM contas WHERE id = :id AND usuario_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $uid]);
    $conta = $stmt->fetch();

    if ($conta && !$conta['padrao']) {
        $stmt = $pdo->prepare('DELETE FROM contas WHERE id = :id AND usuario_id = :uid');
        $stmt->execute(['id' => $id, 'uid' => $uid]);
    }
}

header('Location: ../pages/contas.php');
exit;
