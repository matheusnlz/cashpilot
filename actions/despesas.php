<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

exigirLogin();
exigirPost();

validarCsrf();

$pdo = conectar();

$usuarioId = usuarioLogadoId();

$acao = $_POST['acao'] ?? '';

if ($acao === 'excluir') {

        $id = (int) ($_POST['id'] ?? 0);

        $stmt = $pdo->prepare('DELETE FROM despesas WHERE id = :id AND usuario_id = :uid AND origem_tipo = "manual"');

        $stmt->execute(['id' => $id, 'uid' => $usuarioId]);

        header('Location: ../pages/despesas.php');

        exit;

}

if ($acao === 'criar' || $acao === 'editar') {

        $descricao   = trim($_POST['descricao'] ?? '');

        $valor       = (float) str_replace(',', '.', $_POST['valor'] ?? '0');

        $categoriaId = !empty($_POST['categoria_id']) ? (int) $_POST['categoria_id'] : null;

        $contaId     = !empty($_POST['conta_id']) ? (int) $_POST['conta_id'] : null;

        $data        = $_POST['data_despesa'] ?? date('Y-m-d');

        if ($descricao === '' || $valor <= 0 || !validarVinculos($pdo, $usuarioId, $categoriaId, $contaId, 'despesa')) {

                header('Location: ../pages/despesas.php');

                exit;

    }

        if ($acao === 'criar') {

                $stmt = $pdo->prepare(
                    'INSERT INTO despesas (usuario_id, categoria_id, conta_id, valor, descricao, data_despesa)
             VALUES (:uid, :cat, :conta, :valor, :descricao, :data)'
                );

                $stmt->execute([
                    'uid' => $usuarioId, 'cat' => $categoriaId, 'conta' => $contaId, 'valor' => $valor,
                    'descricao' => $descricao, 'data' => $data,
                ]);

    }  else {

                $id = (int) ($_POST['id'] ?? 0);

                $stmt = $pdo->prepare(
                    'UPDATE despesas SET categoria_id = :cat, conta_id = :conta, valor = :valor, descricao = :descricao, data_despesa = :data
             WHERE id = :id AND usuario_id = :uid AND origem_tipo = "manual"'
                );

                $stmt->execute([
                    'cat' => $categoriaId, 'conta' => $contaId, 'valor' => $valor, 'descricao' => $descricao,
                    'data' => $data, 'id' => $id, 'uid' => $usuarioId,
                ]);

    }

}

header('Location: ../pages/despesas.php');

exit;
