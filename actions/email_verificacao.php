<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/email_service.php';

exigirLogin();
exigirPost();

validarCsrf();

$pdo = conectar();

$uid = (int) usuarioLogadoId();

$acao = $_POST['acao'] ?? '';

$stmt = $pdo->prepare(
    'SELECT email, email_pendente, email_verificado
     FROM usuarios
     WHERE id = :uid'
);

$stmt->execute(['uid' => $uid]);

$usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if ($acao === 'reenviar') {

        $quantidadeReenvios = (int) ($_SESSION['email_reenvios'] ?? 0);

        $espera = $quantidadeReenvios === 0 ? 30 : 300;

        $resultado = cpEnviarCodigo(
            $pdo,
            $uid,
            'confirmacao_email',
            $usuario['email'] ?? '',
            $espera
        );

        if ($resultado['ok']) {

                $_SESSION['email_reenvios'] = $quantidadeReenvios + 1;

                $_SESSION['email_msg_tipo'] = 'sucesso';

                $_SESSION['email_msg'] =
                    'Novo código enviado. A partir de agora, um novo reenvio ficará disponível em 5 minutos.';

    }  else {

                $_SESSION['email_msg_tipo'] = 'aviso';

                $_SESSION['email_msg'] = $resultado['erro'];

    }

        header('Location: ../pages/verificar_email.php');

        exit;

}

if ($acao === 'confirmar') {

        $codigo = preg_replace('/\D/', '', $_POST['codigo'] ?? '');

        if (strlen($codigo) !== 6) {

                $_SESSION['email_msg_tipo'] = 'erro';

                $_SESSION['email_msg'] = 'Digite os 6 números do código recebido.';

                header('Location: ../pages/verificar_email.php');

                exit;

    }

        $resultado = cpValidarCodigo(
            $pdo,
            $uid,
            'confirmacao_email',
            $codigo
        );

        if ($resultado['ok']) {

                $pdo->prepare(
                    'UPDATE usuarios
             SET email_verificado = 1,
                 email_verificado_em = NOW()
             WHERE id = :uid'
                )->execute(['uid' => $uid]);

                $_SESSION['email_verificado'] = 1;

                unset($_SESSION['email_reenvios']);

                $_SESSION['mensagem_perfil'] =
                    'E-mail confirmado com sucesso. Sua conta está verificada.';

                header('Location: ../pages/perfil.php');

                exit;

    }

        $_SESSION['email_msg_tipo'] = 'erro';

        $_SESSION['email_msg'] = $resultado['erro'];

        header('Location: ../pages/verificar_email.php');

        exit;

}

if ($acao === 'reenviar_troca') {

        $destino = $usuario['email_pendente'] ?? '';

        if ($destino === '') {

                $_SESSION['mensagem_perfil'] =
                    'Nenhum novo e-mail aguarda confirmação.';

    }  else {

                $resultado = cpEnviarCodigo(
                    $pdo,
                    $uid,
                    'troca_email',
                    $destino,
                    30
                );

                $_SESSION['mensagem_perfil'] = $resultado['ok']
                    ? 'Enviamos um novo código para o e-mail pendente.'
                    : $resultado['erro'];

    }

        header('Location: ../pages/perfil.php?secao=email');

        exit;

}

if ($acao === 'confirmar_troca') {

        $codigo = preg_replace('/\D/', '', $_POST['codigo'] ?? '');

        $resultado = cpValidarCodigo($pdo, $uid, 'troca_email', $codigo);

        if ($resultado['ok'] && !empty($usuario['email_pendente'])) {

                $novo = mb_strtolower($usuario['email_pendente']);

                $check = $pdo->prepare(
                    'SELECT id
             FROM usuarios
             WHERE email = :email
               AND id <> :uid
             LIMIT 1'
                );

                $check->execute([
                    'email' => $novo,
                    'uid' => $uid,
                ]);

                if ($check->fetchColumn()) {

                        $_SESSION['mensagem_perfil'] =
                            'Esse e-mail passou a ser usado por outra conta.';

        }  else {

                        $pdo->prepare(
                            'UPDATE usuarios
                 SET email = :email,
                     email_pendente = NULL,
                     email_verificado = 1,
                     email_verificado_em = NOW()
                 WHERE id = :uid'
                        )->execute([
                            'email' => $novo,
                            'uid' => $uid,
                        ]);

                        $_SESSION['email_verificado'] = 1;

                        $_SESSION['mensagem_perfil'] =
                            'Novo e-mail confirmado e ativado.';

        }

                header('Location: ../pages/perfil.php?secao=email');

                exit;

    }

        $_SESSION['mensagem_perfil'] = $resultado['erro'];

        header('Location: ../pages/perfil.php?secao=email');

        exit;

}

header('Location: ../pages/perfil.php');

exit;
