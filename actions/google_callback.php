<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/google_auth.php';

exigirPost();
validarCsrf();

$credencialGoogle = (string) ($_POST['credential'] ?? '');
if ($credencialGoogle === '' || strlen($credencialGoogle) > 12000) {
    $_SESSION['erro_login'] = 'Credencial Google inválida.';
    header('Location: ../pages/login.php');
    exit;
}

$resultado = cpGoogleVerificarCredential($credencialGoogle);

if (!$resultado['ok']) {

        $_SESSION['erro_login'] = $resultado['erro'];

        header('Location: ../pages/login.php');

        exit;

}

$pdo = conectar();

// O identificador estável da conta Google é o "sub", não o e-mail.
$stmt = $pdo->prepare(
    'SELECT u.*
     FROM usuario_oauth o
     INNER JOIN usuarios u ON u.id = o.usuario_id
     WHERE o.provider = "google"
       AND o.provider_user_id = :sub
     LIMIT 1'
);

$stmt->execute(['sub' => $resultado['sub']]);

$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if ($usuario) {

        session_regenerate_id(true);
        renovarCsrf();

        $_SESSION['usuario_id'] = (int) $usuario['id'];

        $_SESSION['usuario_nome'] = $usuario['nome'];

        $_SESSION['usuario_username'] = $usuario['username'] ?? '';

        $_SESSION['usuario_tipo'] = $usuario['tipo_perfil'];

        $_SESSION['usuario_avatar'] = $usuario['avatar_path'] ?? '';

        $_SESSION['tema_preferido'] =
            ($usuario['tema_preferido'] ?? 'light') === 'dark' ? 'dark' : 'light';

        $_SESSION['onboarding_concluido'] =
            (int) ($usuario['onboarding_concluido'] ?? 1);

        $_SESSION['email_verificado'] =
            (int) ($usuario['email_verificado'] ?? 1);

        $_SESSION['mostrar_transicao_login'] = true;

        header('Location: ../pages/transicao.php');

        exit;

}

// Existe conta tradicional com o mesmo e-mail: confirmar a senha antes
// de vincular o identificador Google à conta já existente.
$stmt = $pdo->prepare(
    'SELECT id
     FROM usuarios
     WHERE email = :email
     LIMIT 1'
);

$stmt->execute(['email' => $resultado['email']]);

$usuarioExistente = (int) ($stmt->fetchColumn() ?: 0);

if ($usuarioExistente) {

        $_SESSION['google_link_pending'] = $resultado;

        $_SESSION['google_link_usuario'] = $usuarioExistente;

        header('Location: ../pages/vincular_google.php');

        exit;

}

// Conta nova: o Google já confirmou identidade/e-mail, mas o CashPilot
// ainda precisa dos dados de perfil PF/MEI antes de concluir o cadastro.
$_SESSION['google_pending'] = $resultado;

header('Location: ../pages/cadastro.php?google=1');

exit;
