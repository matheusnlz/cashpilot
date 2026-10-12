<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/cashpilot14_financeiro.php';

exigirLogin();
exigirPost();

validarCsrf();

if (usuarioLogadoTipo() !== 'pessoa_fisica') {
    header('Location: ../pages/dashboard.php');

    exit();
}

$pdo = conectar();

$usuarioId = (int) usuarioLogadoId();

$acao = (string) ($_POST['acao'] ?? '');

function cp14Numero($valor): float
{
    $numero = cpNormalizarValorFinanceiro($valor);
    if ($numero === null) {
        throw new InvalidArgumentException('Informe um valor financeiro válido.');
    }

    return $numero;
}

function cp14RetornarInvestimentos(string $mensagem, ?int $investimentoId = null): never
{
    $_SESSION['mensagem_investimentos'] = $mensagem;

    $url = '../pages/investimentos.php';

    if ($investimentoId) {
        $url .= '?investimento=' . $investimentoId;
    }

    header('Location: ' . $url);

    exit();
}

if (!cp14TabelaExiste($pdo, 'investimentos')) {
    cp14RetornarInvestimentos(
        'Investimentos ainda não estão disponíveis nesta instalação. Procure o responsável pelo CashPilot.'
    );
}

try {
    if ($acao === 'criar') {
        $nome = cpTextoLimitado($_POST['nome'] ?? '', 140);

        $classe = (string) ($_POST['classe'] ?? 'outros');

        $classesPermitidas = [
            'renda_fixa',
            'tesouro',
            'acoes',
            'fiis',
            'etfs',
            'fundos',
            'cripto',
            'poupanca',
            'outros'
        ];

        if (!in_array($classe, $classesPermitidas, true)) {
            $classe = 'outros';
        }

        $aplicado = cp14Numero($_POST['valor_aplicado'] ?? 0);

        $atual = cp14Numero($_POST['valor_atual'] ?? $aplicado);

        if ($nome === '' || $aplicado < 0) {
            cp14RetornarInvestimentos('Informe os dados básicos do investimento.');
        }

        $metaId = (int) ($_POST['meta_id'] ?? 0);

        if ($metaId <= 0) {
            $metaId = null;
        }

        if ($metaId !== null) {
            $meta = $pdo->prepare('SELECT id FROM metas WHERE id = :id AND usuario_id = :uid');
            $meta->execute(['id' => $metaId, 'uid' => $usuarioId]);
            if (!$meta->fetchColumn()) {
                cp14RetornarInvestimentos('A meta selecionada não pertence à sua conta.');
            }
        }
        $dataInicio = !empty($_POST['data_inicio']) ? (string) $_POST['data_inicio'] : null;
        if ($dataInicio !== null && !cpValidarDataFinanceira($dataInicio)) {
            cp14RetornarInvestimentos('Informe uma data de início válida.');
        }

        $quantidade =
            ($_POST['quantidade'] ?? '') !== ''
                ? cpNormalizarValorFinanceiro($_POST['quantidade'])
                : null;
        if (($_POST['quantidade'] ?? '') !== '' && $quantidade === null) {
            cp14RetornarInvestimentos('Informe uma quantidade válida.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO
                    investimentos (
                        usuario_id,
                        meta_id,
                        nome,
                        classe,
                        subtipo,
                        instituicao,
                        quantidade,
                        preco_medio,
                        valor_aplicado,
                        valor_atual,
                        incluido_no_saldo,
                        data_inicio,
                        observacao
                    )
                VALUES
                    (
                        :uid,
                        :meta,
                        :nome,
                        :classe,
                        :subtipo,
                        :instituicao,
                        :quantidade,
                        :preco_medio,
                        :aplicado,
                        :atual,
                        :incluido,
                        :data_inicio,
                        :observacao
                    )'
        );

        $stmt->execute([
            'uid' => $usuarioId,
            'meta' => $metaId,
            'nome' => $nome,
            'classe' => $classe,
            'subtipo' => cpTextoLimitado($_POST['subtipo'] ?? '', 100) ?: null,
            'instituicao' => cpTextoLimitado($_POST['instituicao'] ?? '', 120) ?: null,
            'quantidade' => $quantidade,
            'preco_medio' =>
                ($_POST['preco_medio'] ?? '') !== '' ? cp14Numero($_POST['preco_medio']) : null,
            'aplicado' => $aplicado,
            'atual' => $atual,
            'incluido' => ($_POST['incluido_no_saldo'] ?? '1') === '0' ? 0 : 1,
            'data_inicio' => $dataInicio,
            'observacao' => cpTextoLimitado($_POST['observacao'] ?? '', 255) ?: null
        ]);

        $investimentoId = (int) $pdo->lastInsertId();

        if ($aplicado > 0) {
            $stmt = $pdo->prepare(
                'INSERT INTO
                        investimento_movimentacoes (
                            investimento_id,
                            usuario_id,
                            tipo,
                            valor,
                            observacao,
                            data_movimentacao
                        )
                    VALUES
                        (
                            :iid,
                            :uid,
                            "aporte",
                            :valor,
                            "Valor inicial",
                            :data
                        )'
            );

            $stmt->execute([
                'iid' => $investimentoId,
                'uid' => $usuarioId,
                'valor' => $aplicado,
                'data' => $dataInicio ?? date('Y-m-d')
            ]);
        }

        $pdo->commit();
        cp14RetornarInvestimentos('Investimento adicionado à carteira.', $investimentoId);
    }

    if (in_array($acao, ['aporte', 'retirada'], true)) {
        $investimentoId = (int) ($_POST['investimento_id'] ?? 0);

        $valor = cp14Numero($_POST['valor'] ?? 0);

        $dataMovimentacao = (string) ($_POST['data_movimentacao'] ?? date('Y-m-d'));
        if (!cpValidarDataFinanceira($dataMovimentacao)) {
            cp14RetornarInvestimentos('Informe uma data de movimentação válida.', $investimentoId);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT
                    valor_aplicado,
                    valor_atual
                FROM
                    investimentos
                WHERE
                    id = :id
                    AND usuario_id = :uid
                    AND ativo = 1 FOR
                UPDATE'
        );

        $stmt->execute([
            'id' => $investimentoId,
            'uid' => $usuarioId
        ]);

        $investimento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$investimento || $valor <= 0) {
            $pdo->rollBack();

            cp14RetornarInvestimentos(
                'Não foi possível registrar a movimentação.',
                $investimentoId ?: null
            );
        }

        $aplicado = (float) $investimento['valor_aplicado'];

        $atual = (float) $investimento['valor_atual'];

        if ($acao === 'aporte') {
            $novoAplicado = $aplicado + $valor;

            $novoAtual = $atual + $valor;
        } else {
            $valor = min($valor, max(0, $atual));

            if ($valor <= 0) {
                $pdo->rollBack();
                cp14RetornarInvestimentos(
                    'Não há valor disponível para retirada.',
                    $investimentoId
                );
            }

            $novoAtual = max(0, $atual - $valor);

            $proporcao = $atual > 0 ? $valor / $atual : 0;

            $novoAplicado = max(0, $aplicado - $aplicado * $proporcao);
        }

        $stmt = $pdo->prepare(
            'UPDATE investimentos
                SET
                    valor_aplicado = :aplicado,
                    valor_atual = :atual
                WHERE
                    id = :id
                    AND usuario_id = :uid'
        );

        $stmt->execute([
            'aplicado' => $novoAplicado,
            'atual' => $novoAtual,
            'id' => $investimentoId,
            'uid' => $usuarioId
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO
                    investimento_movimentacoes (
                        investimento_id,
                        usuario_id,
                        tipo,
                        valor,
                        observacao,
                        data_movimentacao
                    )
                VALUES
                    (:iid, :uid, :tipo, :valor, :observacao, :data)'
        );

        $stmt->execute([
            'iid' => $investimentoId,
            'uid' => $usuarioId,
            'tipo' => $acao,
            'valor' => $valor,
            'observacao' => cpTextoLimitado($_POST['observacao'] ?? '', 180) ?: null,
            'data' => $dataMovimentacao
        ]);

        $pdo->commit();

        cp14RetornarInvestimentos(
            $acao === 'aporte' ? 'Aporte registrado.' : 'Retirada registrada.',
            $investimentoId
        );
    }

    if ($acao === 'atualizar_valor') {
        $investimentoId = (int) ($_POST['investimento_id'] ?? 0);

        $valorAtual = cp14Numero($_POST['valor_atual'] ?? 0);

        $pdo->beginTransaction();
        $verificar = $pdo->prepare(
            'SELECT id FROM investimentos WHERE id = :id AND usuario_id = :uid AND ativo = 1 FOR UPDATE'
        );
        $verificar->execute(['id' => $investimentoId, 'uid' => $usuarioId]);
        if (!$verificar->fetchColumn()) {
            $pdo->rollBack();
            cp14RetornarInvestimentos('Investimento não encontrado.');
        }
        $stmt = $pdo->prepare(
            'UPDATE investimentos
                SET
                    valor_atual = :valor,
                    incluido_no_saldo = :incluido
                WHERE
                    id = :id
                    AND usuario_id = :uid
                    AND ativo = 1'
        );

        $stmt->execute([
            'valor' => $valorAtual,
            'incluido' => ($_POST['incluido_no_saldo'] ?? '1') === '0' ? 0 : 1,
            'id' => $investimentoId,
            'uid' => $usuarioId
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO
                    investimento_movimentacoes (
                        investimento_id,
                        usuario_id,
                        tipo,
                        valor,
                        observacao,
                        data_movimentacao
                    )
                VALUES
                    (:iid, :uid, "ajuste", :valor, :observacao, :data)'
        );

        $stmt->execute([
            'iid' => $investimentoId,
            'uid' => $usuarioId,
            'valor' => $valorAtual,
            'observacao' => 'Atualização manual do valor atual',
            'data' => date('Y-m-d')
        ]);

        $pdo->commit();
        cp14RetornarInvestimentos('Valor atual da posição atualizado.', $investimentoId);
    }

    if ($acao === 'vincular_meta') {
        $investimentoId = (int) ($_POST['investimento_id'] ?? 0);

        $metaId = (int) ($_POST['meta_id'] ?? 0);

        if ($metaId > 0) {
            $stmt = $pdo->prepare(
                'SELECT
                        id
                    FROM
                        metas
                    WHERE
                        id = :id
                        AND usuario_id = :uid'
            );

            $stmt->execute([
                'id' => $metaId,
                'uid' => $usuarioId
            ]);

            if (!$stmt->fetchColumn()) {
                $metaId = 0;
            }
        }

        $stmt = $pdo->prepare(
            'UPDATE investimentos
                SET
                    meta_id = :meta
                WHERE
                    id = :id
                    AND usuario_id = :uid'
        );

        $stmt->bindValue(
            ':meta',
            $metaId > 0 ? $metaId : null,
            $metaId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL
        );

        $stmt->bindValue(':id', $investimentoId, PDO::PARAM_INT);

        $stmt->bindValue(':uid', $usuarioId, PDO::PARAM_INT);

        $stmt->execute();

        cp14RetornarInvestimentos('Relação com a meta atualizada.', $investimentoId);
    }

    if ($acao === 'desativar') {
        $investimentoId = (int) ($_POST['investimento_id'] ?? 0);

        $stmt = $pdo->prepare(
            'UPDATE investimentos
                SET
                    ativo = 0
                WHERE
                    id = :id
                    AND usuario_id = :uid'
        );

        $stmt->execute([
            'id' => $investimentoId,
            'uid' => $usuarioId
        ]);

        cp14RetornarInvestimentos('Investimento removido da carteira ativa.');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('CashPilot14/Investimentos: ' . $e->getMessage());

    cp14RetornarInvestimentos('Não foi possível concluir a operação.');
}

cp14RetornarInvestimentos('Ação de investimento inválida.');
