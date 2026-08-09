<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$pdo = conectar();
$usuarioId = usuarioLogadoId();
$acao = $_POST['acao'] ?? '';

if ($acao === 'excluir') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM receitas WHERE id = :id AND usuario_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $usuarioId]);
    header('Location: ../pages/receitas.php');
    exit;
}

if ($acao === 'criar' || $acao === 'editar') {
    $descricao   = trim($_POST['descricao'] ?? '');
    $valor       = (float) str_replace(',', '.', $_POST['valor'] ?? '0');
    $categoriaId = !empty($_POST['categoria_id']) ? (int) $_POST['categoria_id'] : null;
    $data        = $_POST['data_receita'] ?? date('Y-m-d');

    if ($descricao === '' || $valor <= 0) {
        header('Location: ../pages/receitas.php');
        exit;
    }

    if ($acao === 'criar') {
        $stmt = $pdo->prepare(
            'INSERT INTO receitas (usuario_id, categoria_id, valor, descricao, data_receita)
             VALUES (:uid, :cat, :valor, :descricao, :data)'
        );
        $stmt->execute([
            'uid' => $usuarioId, 'cat' => $categoriaId, 'valor' => $valor,
            'descricao' => $descricao, 'data' => $data,
        ]);
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare(
            'UPDATE receitas SET categoria_id = :cat, valor = :valor, descricao = :descricao, data_receita = :data
             WHERE id = :id AND usuario_id = :uid'
        );
        $stmt->execute([
            'cat' => $categoriaId, 'valor' => $valor, 'descricao' => $descricao,
            'data' => $data, 'id' => $id, 'uid' => $usuarioId,
        ]);
    }
}

header('Location: ../pages/receitas.php');
exit;
