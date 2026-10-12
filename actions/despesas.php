<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

exigirLogin();
exigirPost();

validarCsrf();

$pdo = conectar();

$usuarioId = (int) usuarioLogadoId();

$acao = $_POST['acao'] ?? '';

try {
    if (!cp17IniciarFormulario($pdo, $usuarioId, 'despesas')) {
        $_SESSION['erro_despesa'] =
            'Esta operação já foi processada. Confira a lista antes de criar outra.';
        header('Location: ../pages/despesas.php');
        exit();
    }
    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare(
            'DELETE FROM despesas WHERE id = :id AND usuario_id = :uid AND origem_tipo = "manual"'
        );
        $stmt->execute(['id' => $id, 'uid' => $usuarioId]);
    }

    if ($acao === 'criar' || $acao === 'editar') {
        $descricao = cpTextoLimitado($_POST['descricao'] ?? '', 180);
        $valor = cpNormalizarValorFinanceiro($_POST['valor'] ?? null);

        $categoriaId = !empty($_POST['categoria_id']) ? (int) $_POST['categoria_id'] : null;

        $contaId = !empty($_POST['conta_id']) ? (int) $_POST['conta_id'] : null;

        $data = (string) ($_POST['data_despesa'] ?? date('Y-m-d'));

        if (
            $descricao === '' ||
            $valor === null ||
            $valor <= 0 ||
            !cpValidarDataFinanceira($data) ||
            !validarVinculos($pdo, $usuarioId, $categoriaId, $contaId, 'despesa')
        ) {
            throw new InvalidArgumentException(
                'Confira a descrição, o valor, a data e os vínculos.'
            );
        }

        if ($acao === 'criar') {
            $stmt = $pdo->prepare(
                'INSERT INTO
                    despesas (
                        usuario_id,
                        categoria_id,
                        conta_id,
                        valor,
                        descricao,
                        data_despesa
                    )
                VALUES
                    (:uid, :cat, :conta, :valor, :descricao, :data)'
            );

            $stmt->execute([
                'uid' => $usuarioId,
                'cat' => $categoriaId,
                'conta' => $contaId,
                'valor' => $valor,
                'descricao' => $descricao,
                'data' => $data
            ]);
        } else {
            $id = (int) ($_POST['id'] ?? 0);

            $stmt = $pdo->prepare(
                'UPDATE despesas
                SET
                    categoria_id = :cat,
                    conta_id = :conta,
                    valor = :valor,
                    descricao = :descricao,
                    data_despesa = :data
                WHERE
                    id = :id
                    AND usuario_id = :uid
                    AND origem_tipo = "manual"'
            );

            $stmt->execute([
                'cat' => $categoriaId,
                'conta' => $contaId,
                'valor' => $valor,
                'descricao' => $descricao,
                'data' => $data,
                'id' => $id,
                'uid' => $usuarioId
            ]);
        }
    }
    $pdo->commit();
} catch (InvalidArgumentException $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['erro_despesa'] = $erro->getMessage();
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    cp17Log('Despesas', $erro);
    $_SESSION['erro_despesa'] = 'Não foi possível salvar a despesa.';
}

header('Location: ../pages/despesas.php');

exit();
