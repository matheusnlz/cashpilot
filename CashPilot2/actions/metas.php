<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();
validarCsrf();

$pdo = conectar();
$usuarioId = usuarioLogadoId();
$acao = $_POST['acao'] ?? '';

if ($acao === 'criar') {
    $titulo     = trim($_POST['titulo'] ?? '');
    $valorMeta  = (float) str_replace(',', '.', $_POST['valor_meta'] ?? '0');
    $valorAtual = (float) str_replace(',', '.', $_POST['valor_atual'] ?? '0');
    $prazo      = !empty($_POST['prazo']) ? $_POST['prazo'] : null;

    if ($titulo !== '' && $valorMeta > 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO metas (usuario_id, titulo, valor_meta, valor_atual, prazo, concluida)
             VALUES (:uid, :titulo, :meta, :atual, :prazo, :concluida)'
        );
        $stmt->execute([
            'uid' => $usuarioId, 'titulo' => $titulo, 'meta' => $valorMeta,
            'atual' => $valorAtual, 'prazo' => $prazo,
            'concluida' => $valorAtual >= $valorMeta ? 1 : 0,
        ]);
    }
}

if ($acao === 'atualizar_valor') {
    $id = (int) ($_POST['id'] ?? 0);
    $valorAtual = (float) str_replace(',', '.', $_POST['valor_atual'] ?? '0');

    $stmtMeta = $pdo->prepare('SELECT valor_meta FROM metas WHERE id = :id AND usuario_id = :uid');
    $stmtMeta->execute(['id' => $id, 'uid' => $usuarioId]);
    $meta = $stmtMeta->fetch();

    if ($meta) {
        $concluida = $valorAtual >= $meta['valor_meta'] ? 1 : 0;
        $stmt = $pdo->prepare('UPDATE metas SET valor_atual = :valor, concluida = :concluida WHERE id = :id AND usuario_id = :uid');
        $stmt->execute(['valor' => $valorAtual, 'concluida' => $concluida, 'id' => $id, 'uid' => $usuarioId]);
    }
}

if ($acao === 'excluir') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM metas WHERE id = :id AND usuario_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $usuarioId]);
}

header('Location: ../pages/metas.php');
exit;
