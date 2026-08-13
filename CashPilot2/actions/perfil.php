<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();
validarCsrf();

$pdo = conectar();
$usuarioId = usuarioLogadoId();
$acao = $_POST['acao'] ?? '';

if ($acao === 'atualizar_dados') {
    $nome = trim($_POST['nome'] ?? '');
    $tipoPerfil = in_array($_POST['tipo_perfil'] ?? '', ['pessoa_fisica', 'mei'], true) ? $_POST['tipo_perfil'] : 'pessoa_fisica';
    $rendaMensal = (float) str_replace(',', '.', $_POST['renda_mensal'] ?? '0');

    if ($nome !== '') {
        $stmt = $pdo->prepare('UPDATE usuarios SET nome = :nome, tipo_perfil = :tipo, renda_mensal = :renda WHERE id = :uid');
        $stmt->execute(['nome' => $nome, 'tipo' => $tipoPerfil, 'renda' => $rendaMensal, 'uid' => $usuarioId]);

        $_SESSION['usuario_nome'] = $nome;
        $_SESSION['usuario_tipo'] = $tipoPerfil;
        $_SESSION['mensagem_perfil'] = 'Dados atualizados com sucesso.';
    }
}

if ($acao === 'alterar_senha') {
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $senhaNova  = $_POST['senha_nova'] ?? '';

    $stmt = $pdo->prepare('SELECT senha_hash FROM usuarios WHERE id = :uid');
    $stmt->execute(['uid' => $usuarioId]);
    $usuario = $stmt->fetch();

    if ($usuario && password_verify($senhaAtual, $usuario['senha_hash']) && strlen($senhaNova) >= 6) {
        $novoHash = password_hash($senhaNova, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE usuarios SET senha_hash = :hash WHERE id = :uid');
        $stmt->execute(['hash' => $novoHash, 'uid' => $usuarioId]);
        $_SESSION['mensagem_perfil'] = 'Senha alterada com sucesso.';
    } else {
        $_SESSION['mensagem_perfil'] = 'Não foi possível alterar a senha. Verifique os dados informados.';
    }
}

header('Location: ../pages/perfil.php');
exit;
