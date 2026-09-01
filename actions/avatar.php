<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

exigirLogin();

$pdo = conectar();

$usuarioId = (int) usuarioLogadoId();

$blob = null;

$mime = null;

$caminhoLegado = null;

try {

        $stmt = $pdo->prepare(
            'SELECT avatar_blob, avatar_mime, avatar_path
         FROM usuarios
         WHERE id = :uid
         LIMIT 1'
        );

        $stmt->execute(['uid' => $usuarioId]);

        $avatar = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $blob = $avatar['avatar_blob'] ?? null;

        $mime = $avatar['avatar_mime'] ?? null;

        $caminhoLegado = $avatar['avatar_path'] ?? null;

}  catch (Throwable $e) {

        $stmt = $pdo->prepare(
            'SELECT avatar_path
         FROM usuarios
         WHERE id = :uid
         LIMIT 1'
        );

        $stmt->execute(['uid' => $usuarioId]);

        $caminhoLegado = $stmt->fetchColumn() ?: null;

}

if ($blob !== null && $blob !== '' && is_string($mime)) {

        header('Content-Type: ' . $mime);

        header('Content-Length: ' . strlen($blob));

        header('Cache-Control: private, max-age=300');

        header('X-Content-Type-Options: nosniff');

        echo $blob;

        exit;

}

if (
    $caminhoLegado &&
    $caminhoLegado !== '__db_avatar__' &&
    str_starts_with($caminhoLegado, 'uploads/avatars/')
) {

        $arquivo = realpath(__DIR__ . '/../' . $caminhoLegado);

        $pastaPermitida = realpath(__DIR__ . '/../uploads/avatars');

        if (
            $arquivo &&
            $pastaPermitida &&
            str_starts_with($arquivo, $pastaPermitida) &&
            is_file($arquivo)
        ) {

                $tipo = mime_content_type($arquivo) ?: 'image/jpeg';

                header('Content-Type: ' . $tipo);

                header('Content-Length: ' . filesize($arquivo));

                header('Cache-Control: private, max-age=300');

                header('X-Content-Type-Options: nosniff');

                readfile($arquivo);

                exit;

    }

}

http_response_code(404);

exit;
