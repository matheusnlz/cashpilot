<?php
require_once __DIR__ . '/../includes/auth.php';
redirecionarSeLogado();

$tituloPagina = 'Entrar';
$erro = $_SESSION['erro_login'] ?? null;
unset($_SESSION['erro_login']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar · CashPilot</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-tela">
    <div class="auth-caixa">
        <div class="auth-logo">Cash<strong>Pilot</strong></div>
        <p class="auth-subtitulo">Entenda para onde seu dinheiro está indo.</p>

        <?php if ($erro): ?>
            <div class="alerta-mensagem erro"><?= limpar($erro) ?></div>
        <?php endif; ?>

        <form action="../actions/login.php" method="POST">
            <?= csrfCampo() ?>
            <div class="form-grupo">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required autofocus>
            </div>
            <div class="form-grupo">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required>
            </div>
            <button type="submit" class="btn btn-primario btn-bloco">Entrar</button>
        </form>

        <p class="auth-rodape">Ainda não tem conta? <a href="cadastro.php">Criar conta</a></p>
    </div>
</div>
</body>
</html>
