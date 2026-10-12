<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/vendas_service.php';

exigirLogin();
exigirPost();
validarCsrf();
$pdo = conectar();
$usuarioId = (int) usuarioLogadoId();
$acao = (string) ($_POST['acao'] ?? '');
$retorno = ($_POST['retorno'] ?? '') === 'vendas.php' ? 'vendas.php' : 'receitas.php';

try {
    if (!cp17IniciarFormulario($pdo, $usuarioId, 'receitas')) {
        $_SESSION['erro_receita'] =
            'Esta operação já foi processada. Confira a lista antes de criar outra.';
        header('Location: ../pages/' . $retorno);
        exit();
    }
    if ($acao === 'excluir') {
        // A venda é a origem da receita: não pode ser desfeita parcialmente aqui.
        $stmt = $pdo->prepare(
            'DELETE FROM receitas
                WHERE
                    id = :id
                    AND usuario_id = :uid
                    AND venda_id IS NULL
                    AND NOT EXISTS (
                        SELECT
                            1
                        FROM
                            vendas v
                        WHERE
                            v.receita_id = receitas.id
                            AND v.usuario_id = receitas.usuario_id
                    )'
        );
        $stmt->execute(['id' => (int) ($_POST['id'] ?? 0), 'uid' => $usuarioId]);
    } elseif (in_array($acao, ['criar', 'editar'], true)) {
        $descricao = trim((string) ($_POST['descricao'] ?? ''));
        $valor = cpNormalizarValorFinanceiro($_POST['valor'] ?? null);
        $categoriaId = !empty($_POST['categoria_id']) ? (int) $_POST['categoria_id'] : null;
        $contaId = !empty($_POST['conta_id']) ? (int) $_POST['conta_id'] : null;
        $data = (string) ($_POST['data_receita'] ?? date('Y-m-d'));
        if (
            !cpValidarDataFinanceira($data) ||
            !validarVinculos($pdo, $usuarioId, $categoriaId, $contaId, 'receita')
        ) {
            throw new InvalidArgumentException('Confira a data, a categoria e a conta da receita.');
        }
        $itemId =
            $acao === 'criar' && usuarioLogadoTipo() === 'mei'
                ? (int) ($_POST['item_venda_id'] ?? 0)
                : 0;
        if ($itemId > 0) {
            cpRegistrarVenda(
                $pdo,
                $usuarioId,
                [$itemId => $_POST['quantidade_venda'] ?? 1],
                $data,
                $contaId
            );
            $_SESSION['mensagem_venda'] = 'Venda registrada e estoque atualizado.';
        } else {
            if ($descricao === '' || $valor === null || $valor <= 0) {
                throw new InvalidArgumentException('Informe descrição e valor positivo.');
            }
            $parametros = [
                'uid' => $usuarioId,
                'cat' => $categoriaId,
                'conta' => $contaId,
                'valor' => $valor,
                'descricao' => mb_substr($descricao, 0, 180),
                'data' => $data
            ];
            if ($acao === 'criar') {
                $stmt = $pdo->prepare(
                    'INSERT INTO
                            receitas (
                                usuario_id,
                                categoria_id,
                                conta_id,
                                valor,
                                descricao,
                                data_receita
                            )
                        VALUES
                            (:uid, :cat, :conta, :valor, :descricao, :data)'
                );
            } else {
                $parametros['id'] = (int) ($_POST['id'] ?? 0);
                $stmt = $pdo->prepare(
                    'UPDATE receitas
                        SET
                            categoria_id = :cat,
                            conta_id = :conta,
                            valor = :valor,
                            descricao = :descricao,
                            data_receita = :data
                        WHERE
                            id = :id
                            AND usuario_id = :uid
                            AND venda_id IS NULL
                            AND NOT EXISTS (
                                SELECT
                                    1
                                FROM
                                    vendas v
                                WHERE
                                    v.receita_id = receitas.id
                                    AND v.usuario_id = receitas.usuario_id
                            )'
                );
            }
            $stmt->execute($parametros);
        }
    }
    $pdo->commit();
} catch (InvalidArgumentException $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['erro_receita'] = $erro->getMessage();
    $_SESSION['mensagem_venda'] = $erro->getMessage();
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    cp17Log('Receitas', $erro);
    $_SESSION['erro_receita'] = 'Não foi possível salvar a receita. Verifique os dados.';
}
header('Location: ../pages/' . $retorno);
exit();
