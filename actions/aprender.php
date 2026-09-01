<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/aprender_helper.php';

exigirLogin();
exigirPost();

$pdo = conectar();

$usuarioId = (int) usuarioLogadoId();

$acao = $_POST['acao'] ?? '';

if ($acao === 'progresso') {

        header('Content-Type: application/json; charset=utf-8');

        validarCsrf();

        $videoId = (int) ($_POST['video_id'] ?? 0);

        $segundos = max(0, (int) ($_POST['segundos'] ?? 0));

        $duracao = max(0, (int) ($_POST['duracao'] ?? 0));

        $percentual = $duracao > 0
            ? min(100, round(($segundos / $duracao) * 100, 2))
            : 0;

        $concluido = $percentual >= 90 ? 1 : 0;

        try {

                $stmt = $pdo->prepare(
                    'INSERT INTO aprender_progresso (
                usuario_id,
                video_id,
                segundos_assistidos,
                percentual,
                concluido
             ) VALUES (
                :uid,
                :video,
                :segundos,
                :percentual,
                :concluido
             )
             ON DUPLICATE KEY UPDATE
                segundos_assistidos = GREATEST(
                    segundos_assistidos,
                    VALUES(segundos_assistidos)
                ),
                percentual = GREATEST(
                    percentual,
                    VALUES(percentual)
                ),
                concluido = GREATEST(
                    concluido,
                    VALUES(concluido)
                )'
                );

                $stmt->execute([
                    'uid' => $usuarioId,
                    'video' => $videoId,
                    'segundos' => $segundos,
                    'percentual' => $percentual,
                    'concluido' => $concluido,
                ]);

                echo json_encode([
                    'ok' => true,
                    'percentual' => $percentual,
                    'concluido' => $concluido,
                ], JSON_UNESCAPED_UNICODE);

    }  catch (Throwable $e) {

                http_response_code(500);

                echo json_encode([
                    'ok' => false,
                ]);

    }

        exit;

}

validarCsrf();

if (!cpUsuarioAdmin($pdo, $usuarioId)) {

        http_response_code(403);

        exit('Acesso restrito.');

}

try {

        if ($acao === 'trilha_criar') {

                $titulo = trim($_POST['titulo'] ?? '');

                $descricao = trim($_POST['descricao'] ?? '') ?: null;

                $perfil = in_array(
                    $_POST['perfil'] ?? '',
                    ['pessoa_fisica', 'mei', 'ambos'],
                    true
                ) ? $_POST['perfil'] : 'ambos';

                if ($titulo !== '') {

                        $stmt = $pdo->prepare(
                            'INSERT INTO aprender_trilhas (
                    titulo,
                    descricao,
                    perfil,
                    ordem
                 ) VALUES (
                    :titulo,
                    :descricao,
                    :perfil,
                    :ordem
                 )'
                        );

                        $stmt->execute([
                            'titulo' => $titulo,
                            'descricao' => $descricao,
                            'perfil' => $perfil,
                            'ordem' => (int) ($_POST['ordem'] ?? 0),
                        ]);

        }

    }

        if ($acao === 'video_criar') {

                $titulo = trim($_POST['titulo'] ?? '');

                $youtubeId = trim($_POST['youtube_video_id'] ?? '');

                $descricao = trim($_POST['descricao'] ?? '') ?: null;

                $categoria = trim($_POST['categoria'] ?? 'Geral') ?: 'Geral';

                $tags = trim($_POST['tags'] ?? '') ?: null;

                $nichos = trim($_POST['nichos'] ?? '') ?: null;

                $objetivos = trim($_POST['objetivos'] ?? '') ?: null;

                $perfil = in_array(
                    $_POST['perfil'] ?? '',
                    ['pessoa_fisica', 'mei', 'ambos'],
                    true
                ) ? $_POST['perfil'] : 'ambos';

                if (
                    preg_match(
                        '~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{6,})~',
                        $youtubeId,
                        $matches
                    )
                ) {

                        $youtubeId = $matches[1];

        }

                if (
                    $titulo !== '' &&
                    preg_match('/^[A-Za-z0-9_-]{6,32}$/', $youtubeId)
                ) {

                        $stmt = $pdo->prepare(
                            'INSERT INTO aprender_videos (
                    titulo,
                    descricao,
                    youtube_video_id,
                    categoria,
                    perfil,
                    tags,
                    nichos,
                    objetivos,
                    ordem
                 ) VALUES (
                    :titulo,
                    :descricao,
                    :youtube,
                    :categoria,
                    :perfil,
                    :tags,
                    :nichos,
                    :objetivos,
                    :ordem
                 )'
                        );

                        $stmt->execute([
                            'titulo' => $titulo,
                            'descricao' => $descricao,
                            'youtube' => $youtubeId,
                            'categoria' => $categoria,
                            'perfil' => $perfil,
                            'tags' => $tags,
                            'nichos' => $nichos,
                            'objetivos' => $objetivos,
                            'ordem' => (int) ($_POST['ordem'] ?? 0),
                        ]);

                        $videoId = (int) $pdo->lastInsertId();

                        $trilhaId = (int) ($_POST['trilha_id'] ?? 0);

                        if ($trilhaId > 0) {

                                $stmt = $pdo->prepare(
                                    'INSERT IGNORE INTO aprender_trilha_videos (
                        trilha_id,
                        video_id,
                        ordem
                     ) VALUES (
                        :trilha,
                        :video,
                        :ordem
                     )'
                                );

                                $stmt->execute([
                                    'trilha' => $trilhaId,
                                    'video' => $videoId,
                                    'ordem' => (int) ($_POST['ordem'] ?? 0),
                                ]);

            }

        }

    }

        if ($acao === 'video_desativar') {

                $stmt = $pdo->prepare(
                    'UPDATE aprender_videos
             SET ativo = 0
             WHERE id = :id'
                );

                $stmt->execute([
                    'id' => (int) ($_POST['id'] ?? 0),
                ]);

    }

        if ($acao === 'trilha_vincular') {

                $stmt = $pdo->prepare(
                    'INSERT INTO aprender_trilha_videos (
                trilha_id,
                video_id,
                ordem
             ) VALUES (
                :trilha,
                :video,
                :ordem
             )
             ON DUPLICATE KEY UPDATE
                ordem = VALUES(ordem)'
                );

                $stmt->execute([
                    'trilha' => (int) ($_POST['trilha_id'] ?? 0),
                    'video' => (int) ($_POST['video_id'] ?? 0),
                    'ordem' => (int) ($_POST['ordem'] ?? 0),
                ]);

    }

        $_SESSION['mensagem_aprender'] =
            'Conteúdo atualizado com sucesso.';

}  catch (Throwable $e) {

        error_log(
            'CashPilot/Aprender 11.1: ' . $e->getMessage()
        );

        $_SESSION['mensagem_aprender'] =
            'Não foi possível concluir. Verifique a migration 009 e tente novamente.';

}

header('Location: ../pages/aprender_gerenciar.php');

exit;
