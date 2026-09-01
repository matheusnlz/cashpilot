<?php
require_once __DIR__ . '/../includes/auth.php';

exigirLogin();

$token = $_GET['token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit('Solicitação inválida.');
}

$_SESSION = [];
cashpilotRemoverCookieSessao();
session_destroy();
header('Location: ../pages/logout_transicao.php');
exit;
