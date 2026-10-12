<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';

exigirLogin();
exigirPost();
validarCsrf();

if (($_POST['aceite_ia'] ?? '') !== '1') {
    header('Location: ../pages/copiloto.php');
    exit();
}

try {
    $stmt = conectar()->prepare(
        'INSERT INTO cp_privacidade (usuario_id, versao_aviso, ia_externa)
         VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE
            versao_aviso = VALUES(versao_aviso),
            ia_externa = VALUES(ia_externa)'
    );
    $stmt->execute([(int) usuarioLogadoId(), 'aceite_copiloto']);
} catch (Throwable $erro) {
    cp17Log('AceiteCopiloto', $erro);
    $_SESSION['mensagem_erro_copiloto'] =
        'Não foi possível registrar o aceite. Atualize a página e tente novamente.';
}

header('Location: ../pages/copiloto.php');
exit();
