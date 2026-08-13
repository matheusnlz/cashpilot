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
    $stmt = $pdo->prepare('DELETE FROM receitas WHERE id = :id AND usuario_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $usuarioId]);
    header('Location: ../pages/receitas.php');
    exit;
}

if ($acao === 'criar' || $acao === 'editar') {
    $descricao   = trim($_POST['descricao'] ?? '');
    $valor       = (float) str_replace(',', '.', $_POST['valor'] ?? '0');
    $categoriaId = !empty($_POST['categoria_id']) ? (int) $_POST['categoria_id'] : null;
    $contaId     = !empty($_POST['conta_id']) ? (int) $_POST['conta_id'] : null;
    $data        = $_POST['data_receita'] ?? date('Y-m-d');

    if ($descricao === '' || $valor <= 0 || !validarVinculos($pdo, $usuarioId, $categoriaId, $contaId, 'receita')) {
        header('Location: ../pages/receitas.php');
        exit;
    }

    if ($acao === 'criar') {
        $stmt = $pdo->prepare(
            'INSERT INTO receitas (usuario_id, categoria_id, conta_id, valor, descricao, data_receita)
             VALUES (:uid, :cat, :conta, :valor, :descricao, :data)'
        );
        $stmt->execute([
            'uid' => $usuarioId, 'cat' => $categoriaId, 'conta' => $contaId, 'valor' => $valor,
            'descricao' => $descricao, 'data' => $data,
        ]);
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare(
            'UPDATE receitas SET categoria_id = :cat, conta_id = :conta, valor = :valor, descricao = :descricao, data_receita = :data
             WHERE id = :id AND usuario_id = :uid'
        );
        $stmt->execute([
            'cat' => $categoriaId, 'conta' => $contaId, 'valor' => $valor, 'descricao' => $descricao,
            'data' => $data, 'id' => $id, 'uid' => $usuarioId,
        ]);
    }
}

function validarVinculos(PDO $pdo, int $usuarioId, ?int $categoriaId, ?int $contaId, string $tipo): bool
{
    if ($categoriaId !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM categorias WHERE id = :id AND usuario_id = :uid AND tipo = :tipo');
        $stmt->execute(['id' => $categoriaId, 'uid' => $usuarioId, 'tipo' => $tipo]);
        if (!$stmt->fetchColumn()) return false;
    }
    if ($contaId !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM contas WHERE id = :id AND usuario_id = :uid');
        $stmt->execute(['id' => $contaId, 'uid' => $usuarioId]);
        if (!$stmt->fetchColumn()) return false;
    }
    return true;
}

header('Location: ../pages/receitas.php');
exit;
