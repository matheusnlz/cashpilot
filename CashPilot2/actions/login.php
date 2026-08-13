<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/login.php');
    exit;
}
validarCsrf();

$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

if ($email === '' || $senha === '') {
    $_SESSION['erro_login'] = 'Preencha e-mail e senha.';
    header('Location: ../pages/login.php');
    exit;
}

$pdo = conectar();
$stmt = $pdo->prepare('SELECT id, nome, senha_hash, tipo_perfil FROM usuarios WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$usuario = $stmt->fetch();

if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {
    $_SESSION['erro_login'] = 'E-mail ou senha inválidos.';
    header('Location: ../pages/login.php');
    exit;
}

session_regenerate_id(true);
$_SESSION['usuario_id']   = $usuario['id'];
$_SESSION['usuario_nome'] = $usuario['nome'];
$_SESSION['usuario_tipo'] = $usuario['tipo_perfil'];

header('Location: ../pages/dashboard.php');
exit;
