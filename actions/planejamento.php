<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/cashpilot14_financeiro.php';

exigirLogin();
exigirPost();

validarCsrf();

if (usuarioLogadoTipo() !== 'pessoa_fisica') {

        header('Location: ../pages/dashboard.php');

        exit;

}

$pdo = conectar();

$usuarioId = (int) usuarioLogadoId();

$competencia = preg_match(
    '/^\d{4}-\d{2}$/',
    (string) ($_POST['competencia'] ?? '')
)
    ? $_POST['competencia']
    : date('Y-m');

function cp14PlanejamentoValor($valor): float
 {

        return max(
            0,
            (float) str_replace(
                ',',
                '.',
                (string) $valor
            )
        );

}

$acao = (string) ($_POST['acao'] ?? 'salvar');

if ($acao === 'copiar_anterior') {

        $anterior = date('Y-m', strtotime($competencia . '-01 -1 month'));

        try {

                $pdo->beginTransaction();

                $stmt=$pdo->prepare('INSERT INTO planejamento_mensal (usuario_id,competencia,receita_esperada,gastos_fixos_estimados,valor_metas,observacao) SELECT usuario_id,:destino,receita_esperada,gastos_fixos_estimados,valor_metas,observacao FROM planejamento_mensal WHERE usuario_id=:uid AND competencia=:origem ON DUPLICATE KEY UPDATE receita_esperada=VALUES(receita_esperada),gastos_fixos_estimados=VALUES(gastos_fixos_estimados),valor_metas=VALUES(valor_metas),observacao=VALUES(observacao)');

                $stmt->execute(['destino'=>$competencia,'uid'=>$usuarioId,'origem'=>$anterior]);

                if (cp14TabelaExiste($pdo,'planejamento_categoria_mensal')) {

                        $stmt=$pdo->prepare('INSERT INTO planejamento_categoria_mensal (usuario_id,competencia,categoria_id,valor_limite) SELECT usuario_id,:destino,categoria_id,valor_limite FROM planejamento_categoria_mensal WHERE usuario_id=:uid AND competencia=:origem ON DUPLICATE KEY UPDATE valor_limite=VALUES(valor_limite)');

                        $stmt->execute(['destino'=>$competencia,'uid'=>$usuarioId,'origem'=>$anterior]);

        }

                if (cp14TabelaExiste($pdo,'planejamento_destino_mensal')) {

                        $stmt=$pdo->prepare('INSERT INTO planejamento_destino_mensal (usuario_id,competencia,tipo,valor_planejado) SELECT usuario_id,:destino,tipo,valor_planejado FROM planejamento_destino_mensal WHERE usuario_id=:uid AND competencia=:origem ON DUPLICATE KEY UPDATE valor_planejado=VALUES(valor_planejado)');

                        $stmt->execute(['destino'=>$competencia,'uid'=>$usuarioId,'origem'=>$anterior]);

        }

                $pdo->commit();

                $_SESSION['mensagem_pf']='Planejamento de '.$anterior.' copiado para '.$competencia.'. Os gastos realizados não foram copiados.';

    }
     catch(Throwable $e) {

                if($pdo->inTransaction())$pdo->rollBack();

                error_log('CashPilot143/CopiarPlanejamento: '.$e->getMessage());

                $_SESSION['mensagem_pf']='Não foi possível copiar o planejamento anterior.';

    }

        header('Location: ../pages/planejamento.php?competencia='.urlencode($competencia));

    exit;

}

try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO planejamento_mensal (
            usuario_id,
            competencia,
            receita_esperada,
            gastos_fixos_estimados,
            valor_metas,
            observacao
         ) VALUES (
            :uid,
            :competencia,
            :receita,
            :fixos,
            :metas,
            :observacao
         )
         ON DUPLICATE KEY UPDATE
            receita_esperada = VALUES(receita_esperada),
            gastos_fixos_estimados = VALUES(gastos_fixos_estimados),
            valor_metas = VALUES(valor_metas),
            observacao = VALUES(observacao)'
        );

        $stmt->execute([
            'uid' => $usuarioId,
            'competencia' => $competencia,
            'receita' => cp14PlanejamentoValor(
                $_POST['receita_esperada'] ?? 0
            ),
            'fixos' => cp14PlanejamentoValor(
                $_POST['gastos_fixos_estimados'] ?? 0
            ),
            'metas' => cp14PlanejamentoValor(
                $_POST['valor_metas'] ?? 0
            ),
            'observacao' =>
                trim((string) ($_POST['observacao'] ?? '')) ?: null,
        ]);

        if (cp14TabelaExiste(
            $pdo,
            'planejamento_destino_mensal'
        )) {

                $stmtDestino = $pdo->prepare(
                    'INSERT INTO planejamento_destino_mensal (
                usuario_id,
                competencia,
                tipo,
                valor_planejado
             ) VALUES (
                :uid,
                :competencia,
                :tipo,
                :valor
             )
             ON DUPLICATE KEY UPDATE
                valor_planejado = VALUES(valor_planejado)'
                );

                foreach (
                    [
                        'investimentos' =>
                            $_POST['valor_investimentos'] ?? 0,
                        'reserva' =>
                            $_POST['valor_reserva'] ?? 0,
                    ]
                    as $tipo => $valor
                ) {

                        $stmtDestino->execute([
                            'uid' => $usuarioId,
                            'competencia' => $competencia,
                            'tipo' => $tipo,
                            'valor' => cp14PlanejamentoValor($valor),
                        ]);

        }

    }

        if (cp14TabelaExiste(
            $pdo,
            'planejamento_categoria_mensal'
        )) {

                $categorias =
                    $_POST['categoria_limite']
                    ?? [];

                $stmtCategoria = $pdo->prepare(
                    'INSERT INTO planejamento_categoria_mensal (
                usuario_id,
                competencia,
                categoria_id,
                valor_limite
             ) VALUES (
                :uid,
                :competencia,
                :categoria,
                :valor
             )
             ON DUPLICATE KEY UPDATE
                valor_limite = VALUES(valor_limite)'
                );

                foreach ($categorias as $categoriaId => $valor) {

                        $categoriaId = (int) $categoriaId;

                        if ($categoriaId <= 0) {

                                continue;

            }

                        $stmtValida = $pdo->prepare(
                            'SELECT id
                 FROM categorias
                 WHERE id = :id
                   AND usuario_id = :uid
                   AND tipo = "despesa"'
                        );

                        $stmtValida->execute([
                            'id' => $categoriaId,
                            'uid' => $usuarioId,
                        ]);

                        if (!$stmtValida->fetchColumn()) {

                                continue;

            }

                        $stmtCategoria->execute([
                            'uid' => $usuarioId,
                            'competencia' => $competencia,
                            'categoria' => $categoriaId,
                            'valor' => cp14PlanejamentoValor($valor),
                        ]);

        }

    }

        $pdo->commit();

        $_SESSION['mensagem_pf'] =
            'Planejamento mensal atualizado.';

}
 catch (Throwable $e) {

        if ($pdo->inTransaction()) {

                $pdo->rollBack();

    }

        error_log(
            'CashPilot14/Planejamento: '
            . $e->getMessage()
        );

        $_SESSION['mensagem_pf'] =
            'Não foi possível salvar o planejamento.';

}

header(
    'Location: ../pages/planejamento.php?competencia='
    . urlencode($competencia)
);

exit;
