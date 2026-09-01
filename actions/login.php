<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

exigirPost();
validarCsrf();

$identificador = mb_strtolower(
    ltrim(
        trim($_POST['identificador'] ?? $_POST['email'] ?? ''),
        '@'
    )
);

$senha = $_POST['senha'] ?? '';

if ($identificador === '' || $senha === '') {

        $_SESSION['erro_login'] = 'Preencha seu e-mail/usuário e senha.';

        header('Location: ../pages/login.php');

        exit;

}

$pdo = conectar();

$segundosBloqueio = cashpilotLoginBloqueado($pdo, $identificador);
if ($segundosBloqueio > 0) {
    $_SESSION['erro_login'] = 'Muitas tentativas de acesso. Aguarde alguns minutos e tente novamente.';
    header('Location: ../pages/login.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT
        id,
        nome,
        username,
        email,
        email_verificado,
        senha_hash,
        tipo_perfil,
        avatar_path,
        tema_preferido,
        onboarding_concluido
     FROM usuarios
     WHERE LOWER(email) = :email_login
        OR LOWER(username) = :username_login
     LIMIT 1'
);

$stmt->execute([
    'email_login' => $identificador,
    'username_login' => $identificador,
]);

$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario || !password_verify($senha, $usuario['senha_hash'])) {

        cashpilotRegistrarFalhaLogin($pdo, $identificador);

        $_SESSION['erro_login'] = 'E-mail, usuário ou senha inválidos.';

        header('Location: ../pages/login.php');

        exit;

}

cashpilotLimparFalhasLogin($pdo, $identificador);
session_regenerate_id(true);
renovarCsrf();

$_SESSION['usuario_id'] = (int) $usuario['id'];

$_SESSION['usuario_nome'] = $usuario['nome'];

$_SESSION['usuario_username'] = $usuario['username'] ?? '';

$_SESSION['usuario_tipo'] = $usuario['tipo_perfil'];

$_SESSION['usuario_avatar'] = $usuario['avatar_path'] ?? '';

$_SESSION['tema_preferido'] =
    ($usuario['tema_preferido'] ?? 'light') === 'dark'
        ? 'dark'
        : 'light';

$_SESSION['email_verificado'] = (int) ($usuario['email_verificado'] ?? 1);

$_SESSION['onboarding_concluido'] =
    (int) ($usuario['onboarding_concluido'] ?? 1);

$_SESSION['mostrar_transicao_login'] = true;

header('Location: ../pages/transicao.php');

exit;
