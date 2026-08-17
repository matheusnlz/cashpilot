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
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $limite = ($_POST['limite_gastos_mensal'] ?? '') !== '' ? max(0, (float)str_replace(',', '.', $_POST['limite_gastos_mensal'])) : null;
    if ($nome !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $check = $pdo->prepare('SELECT id FROM usuarios WHERE email=:email AND id<>:uid LIMIT 1');
        $check->execute(['email'=>$email,'uid'=>$usuarioId]);
        if ($check->fetch()) {
            $_SESSION['mensagem_perfil'] = 'Este e-mail já está em uso por outra conta.';
        } else {
            $stmt = $pdo->prepare('UPDATE usuarios SET nome=:nome,email=:email,telefone=:telefone,limite_gastos_mensal=CASE WHEN tipo_perfil="pessoa_fisica" THEN :limite ELSE limite_gastos_mensal END WHERE id=:uid');
            $stmt->execute(['nome'=>$nome,'email'=>$email,'telefone'=>$telefone?:null,'limite'=>$limite,'uid'=>$usuarioId]);
            $_SESSION['usuario_nome'] = $nome;
            $_SESSION['mensagem_perfil'] = 'Dados atualizados com sucesso.';
        }
    } else { $_SESSION['mensagem_perfil'] = 'Informe nome e e-mail válidos.'; }
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

if ($acao === 'upload_avatar' && isset($_FILES['avatar'])) {
    $arquivo = $_FILES['avatar'];
    $erro = null;
    if ($arquivo['error'] !== UPLOAD_ERR_OK) $erro = 'Não foi possível enviar a imagem.';
    elseif ($arquivo['size'] > 2 * 1024 * 1024) $erro = 'A imagem deve ter no máximo 2 MB.';
    else {
        $info = @getimagesize($arquivo['tmp_name']);
        $mime = $info['mime'] ?? '';
        if (!$info || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) $erro = 'Use uma imagem JPG, PNG ou WEBP.';
    }
    if ($erro === null) {
        $pasta = __DIR__ . '/../uploads/avatars';
        if (!is_dir($pasta)) mkdir($pasta, 0755, true);
        $nomeArquivo = 'avatar_' . $usuarioId . '_' . bin2hex(random_bytes(8)) . '.jpg';
        $destino = $pasta . '/' . $nomeArquivo;
        $info = getimagesize($arquivo['tmp_name']);
        $imagem = match ($info['mime']) {
            'image/png' => imagecreatefrompng($arquivo['tmp_name']),
            'image/webp' => imagecreatefromwebp($arquivo['tmp_name']),
            default => imagecreatefromjpeg($arquivo['tmp_name']),
        };
        if ($imagem && imagejpeg($imagem, $destino, 88)) {
            imagedestroy($imagem);
            $caminho = 'uploads/avatars/' . $nomeArquivo;
            $stmt = $pdo->prepare('SELECT avatar_path FROM usuarios WHERE id = :uid');
            $stmt->execute(['uid' => $usuarioId]);
            $anterior = $stmt->fetchColumn();
            if ($anterior && str_starts_with($anterior, 'uploads/avatars/')) @unlink(__DIR__ . '/../' . $anterior);
            $stmt = $pdo->prepare('UPDATE usuarios SET avatar_path = :avatar WHERE id = :uid');
            $stmt->execute(['avatar' => $caminho, 'uid' => $usuarioId]);
            $_SESSION['usuario_avatar'] = $caminho;
            $_SESSION['mensagem_perfil'] = 'Foto de perfil atualizada.';
        } else $erro = 'Não foi possível processar a imagem.';
    }
    if ($erro) $_SESSION['mensagem_perfil'] = $erro;
}

header('Location: ../pages/perfil.php');
exit;
