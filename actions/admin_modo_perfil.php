<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';

$pdo = conectar();
exigirAdminCashPilot($pdo);
exigirPost();
validarCsrf();

$modo = (string) ($_POST['modo'] ?? '');
if ($modo === 'real') {
    unset($_SESSION['admin_perfil_teste']);
} elseif (in_array($modo, ['pessoa_fisica', 'mei'], true)) {
    $_SESSION['admin_perfil_teste'] = $modo;
} else {
    http_response_code(422);
    exit('Modo inválido.');
}

header('Location: ../pages/dashboard.php');
exit();
