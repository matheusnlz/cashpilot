<?php
require_once __DIR__ . '/../includes/auth.php';
redirecionarSeLogado();

$tituloPagina = 'Criar conta';
$erro = $_SESSION['erro_cadastro'] ?? null;
$dadosAntigos = $_SESSION['dados_cadastro'] ?? [];
unset($_SESSION['erro_cadastro'], $_SESSION['dados_cadastro']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar conta · CashPilot</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-tela">
    <div class="auth-caixa">
        <div class="auth-logo">Cash<strong>Pilot</strong></div>
        <p class="auth-subtitulo">Crie sua conta e comece a organizar suas finanças.</p>

        <?php if ($erro): ?>
            <div class="alerta-mensagem erro"><?= limpar($erro) ?></div>
        <?php endif; ?>

        <form action="../actions/cadastro.php" method="POST">
            <div class="form-grupo">
                <label for="nome">Nome completo</label>
                <input type="text" id="nome" name="nome" required value="<?= limpar($dadosAntigos['nome'] ?? '') ?>">
            </div>
            <div class="form-grupo">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" required value="<?= limpar($dadosAntigos['email'] ?? '') ?>">
            </div>
            <div class="form-grupo">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required minlength="6">
            </div>
            <div class="form-grupo">
                <label for="tipo_perfil">Perfil</label>
                <select id="tipo_perfil" name="tipo_perfil">
                    <option value="pessoa_fisica">Pessoa física</option>
                    <option value="mei">MEI / Pequeno empreendedor</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primario btn-bloco">Criar minha conta</button>
        </form>

        <p class="auth-rodape">Já tem conta? <a href="login.php">Entrar</a></p>
    </div>
</div>
</body>
</html>
