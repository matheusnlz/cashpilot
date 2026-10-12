<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

exigirLogin();
exigirPost();

validarCsrf();

if (usuarioLogadoTipo() === 'mei') {
    header('Location: ../pages/dashboard.php');

    exit();
}

$pdo = conectar();

$uid = usuarioLogadoId();

$acao = $_POST['acao'] ?? '';

function nmeta(mixed $valor): ?float
{
    return cpNormalizarValorFinanceiro($valor);
}

try {
    if ($acao === 'criar') {
        $titulo = cpTextoLimitado($_POST['titulo'] ?? '', 140);

        $meta = nmeta($_POST['valor_meta'] ?? null);

        $atual = nmeta($_POST['valor_atual'] ?? 0);

        $prazo = !empty($_POST['prazo']) ? (string) $_POST['prazo'] : null;

        if (
            $titulo === '' ||
            $meta === null ||
            $meta <= 0 ||
            $atual === null ||
            ($prazo !== null && !cpValidarDataFinanceira($prazo))
        ) {
            throw new InvalidArgumentException('Confira o nome, os valores e o prazo da meta.');
        }

        $pdo->beginTransaction();

        $s = $pdo->prepare(
            'INSERT INTO
                    metas (
                        usuario_id,
                        titulo,
                        valor_meta,
                        valor_atual,
                        prazo,
                        concluida
                    )
                VALUES
                    (:uid, :titulo, :meta, :atual, :prazo, :c)'
        );

        $s->execute([
            'uid' => $uid,
            'titulo' => $titulo,
            'meta' => $meta,
            'atual' => $atual,
            'prazo' => $prazo,
            'c' => $atual >= $meta ? 1 : 0
        ]);

        $id = (int) $pdo->lastInsertId();

        if ($atual > 0) {
            $m = $pdo->prepare(
                'INSERT INTO
                        meta_movimentacoes (
                            meta_id,
                            usuario_id,
                            tipo,
                            valor,
                            observacao,
                            data_movimentacao
                        )
                    VALUES
                        (
                            :mid,
                            :uid,
                            "aporte",
                            :valor,
                            "Valor inicial",
                            :data
                        )'
            );

            $m->execute(['mid' => $id, 'uid' => $uid, 'valor' => $atual, 'data' => date('Y-m-d')]);
        }

        $pdo->commit();
        $_SESSION['mensagem_metas'] = 'Meta criada com sucesso.';
    }

    if (in_array($acao, ['aporte', 'retirada'], true)) {
        $id = (int) ($_POST['id'] ?? 0);

        $valor = nmeta($_POST['valor'] ?? null);

        $obs = cpTextoLimitado($_POST['observacao'] ?? '', 180);

        $data = (string) ($_POST['data_movimentacao'] ?? date('Y-m-d'));

        if ($valor === null || $valor <= 0 || !cpValidarDataFinanceira($data)) {
            throw new InvalidArgumentException('Informe um valor e uma data válidos.');
        }

        $pdo->beginTransaction();
        $s = $pdo->prepare(
            'SELECT valor_meta,valor_atual FROM metas WHERE id=:id AND usuario_id=:uid FOR UPDATE'
        );

        $s->execute(['id' => $id, 'uid' => $uid]);

        $meta = $s->fetch();
        if (!$meta) {
            throw new InvalidArgumentException('Meta não encontrada.');
        }

        if ($acao === 'retirada') {
            $valor = min($valor, max(0, (float) $meta['valor_atual']));
            if ($valor <= 0) {
                throw new InvalidArgumentException('Não há valor disponível para retirada.');
            }
        }

        $novo =
            $acao === 'aporte'
                ? (float) $meta['valor_atual'] + $valor
                : max(0, (float) $meta['valor_atual'] - $valor);

        $pdo->prepare(
            'UPDATE metas SET valor_atual=:v,concluida=:c WHERE id=:id AND usuario_id=:uid'
        )->execute([
            'v' => $novo,
            'c' => $novo >= (float) $meta['valor_meta'] ? 1 : 0,
            'id' => $id,
            'uid' => $uid
        ]);

        $pdo->prepare(
            'INSERT INTO
                    meta_movimentacoes (
                        meta_id,
                        usuario_id,
                        tipo,
                        valor,
                        observacao,
                        data_movimentacao
                    )
                VALUES
                    (:mid, :uid, :tipo, :valor, :obs, :data)'
        )->execute([
            'mid' => $id,
            'uid' => $uid,
            'tipo' => $acao,
            'valor' => $valor,
            'obs' => $obs ?: null,
            'data' => $data
        ]);

        $pdo->commit();
        $_SESSION['mensagem_metas'] =
            $acao === 'aporte' ? 'Aporte registrado.' : 'Retirada registrada.';
    }

    if ($acao === 'excluir') {
        $pdo->prepare('DELETE FROM metas WHERE id=:id AND usuario_id=:uid')->execute([
            'id' => (int) ($_POST['id'] ?? 0),
            'uid' => $uid
        ]);
        $_SESSION['mensagem_metas'] = 'Meta excluída.';
    }
} catch (InvalidArgumentException $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['mensagem_metas'] = $erro->getMessage();
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('CashPilot/Metas: ' . $erro->getMessage());
    $_SESSION['mensagem_metas'] = 'Não foi possível concluir a operação com a meta.';
}

$ret = (int) ($_POST['id'] ?? 0);

header('Location: ../pages/metas.php' . ($ret ? '?meta=' . $ret : ''));

exit();
