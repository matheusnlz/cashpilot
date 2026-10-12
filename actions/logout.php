<?php
require_once __DIR__ . '/../includes/auth.php';

exigirLogin();
exigirPost();
validarCsrf();

$_SESSION = [];
cashpilotRemoverCookieSessao();
session_destroy();

header('Location: ../pages/logout_transicao.php');
exit();
