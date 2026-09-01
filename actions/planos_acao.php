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

        if ($acao === 'criar') {

                $titulo = trim($_POST['titulo'] ?? '');

                $descricao = trim($_POST['descricao'] ?? '') ?: null;

                $origem = trim($_POST['origem'] ?? 'manual') ?: 'manual';

                $itens = array_values(
                    array_filter(
                        array_map('trim', $_POST['itens'] ?? []),
                        static fn (string $item): bool => $item !== ''
                    )
                );

                if ($titulo === '') {

                        throw new RuntimeException('Informe um título para o plano.');

        }

                if (count($itens) < 1) {

                        throw new RuntimeException('Adicione ao menos um passo ao plano.');

        }

                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO planos_acao (
                usuario_id,
                titulo,
                descricao,
                origem
             ) VALUES (
                :uid,
                :titulo,
                :descricao,
                :origem
             )'
                );

                $stmt->execute([
                    'uid' => $usuarioId,
                    'titulo' => $titulo,
                    'descricao' => $descricao,
                    'origem' => $origem,
                ]);

                $planoId = (int) $pdo->lastInsertId();

                $stmtItem = $pdo->prepare(
                    'INSERT INTO plano_acao_itens (
                plano_id,
                descricao,
                ordem
             ) VALUES (
                :plano_id,
                :descricao,
                :ordem
             )'
                );

                foreach ($itens as $ordem => $item) {

                        $stmtItem->execute([
                            'plano_id' => $planoId,
                            'descricao' => $item,
                            'ordem' => $ordem,
                        ]);

        }

                $pdo->commit();

                $_SESSION['mensagem_plano'] = 'Plano de ação criado com sucesso.';

    }

        if ($acao === 'toggle_item') {

                $stmt = $pdo->prepare(
                    'UPDATE plano_acao_itens i
             JOIN planos_acao p
               ON p.id = i.plano_id
             SET i.concluido = 1 - i.concluido,
                 p.atualizado_em = NOW()
             WHERE i.id = :id
               AND p.usuario_id = :uid'
                );

                $stmt->execute([
                    'id' => (int) ($_POST['id'] ?? 0),
                    'uid' => $usuarioId,
                ]);

                $_SESSION['mensagem_plano'] = 'Progresso do plano atualizado.';

    }

        if ($acao === 'concluir') {

                $stmt = $pdo->prepare(
                    'UPDATE planos_acao
             SET status = "concluido",
                 atualizado_em = NOW()
             WHERE id = :id
               AND usuario_id = :uid'
                );

                $stmt->execute([
                    'id' => (int) ($_POST['id'] ?? 0),
                    'uid' => $usuarioId,
                ]);

                $_SESSION['mensagem_plano'] = 'Plano marcado como concluído.';

    }

        if ($acao === 'arquivar') {

                $stmt = $pdo->prepare(
                    'UPDATE planos_acao
             SET status = "arquivado",
                 atualizado_em = NOW()
             WHERE id = :id
               AND usuario_id = :uid'
                );

                $stmt->execute([
                    'id' => (int) ($_POST['id'] ?? 0),
                    'uid' => $usuarioId,
                ]);

                $_SESSION['mensagem_plano'] = 'Plano arquivado.';

    }

}  catch (Throwable $erro) {

        if ($pdo->inTransaction()) {

                $pdo->rollBack();

    }

        error_log('CashPilot/Plano: ' . $erro->getMessage());

        $_SESSION['mensagem_plano'] = $erro instanceof RuntimeException
            ? $erro->getMessage()
            : 'Não foi possível atualizar o plano.';

}

header('Location: ../pages/planos_acao.php');

exit;
