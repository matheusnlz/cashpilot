<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();
validarCsrf();

$pdo = conectar();
$usuarioId = usuarioLogadoId();
$acao = $_POST['acao'] ?? '';

if ($acao === 'excluir') {
    $id = (int) ($_POST['id'] ?? 0);
    // Não permite excluir categorias padrão do sistema
    $stmt = $pdo->prepare('DELETE FROM categorias WHERE id = :id AND usuario_id = :uid AND padrao = 0');
    $stmt->execute(['id' => $id, 'uid' => $usuarioId]);
}

if ($acao === 'criar') {
    $nome = trim($_POST['nome'] ?? '');
    $tipo = $_POST['tipo'] ?? 'despesa';
    $cor  = $_POST['cor'] ?? '#2F5D62';

    if (!in_array($tipo, ['receita', 'despesa'], true)) {
        $tipo = 'despesa';
    }

    if ($nome !== '') {
        $stmt = $pdo->prepare(
            'INSERT INTO categorias (usuario_id, nome, tipo, cor, padrao) VALUES (:uid, :nome, :tipo, :cor, 0)'
        );
        $stmt->execute(['uid' => $usuarioId, 'nome' => $nome, 'tipo' => $tipo, 'cor' => $cor]);
    }
}

header('Location: ../pages/categorias.php');
exit;
