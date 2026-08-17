<?php
require_once __DIR__ . '/../includes/auth.php';
redirecionarSeLogado();
$tituloPagina='Entrar';$erro=$_SESSION['erro_login']??null;unset($_SESSION['erro_login']);
?>
<!DOCTYPE html>
<html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Entrar · CashPilot</title><link rel="stylesheet" href="../assets/css/style.css"></head>
<body>
<div class="auth-tela auth-tiktok auth-login-clean">
    <aside class="auth-painel-marca auth-painel-clean">
        <img src="../assets/img/logo-cashpilot-escura.png" class="auth-logo-transparente" alt="CashPilot">
        <span class="eyebrow">CONTROLE · ANÁLISE · CLAREZA</span>
        <h1>Entenda seus números antes de decidir o próximo passo.</h1>
        <p>O CashPilot reúne organização financeira, RadarPilot e um Copiloto preparado para interpretar seus dados.</p>
        <div class="auth-visual-financeiro"><div><span>Receitas</span><strong>↗</strong></div><div><span>Despesas</span><strong>↘</strong></div><div><span>Decisão</span><strong>→</strong></div></div>
    </aside>
    <section class="auth-caixa auth-caixa-login">
        <div class="auth-marca-mobile"><img src="../assets/img/logo-cashpilot-escura.png" alt="CashPilot"></div>
        <div class="auth-titulo"><h2>Bem-vindo de volta</h2><p>Entre para continuar acompanhando suas finanças.</p></div>
        <?php if($erro):?><div class="alerta-mensagem erro"><?=limpar($erro)?></div><?php endif;?>
        <form action="../actions/login.php" method="POST" autocomplete="off">
            <?=csrfCampo()?>
            <div class="form-grupo"><label for="email">E-mail</label><input autocomplete="off" type="email" id="email" name="email" required autofocus></div>
            <div class="form-grupo"><label for="senha">Senha</label><input autocomplete="off" type="password" id="senha" name="senha" required></div>
            <button type="submit" class="btn btn-primario btn-bloco">Entrar</button>
        </form>
        <p class="auth-rodape">Ainda não tem conta? <a href="cadastro.php">Criar conta</a></p>
    </section>
</div>
</body></html>
