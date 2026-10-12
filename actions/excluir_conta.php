<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();
exigirPost();
validarCsrf();
if (trim((string) ($_POST['confirmacao'] ?? '')) !== 'EXCLUIR') {
    $_SESSION['mensagem_perfil'] = 'Confirmação inválida.';
    header('Location: ../pages/privacidade.php');
    exit();
}
$pdo = conectar();
$uid = (int) usuarioLogadoId();
try {
    $pdo->beginTransaction();
    $conexoes = $pdo->prepare('SELECT id FROM pluggy_items WHERE usuario_id = ? FOR UPDATE');
    $conexoes->execute([$uid]);
    if ($conexoes->fetchColumn()) {
        $pdo->rollBack();
        $_SESSION['privacidade_mensagem'] =
            'Desconecte as instituições em Conecte seu banco antes de excluir a conta, para encerrar também o acesso no provedor.';
        header('Location: ../pages/privacidade.php');
        exit();
    }
    $limparEventos = $pdo->prepare('UPDATE pluggy_webhook_eventos
        SET payload_json = "{}", erro_mensagem = NULL, item_id = NULL, status = "ignorado"
        WHERE usuario_id = ?');
    $limparEventos->execute([$uid]);
    $s = $pdo->prepare('DELETE FROM usuarios WHERE id=:u');
    $s->execute(['u' => $uid]);
    $pdo->commit();
    $_SESSION = [];
    cashpilotRemoverCookieSessao();
    session_destroy();
    header('Location: ../index.php?conta=excluida');
    exit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    cp17Log('ExcluirConta', $e);
    http_response_code(500);
    exit('Não foi possível excluir a conta agora.');
}
