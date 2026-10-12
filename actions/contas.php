<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

exigirLogin();
exigirPost();
validarCsrf();

$pdo = conectar();

$uid = (int) usuarioLogadoId();

$acao = $_POST['acao'] ?? '';

if ($acao === 'criar') {
    $nome = cpTextoLimitado($_POST['nome'] ?? '', 80);

    $tipo = in_array(
        $_POST['tipo'] ?? '',
        ['corrente', 'poupanca', 'carteira', 'empresarial', 'outra'],
        true
    )
        ? $_POST['tipo']
        : 'corrente';

    $saldo = cpNormalizarValorFinanceiro($_POST['saldo_inicial'] ?? null);

    if ($nome !== '' && $saldo !== null) {
        $stmt = $pdo->prepare(
            'INSERT INTO
                    contas (usuario_id, nome, tipo, saldo_inicial, padrao)
                VALUES
                    (
                        :uid,
                        :nome,
                        :tipo,
                        :saldo,
                        IF(
                            (
                                SELECT
                                    COUNT(*)
                                FROM
                                    contas c
                                WHERE
                                    c.usuario_id = :uid2
                            ) = 0,
                            1,
                            0
                        )
                    )'
        );

        $stmt->execute([
            'uid' => $uid,
            'uid2' => $uid,
            'nome' => $nome,
            'tipo' => $tipo,
            'saldo' => $saldo
        ]);
    } else {
        $_SESSION['mensagem_contas'] = 'Confira o nome e o saldo inicial da conta.';
    }
}

if ($acao === 'excluir') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT padrao FROM contas WHERE id = :id AND usuario_id = :uid');

    $stmt->execute(['id' => $id, 'uid' => $uid]);

    $conta = $stmt->fetch();

    if ($conta && !(int) $conta['padrao']) {
        $stmt = $pdo->prepare('DELETE FROM contas WHERE id = :id AND usuario_id = :uid');

        $stmt->execute(['id' => $id, 'uid' => $uid]);
    } else {
        $_SESSION['mensagem_contas'] = 'A conta principal não pode ser excluída.';
    }
}

header('Location: ../pages/contas.php');

exit();
