<?php
require_once __DIR__ . '/../includes/auth.php';

redirecionarSeLogado();

$uid = (int) ($_SESSION['reset_usuario_id'] ?? 0);
$validado = !empty($_SESSION['reset_codigo_validado']);
$validadoEm = (int) ($_SESSION['reset_validado_em'] ?? 0);

if (!$uid || !$validado || !$validadoEm || (time() - $validadoEm) > 900) {
    unset($_SESSION['reset_codigo_validado'], $_SESSION['reset_validado_em']);
    header('Location: recuperar_senha.php');
    exit;
}

$msg = $_SESSION['reset_msg'] ?? null;
$msgTipo = $_SESSION['reset_msg_tipo'] ?? 'erro';

unset($_SESSION['reset_msg'], $_SESSION['reset_msg_tipo']);
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nova senha · CashPilot</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=13.2">
<link rel="stylesheet" href="../assets/css/components.css?v=13.4">
<link rel="stylesheet" href="../assets/css/theme.css?v=13.4.1">
<link rel="stylesheet" href="../assets/css/modules.css?v=14.0">
<link rel="stylesheet" href="../assets/css/auth.css?v=14.1">
</head>
<body class="auth-body-transition cp-auth-light cp141-auth-page auth-page-ready">
<div class="auth-tela auth-transition-page">
    <main class="auth-transition-card auth-login-state cp141-recovery-card">
        <section class="auth-transition-form">
            <div class="auth-form-inner">
                <div class="auth-form-brand-mobile">
                    <img src="../assets/img/logo-cashpilot-escura.png" alt="CashPilot">
                </div>

                <div class="auth-titulo auth-transition-title">
                    <span class="auth-kicker">NOVA SENHA</span>
                    <h1>Crie uma nova senha</h1>
                    <p>
                        Seu código foi confirmado. Agora escolha uma nova
                        senha para acessar o CashPilot.
                    </p>
                </div>

                <?php if ($msg): ?>
                    <div class="alerta-mensagem <?= limpar($msgTipo) ?>">
                        <?= limpar($msg) ?>
                    </div>
                <?php endif; ?>

                <form action="../actions/recuperar_senha.php" method="POST">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="acao" value="redefinir">

                    <div class="form-grupo auth-field">
                        <label for="senha_nova">Nova senha</label>
                        <div class="auth-input-wrap">
                            <input
                                type="password"
                                id="senha_nova"
                                name="senha_nova"
                                minlength="6"
                                autocomplete="new-password"
                                required
                                placeholder="Mínimo de 6 caracteres"
                            >
                        </div>
                    </div>

                    <div class="form-grupo auth-field">
                        <label for="senha_confirmacao">Confirmar nova senha</label>
                        <div class="auth-input-wrap">
                            <input
                                type="password"
                                id="senha_confirmacao"
                                name="senha_confirmacao"
                                minlength="6"
                                autocomplete="new-password"
                                required
                                placeholder="Digite a senha novamente"
                            >
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="btn btn-primario btn-bloco auth-main-button"
                    >
                        Salvar nova senha
                    </button>
                </form>

                <p class="cp141-security-note">
                    Após salvar, você voltará para a tela de login.
                </p>
            </div>
        </section>

        <aside class="auth-transition-brand cp141-recovery-brand">
            <div class="auth-brand-orbit auth-brand-orbit-1"></div>
            <div class="auth-brand-orbit auth-brand-orbit-2"></div>

            <div class="auth-brand-content">
                <img
                    src="../assets/img/logo-cashpilot-v13.png"
                    class="auth-transition-logo"
                    alt="CashPilot"
                >
                <span class="auth-brand-kicker">ÚLTIMA ETAPA</span>
                <h2>Seu acesso está quase recuperado.</h2>
                <p>
                    Escolha uma senha nova e diferente para manter sua
                    conta protegida.
                </p>

                <div class="cp141-auth-steps">
                    <span><b>1</b> Informe seu e-mail</span>
                    <span><b>2</b> Confirme o código</span>
                    <span class="ativo"><b>3</b> Crie uma nova senha</span>
                </div>

                <small>Use uma senha que você não utiliza em outros serviços.</small>
            </div>
        </aside>
    </main>
</div>
</body>
</html>
