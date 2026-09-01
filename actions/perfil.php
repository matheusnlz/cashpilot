<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/email_service.php';

exigirLogin();
exigirPost();

validarCsrf();

$pdo = conectar();

$usuarioId = (int) usuarioLogadoId();

$acao = $_POST['acao'] ?? '';

if ($acao === 'atualizar_dados') {

        $nome = trim($_POST['nome'] ?? '');

        $email = '';
     // E-mail é alterado em um fluxo separado com confirmação.
        $telefone = trim($_POST['telefone'] ?? '');

        $username = mb_strtolower(ltrim(trim($_POST['username'] ?? ''), '@'));

        $limite = ($_POST['limite_gastos_mensal'] ?? '') !== ''
            ? max(0, (float) str_replace(',', '.', $_POST['limite_gastos_mensal']))
            : null;

        $dadosInvalidos =
            $nome === '' ||
            !preg_match('/^[a-z0-9._]{3,20}$/', $username);

        if ($dadosInvalidos) {

                $_SESSION['mensagem_perfil'] = 'Confira nome e nome de usuário.';

    }  else {

                $check = $pdo->prepare(
                    'SELECT id
             FROM usuarios
             WHERE username = :username
               AND id <> :uid
             LIMIT 1'
                );

                $check->execute([
                    'username' => $username,
                    'uid' => $usuarioId,
                ]);

                if ($check->fetch()) {

                        $_SESSION['mensagem_perfil'] = 'Este nome de usuário já está em uso.';

        }  else {

                        $atual = $pdo->prepare(
                            'SELECT username, username_alterado_em
                 FROM usuarios
                 WHERE id = :uid'
                        );

                        $atual->execute([
                            'uid' => $usuarioId,
                        ]);

                        $usuarioAtual = $atual->fetch(PDO::FETCH_ASSOC) ?: [];

                        $mudouUsername = $username !== ($usuarioAtual['username'] ?? '');

                        $alteracaoBloqueada =
                            $mudouUsername &&
                            !empty($usuarioAtual['username_alterado_em']) &&
                            strtotime($usuarioAtual['username_alterado_em'] . ' +7 days') > time();

                        if ($alteracaoBloqueada) {

                                $liberaEm = date(
                                    'd/m/Y H:i',
                                    strtotime($usuarioAtual['username_alterado_em'] . ' +7 days')
                                );

                                $_SESSION['mensagem_perfil'] =
                                    'Seu nome de usuário poderá ser alterado novamente em ' . $liberaEm . '.';

            }  else {

                                $sql =
                                    'UPDATE usuarios
                     SET nome = :nome,
                         telefone = :telefone,
                         username = :username,
                         limite_gastos_mensal = CASE
                             WHEN tipo_perfil = "pessoa_fisica" THEN :limite
                             ELSE limite_gastos_mensal
                         END';

                                if ($mudouUsername) {

                                        $sql .= ', username_alterado_em = NOW()';

                }

                                $sql .= ' WHERE id = :uid';

                                $stmt = $pdo->prepare($sql);

                                $stmt->execute([
                                    'nome' => $nome,
                                    'telefone' => $telefone !== '' ? $telefone : null,
                                    'username' => $username,
                                    'limite' => $limite,
                                    'uid' => $usuarioId,
                                ]);

                                $_SESSION['usuario_nome'] = $nome;

                                $_SESSION['usuario_username'] = $username;

                                $_SESSION['mensagem_perfil'] = 'Dados atualizados com sucesso.';

            }

        }

    }

}

if ($acao === 'alterar_senha') {

        $senhaAtual = $_POST['senha_atual'] ?? '';

        $senhaNova = $_POST['senha_nova'] ?? '';

        $senhaConfirmacao = $_POST['senha_confirmacao'] ?? '';

        $stmt = $pdo->prepare(
            'SELECT senha_hash
         FROM usuarios
         WHERE id = :uid'
        );

        $stmt->execute([
            'uid' => $usuarioId,
        ]);

        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $usuario &&
            password_verify($senhaAtual, $usuario['senha_hash']) &&
            strlen($senhaNova) >= 6 &&
            hash_equals($senhaNova, $senhaConfirmacao)
        ) {

                $novoHash = password_hash($senhaNova, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare(
                    'UPDATE usuarios
             SET senha_hash = :hash
             WHERE id = :uid'
                );

                $stmt->execute([
                    'hash' => $novoHash,
                    'uid' => $usuarioId,
                ]);

                $_SESSION['mensagem_perfil'] = 'Senha alterada com sucesso.';

    }  else {

                $_SESSION['mensagem_perfil'] =
                    'Não foi possível alterar a senha. Verifique os dados informados.';

    }

}

if ($acao === 'alterar_email') {

        $novoEmail = mb_strtolower(trim($_POST['novo_email'] ?? ''));

        if (!filter_var($novoEmail, FILTER_VALIDATE_EMAIL)) {

                $_SESSION['mensagem_perfil'] = 'Informe um e-mail válido.';

    }  else {

                $check = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email AND id <> :uid LIMIT 1');

                $check->execute(['email' => $novoEmail, 'uid' => $usuarioId]);

                if ($check->fetchColumn()) {

                        $_SESSION['mensagem_perfil'] = 'Este e-mail já pertence a outra conta.';

        }  else {

                        $pdo->prepare('UPDATE usuarios SET email_pendente = :email WHERE id = :uid')
                            ->execute(['email' => $novoEmail, 'uid' => $usuarioId]);

                        $envio = cpEnviarCodigo($pdo, $usuarioId, 'troca_email', $novoEmail);

                        $_SESSION['mensagem_perfil'] = $envio['ok']
                            ? 'Enviamos um código para o novo e-mail. O endereço atual continua ativo até a confirmação.'
                            : $envio['erro'];

        }

    }

        header('Location: ../pages/perfil.php?secao=email');

        exit;

}

if ($acao === 'upload_avatar' && isset($_FILES['avatar'])) {

        $arquivo = $_FILES['avatar'];

        $erro = null;

        $mime = '';

        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {

                $erro = 'Não foi possível enviar a imagem.';

    }  elseif (($arquivo['size'] ?? 0) > 2 * 1024 * 1024) {

                $erro = 'A imagem deve ter no máximo 2 MB.';

    }  else {

                $info = @getimagesize($arquivo['tmp_name']);

                $mime = $info['mime'] ?? '';

                if (
                    !$info ||
                    !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)
                ) {

                        $erro = 'Use uma imagem JPG, PNG ou WEBP.';

        } elseif (
                    (int) ($info[0] ?? 0) <= 0 ||
                    (int) ($info[1] ?? 0) <= 0 ||
                    (int) ($info[0] ?? 0) > 6000 ||
                    (int) ($info[1] ?? 0) > 6000 ||
                    ((int) $info[0] * (int) $info[1]) > 20000000
                ) {

                        $erro = 'A resolução da imagem é muito alta. Use uma imagem de até 20 megapixels.';

        }

    }

        $conteudoImagem = null;

        $mimeFinal = $mime;

        if ($erro === null) {

                $gdDisponivel =
                    function_exists('imagejpeg') &&
                    (
                        ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) ||
                        ($mime === 'image/png' && function_exists('imagecreatefrompng')) ||
                        ($mime === 'image/webp' && function_exists('imagecreatefromwebp'))
                    );

                if ($gdDisponivel) {

                        if ($mime === 'image/png') {

                                $imagem = @imagecreatefrompng($arquivo['tmp_name']);

            }  elseif ($mime === 'image/webp') {

                                $imagem = @imagecreatefromwebp($arquivo['tmp_name']);

            }  else {

                                $imagem = @imagecreatefromjpeg($arquivo['tmp_name']);

            }

                        if ($imagem) {

                                $largura = imagesx($imagem);

                                $altura = imagesy($imagem);

                                $lado = min($largura, $altura);

                                $origemX = (int) (($largura - $lado) / 2);

                                $origemY = (int) (($altura - $lado) / 2);

                                $avatar = imagecreatetruecolor(512, 512);

                                imagecopyresampled(
                                    $avatar,
                                    $imagem,
                                    0,
                                    0,
                                    $origemX,
                                    $origemY,
                                    512,
                                    512,
                                    $lado,
                                    $lado
                                );

                                ob_start();

                                imagejpeg($avatar, null, 88);

                                $conteudoImagem = ob_get_clean();

                                imagedestroy($avatar);

                                imagedestroy($imagem);

                                $mimeFinal = 'image/jpeg';

            }

        }

                if ($conteudoImagem === null) {

                        $conteudoImagem = @file_get_contents($arquivo['tmp_name']);

        }

                if ($conteudoImagem === false || $conteudoImagem === '') {

                        $erro = 'Não foi possível preparar a imagem enviada.';

        }

    }

        if ($erro === null) {

                try {

                        $stmt = $pdo->prepare(
                            'SELECT avatar_path
                 FROM usuarios
                 WHERE id = :uid'
                        );

                        $stmt->execute(['uid' => $usuarioId]);

                        $avatarAnterior = $stmt->fetchColumn();

                        $stmt = $pdo->prepare(
                            'UPDATE usuarios
                 SET avatar_blob = :avatar_blob,
                     avatar_mime = :avatar_mime,
                     avatar_atualizado_em = NOW(),
                     avatar_path = "__db_avatar__"
                 WHERE id = :uid'
                        );

                        $stmt->bindValue(':avatar_blob', $conteudoImagem, PDO::PARAM_LOB);

                        $stmt->bindValue(':avatar_mime', $mimeFinal, PDO::PARAM_STR);

                        $stmt->bindValue(':uid', $usuarioId, PDO::PARAM_INT);

                        $stmt->execute();

                        if (
                            $avatarAnterior &&
                            str_starts_with($avatarAnterior, 'uploads/avatars/')
                        ) {

                                $arquivoAnterior = __DIR__ . '/../' . $avatarAnterior;

                                if (is_file($arquivoAnterior)) {

                                        @unlink($arquivoAnterior);

                }

            }

                        $_SESSION['usuario_avatar'] = '__db_avatar__';

                        $_SESSION['mensagem_perfil'] = 'Foto de perfil atualizada.';

        }  catch (Throwable $e) {

                        $_SESSION['mensagem_perfil'] =
                            'Não foi possível salvar a foto. Execute a migration 010 do CashPilot e tente novamente.';

        }

    }  else {

                $_SESSION['mensagem_perfil'] = $erro;

    }

}

if ($acao === 'remover_avatar') {

        try {

                $stmt = $pdo->prepare(
                    'SELECT avatar_path
             FROM usuarios
             WHERE id = :uid'
                );

                $stmt->execute(['uid' => $usuarioId]);

                $avatarAnterior = $stmt->fetchColumn();

                $stmt = $pdo->prepare(
                    'UPDATE usuarios
             SET avatar_blob = NULL,
                 avatar_mime = NULL,
                 avatar_atualizado_em = NULL,
                 avatar_path = NULL
             WHERE id = :uid'
                );

                $stmt->execute(['uid' => $usuarioId]);

                if (
                    $avatarAnterior &&
                    str_starts_with($avatarAnterior, 'uploads/avatars/')
                ) {

                        $arquivoAnterior = __DIR__ . '/../' . $avatarAnterior;

                        if (is_file($arquivoAnterior)) {

                                @unlink($arquivoAnterior);

            }

        }

                $_SESSION['usuario_avatar'] = '';

                $_SESSION['mensagem_perfil'] = 'Foto de perfil removida.';

    }  catch (Throwable $e) {

                $_SESSION['mensagem_perfil'] =
                    'Não foi possível remover a foto de perfil.';

    }

}

if ($acao === 'alterar_senha') {
     header('Location: ../pages/perfil.php?secao=seguranca');
     exit;

}

if ($acao === 'atualizar_dados') {
     header('Location: ../pages/perfil.php?secao=dados');
     exit;

}

header('Location: ../pages/perfil.php');

exit;
