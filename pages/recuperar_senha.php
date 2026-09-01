<?php
require_once __DIR__ . '/../includes/auth.php';

redirecionarSeLogado();

$msg = $_SESSION['reset_msg'] ?? null;
$msgTipo = $_SESSION['reset_msg_tipo'] ?? 'erro';

unset($_SESSION['reset_msg'], $_SESSION['reset_msg_tipo']);
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha · CashPilot</title>
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
                    <span class="auth-kicker">RECUPERAÇÃO DE ACESSO</span>
                    <h1>Esqueceu sua senha?</h1>
                    <p>
                        Informe o e-mail da sua conta. Enviaremos um código
                        de segurança para você continuar.
                    </p>
                </div>

                <?php if ($msg): ?>
                    <div class="alerta-mensagem <?= limpar($msgTipo) ?>">
                        <?= limpar($msg) ?>
                    </div>
                <?php endif; ?>

                <form action="../actions/recuperar_senha.php" method="POST">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="acao" value="solicitar">

                    <div class="form-grupo auth-field">
                        <label for="email">E-mail</label>
                        <div class="auth-input-wrap">
                            <span class="auth-input-icon" aria-hidden="true">✉</span>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                autocomplete="email"
                                required
                                autofocus
                                placeholder="seu@email.com"
                            >
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="btn btn-primario btn-bloco auth-main-button"
                    >
                        Enviar código
                    </button>
                </form>

                <p class="cp141-security-note">
                    O código possui 6 dígitos e é válido por 15 minutos.
                </p>

                <p class="auth-rodape auth-transition-footer">
                    Lembrou sua senha?
                    <a href="login.php">Voltar para entrar</a>
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
                <span class="auth-brand-kicker">ACESSO SEGURO</span>
                <h2>Recupere sua conta com tranquilidade.</h2>
                <p>
                    Nós confirmamos que o acesso é seu antes de permitir
                    a criação de uma nova senha.
                </p>

                <div class="cp141-auth-steps">
                    <span class="ativo"><b>1</b> Informe seu e-mail</span>
                    <span><b>2</b> Confirme o código</span>
                    <span><b>3</b> Crie uma nova senha</span>
                </div>

                <small>CashPilot · Segurança da sua conta em primeiro lugar.</small>
            </div>
        </aside>
    </main>
</div>
</body>
</html>
