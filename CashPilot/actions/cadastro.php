<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/cadastro.php');
    exit;
}

$nome  = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';
$tipoPerfil = $_POST['tipo_perfil'] ?? 'pessoa_fisica';
if (!in_array($tipoPerfil, ['pessoa_fisica', 'mei'], true)) {
    $tipoPerfil = 'pessoa_fisica';
}

$_SESSION['dados_cadastro'] = ['nome' => $nome, 'email' => $email];

if ($nome === '' || $email === '' || $senha === '') {
    $_SESSION['erro_cadastro'] = 'Preencha todos os campos.';
    header('Location: ../pages/cadastro.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['erro_cadastro'] = 'Informe um e-mail válido.';
    header('Location: ../pages/cadastro.php');
    exit;
}

if (strlen($senha) < 6) {
    $_SESSION['erro_cadastro'] = 'A senha deve ter no mínimo 6 caracteres.';
    header('Location: ../pages/cadastro.php');
    exit;
}

$pdo = conectar();

$stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
if ($stmt->fetch()) {
    $_SESSION['erro_cadastro'] = 'Este e-mail já está cadastrado.';
    header('Location: ../pages/cadastro.php');
    exit;
}

$hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $pdo->prepare(
    'INSERT INTO usuarios (nome, email, senha_hash, tipo_perfil) VALUES (:nome, :email, :senha_hash, :tipo_perfil)'
);
$stmt->execute([
    'nome'        => $nome,
    'email'       => $email,
    'senha_hash'  => $hash,
    'tipo_perfil' => $tipoPerfil,
]);

$usuarioId = (int) $pdo->lastInsertId();

unset($_SESSION['dados_cadastro']);
session_regenerate_id(true);
$_SESSION['usuario_id']   = $usuarioId;
$_SESSION['usuario_nome'] = $nome;
$_SESSION['usuario_tipo'] = $tipoPerfil;

header('Location: ../pages/dashboard.php');
exit;
