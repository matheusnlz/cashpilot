<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/email_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

        header('Location: ../pages/recuperar_senha.php');

        exit;

}

validarCsrf();

$pdo = conectar();

$acao = $_POST['acao'] ?? '';

if ($acao === 'solicitar') {

        $email = mb_strtolower(trim($_POST['email'] ?? ''));

        /*
     * A resposta pública não confirma se o endereço existe.
     * Isso reduz enumeração de contas.
     */
        $mensagemGenerica =
            'Se o e-mail estiver cadastrado, enviaremos um código de recuperação.';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                $_SESSION['reset_msg_tipo'] = 'erro';

                $_SESSION['reset_msg'] = 'Informe um endereço de e-mail válido.';

                header('Location: ../pages/recuperar_senha.php');

                exit;

    }

        $stmt = $pdo->prepare(
            'SELECT id, email
         FROM usuarios
         WHERE email = :email
         LIMIT 1'
        );

        $stmt->execute(['email' => $email]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {

                $_SESSION['reset_msg_tipo'] = 'sucesso';

                $_SESSION['reset_msg'] = $mensagemGenerica;

                header('Location: ../pages/recuperar_senha.php');

                exit;

    }

        $uid = (int) $usuario['id'];

        $resultado = cpEnviarCodigo(
            $pdo,
            $uid,
            'recuperacao_senha',
            $email
        );

        if (!$resultado['ok']) {

                $_SESSION['reset_msg_tipo'] = 'erro';

                $_SESSION['reset_msg'] = $resultado['erro'];

                header('Location: ../pages/recuperar_senha.php');

                exit;

    }

        $_SESSION['reset_usuario_id'] = $uid;

        $_SESSION['reset_email'] = $email;

        $_SESSION['reset_reenvios'] = 0;

        unset($_SESSION['reset_codigo_validado']);

        header('Location: ../pages/recuperar_codigo.php');

        exit;

}

if ($acao === 'reenviar') {

        $uid = (int) ($_SESSION['reset_usuario_id'] ?? 0);

        $email = (string) ($_SESSION['reset_email'] ?? '');

        if (!$uid || $email === '') {

                header('Location: ../pages/recuperar_senha.php');

                exit;

    }

        $quantidadeReenvios = (int) ($_SESSION['reset_reenvios'] ?? 0);

        $espera = $quantidadeReenvios === 0 ? 30 : 300;

        $resultado = cpEnviarCodigo(
            $pdo,
            $uid,
            'recuperacao_senha',
            $email,
            $espera
        );

        if ($resultado['ok']) {

                $_SESSION['reset_reenvios'] = $quantidadeReenvios + 1;

                $_SESSION['reset_msg_tipo'] = 'sucesso';

                $_SESSION['reset_msg'] =
                    'Novo código enviado. O próximo reenvio ficará disponível em 5 minutos.';

    }  else {

                $_SESSION['reset_msg_tipo'] = 'aviso';

                $_SESSION['reset_msg'] = $resultado['erro'];

    }

        header('Location: ../pages/recuperar_codigo.php');

        exit;

}

if ($acao === 'confirmar_codigo') {

        $uid = (int) ($_SESSION['reset_usuario_id'] ?? 0);

        $codigo = preg_replace('/\D/', '', $_POST['codigo'] ?? '');

        if (!$uid) {

                header('Location: ../pages/recuperar_senha.php');

                exit;

    }

        if (strlen($codigo) !== 6) {

                $_SESSION['reset_msg_tipo'] = 'erro';

                $_SESSION['reset_msg'] = 'Digite os 6 números do código recebido.';

                header('Location: ../pages/recuperar_codigo.php');

                exit;

    }

        $resultado = cpValidarCodigo(
            $pdo,
            $uid,
            'recuperacao_senha',
            $codigo
        );

        if (!$resultado['ok']) {

                $_SESSION['reset_msg_tipo'] = 'erro';

                $_SESSION['reset_msg'] = $resultado['erro'];

                header('Location: ../pages/recuperar_codigo.php');

                exit;

    }

        $_SESSION['reset_codigo_validado'] = true;

        $_SESSION['reset_validado_em'] = time();

        header('Location: ../pages/redefinir_senha.php');

        exit;

}

if ($acao === 'redefinir') {

        $uid = (int) ($_SESSION['reset_usuario_id'] ?? 0);

        $validado = !empty($_SESSION['reset_codigo_validado']);

        $validadoEm = (int) ($_SESSION['reset_validado_em'] ?? 0);

        if (
            !$uid ||
            !$validado ||
            !$validadoEm ||
            (time() - $validadoEm) > 900
        ) {

                unset(
                    $_SESSION['reset_codigo_validado'],
                    $_SESSION['reset_validado_em']
                );

                $_SESSION['reset_msg_tipo'] = 'aviso';

                $_SESSION['reset_msg'] =
                    'A confirmação expirou. Solicite um novo código.';

                header('Location: ../pages/recuperar_senha.php');

                exit;

    }

        $senha = $_POST['senha_nova'] ?? '';

        $confirmacao = $_POST['senha_confirmacao'] ?? '';

        if (strlen($senha) < 6) {

                $_SESSION['reset_msg_tipo'] = 'erro';

                $_SESSION['reset_msg'] =
                    'A nova senha precisa ter pelo menos 6 caracteres.';

                header('Location: ../pages/redefinir_senha.php');

                exit;

    }

        if ($senha !== $confirmacao) {

                $_SESSION['reset_msg_tipo'] = 'erro';

                $_SESSION['reset_msg'] =
                    'As duas senhas precisam ser iguais.';

                header('Location: ../pages/redefinir_senha.php');

                exit;

    }

        $pdo->prepare(
            'UPDATE usuarios
         SET senha_hash = :hash
         WHERE id = :uid'
        )->execute([
            'hash' => password_hash($senha, PASSWORD_DEFAULT),
            'uid' => $uid,
        ]);

        unset(
            $_SESSION['reset_usuario_id'],
            $_SESSION['reset_email'],
            $_SESSION['reset_reenvios'],
            $_SESSION['reset_codigo_validado'],
            $_SESSION['reset_validado_em']
        );

        $_SESSION['erro_login'] =
            'Senha atualizada com sucesso. Entre com sua nova senha.';

        header('Location: ../pages/login.php');

        exit;

}

header('Location: ../pages/recuperar_senha.php');

exit;
