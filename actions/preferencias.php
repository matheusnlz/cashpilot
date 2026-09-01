<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

exigirLogin();

$pdo = conectar();

$usuarioId = (int) usuarioLogadoId();

$acao = $_POST['acao'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        validarCsrf();

}

if ($acao === 'tema') {

        header('Content-Type: application/json; charset=utf-8');

        $tema = ($_POST['tema'] ?? '') === 'dark' ? 'dark' : 'light';

        $stmt = $pdo->prepare(
            'UPDATE usuarios
         SET tema_preferido = :tema
         WHERE id = :uid'
        );

        $stmt->execute([
            'tema' => $tema,
            'uid' => $usuarioId,
        ]);

        $_SESSION['tema_preferido'] = $tema;

        echo json_encode([
            'ok' => true,
            'tema' => $tema,
        ], JSON_UNESCAPED_UNICODE);

        exit;

}

if ($acao === 'marcar_apresentacao') {

        header('Content-Type: application/json; charset=utf-8');

        $chave = trim($_POST['chave'] ?? '');

        if (!preg_match('/^[a-z0-9_]{2,80}$/', $chave)) {

                http_response_code(422);

                echo json_encode([
                    'ok' => false,
                ]);

                exit;

    }

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO usuario_apresentacoes (
            usuario_id,
            chave
         ) VALUES (
            :uid,
            :chave
         )'
        );

        $stmt->execute([
            'uid' => $usuarioId,
            'chave' => $chave,
        ]);

        echo json_encode([
            'ok' => true,
        ]);

        exit;

}

if ($acao === 'concluir_onboarding') {

        $stmt = $pdo->prepare(
            'UPDATE usuarios
         SET onboarding_concluido = 1
         WHERE id = :uid'
        );

        $stmt->execute([
            'uid' => $usuarioId,
        ]);

        $_SESSION['onboarding_concluido'] = 1;

        header('Location: ../pages/dashboard.php');

        exit;

}

if ($acao === 'reiniciar_onboarding') {

        $stmt = $pdo->prepare(
            'DELETE FROM usuario_apresentacoes
         WHERE usuario_id = :uid'
        );

        $stmt->execute([
            'uid' => $usuarioId,
        ]);

        header('Location: ../pages/boas_vindas.php?repetir=1');

        exit;

}

header('Location: ../pages/perfil.php');

exit;
