<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../includes/google_auth.php';

redirecionarSeLogado();


$tituloPagina = 'Entrar';

$erro = $_SESSION['erro_login'] ?? null;

unset($_SESSION['erro_login']);


$googleClientId = cpGoogleClientId();?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Entrar · CashPilot</title>
<link rel="stylesheet" href="../assets/css/style.css?v=13.0">
<link rel="stylesheet" href="../assets/css/components.css?v=13.4">
<link rel="stylesheet" href="../assets/css/theme.css?v=13.4.1">
</head>
<body class="auth-body-transition cp-auth-light">
<div class="auth-tela auth-transition-page">
<main class="auth-transition-card auth-login-state" id="authTransitionCard">
<section class="auth-transition-form">
<div class="auth-form-inner">
<div class="auth-form-brand-mobile">
<img src="../assets/img/logo-cashpilot-escura.png" alt="CashPilot">
</div>
<div class="auth-titulo auth-transition-title">
<span class="auth-kicker">SEU ESPAÇO FINANCEIRO</span>
<h1>Bem-vindo de volta</h1>
<p>Entre para continuar acompanhando suas finanças.</p>
</div>

                <?php if ($erro):?>
                    <div class="alerta-mensagem erro"><?= limpar($erro)?></div>
                <?php endif;?>

                <?php if (($_GET['sessao'] ?? '') === 'expirada'):?>
                    <div class="alerta-mensagem aviso">
                        Sua sessão expirou. Entre novamente para continuar com segurança.
                    </div>
                <?php endif;?>

                <form action="../actions/login.php" method="POST" autocomplete="off">
                    <?= csrfCampo()?>

                    <div class="form-grupo auth-field">
<label for="identificador">E-mail ou nome de usuário</label>
<div class="auth-input-wrap">
<span class="auth-input-icon" aria-hidden="true">✉</span>
<input
                                autocomplete="off"
                                type="text"
                                id="identificador"
                                name="identificador"
                                required
                                autofocus
                                placeholder="seu@email.com ou @usuario"
                            >
</div>
</div>
<div class="form-grupo auth-field">
<label for="senha">Senha</label>
<div class="auth-input-wrap">
<span class="auth-input-icon" aria-hidden="true">◇</span>
<input
                                autocomplete="off"
                                type="password"
                                id="senha"
                                name="senha"
                                required
                                placeholder="Digite sua senha"
                            >
<button type="button" class="auth-password-toggle" id="toggleSenhaLogin" aria-label="Mostrar senha" aria-pressed="false">
<svg class="cp-eye cp-eye-open" viewBox="0 0 24 24" aria-hidden="true">
<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
<circle cx="12" cy="12" r="2.6"/>
</svg>
<svg class="cp-eye cp-eye-off" viewBox="0 0 24 24" aria-hidden="true">
<path d="M3 3l18 18"/>
<path d="M10.6 6.2A9.9 9.9 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-2.1 2.8"/>
<path d="M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.5 6 9.5 6a9.8 9.8 0 0 0 4.1-.9"/>
<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
</svg>
</button>
</div>
</div>
<div class="auth-login-support">
<a href="recuperar_senha.php">Esqueceu a senha?</a>
</div>
<button type="submit" class="btn btn-primario btn-bloco auth-main-button">
                        Entrar
                    </button>
</form>
<div class="auth-social-divider">
<span>ou</span>
</div>
<div class="cp-google-area">
                    <?php if ($googleClientId !== ''):?>
                        <div id="g_id_onload"
                             data-client_id="<?= limpar($googleClientId)?>"
                             data-callback="cashPilotGoogleCredential"
                             data-auto_prompt="false">
</div>
<div class="g_id_signin" data-type="standard" data-theme="outline" data-size="large" data-text="continue_with" data-shape="rectangular" data-logo_alignment="left" data-width="330">
</div>
                    <?php else:?>
                        <button type="button" class="cp-google-placeholder" onclick="alert('Configure GOOGLE_CLIENT_ID para ativar o login com Google neste computador.')">
<img class="cp-google-icon" src="../assets/img/google-g.svg" alt="" aria-hidden="true">
<span class="cp-google-label">Continuar com Google</span>
</button>
                    <?php endif;?>
                </div>
<p class="auth-rodape auth-transition-footer">
                    Ainda não tem conta?
                    <a href="cadastro.php" class="auth-switch-link" data-auth-target="cadastro">
                        Criar conta
                    </a>
</p>
</div>
</section>
<aside class="auth-transition-brand">
<div class="auth-brand-orbit auth-brand-orbit-1">
</div>
<div class="auth-brand-orbit auth-brand-orbit-2">
</div>
<div class="auth-brand-content">
<img
                    src="../assets/img/logo-cashpilot-v13.png"
                    class="auth-transition-logo"
                    alt="CashPilot"
                >
<span class="auth-brand-kicker">COMECE A ORGANIZAR</span>
<h2>Novo por aqui?</h2>
<p>
                    Crie sua conta e transforme seus dados financeiros em informações
                    mais claras para suas decisões.
                </p>
<a
                    href="cadastro.php"
                    class="auth-brand-action auth-switch-link"
                    data-auth-target="cadastro"
                >
                    Criar minha conta <span>→</span>
</a>
<small>Simples para começar. Feito para evoluir com você.</small>
</div>
</aside>
</main>
</div>
<script>
const senhaLogin=document.getElementById('senha'),toggleSenhaLogin=document.getElementById('toggleSenhaLogin');

toggleSenhaLogin?.addEventListener('click',()=>{const mostrando=senhaLogin.type==='text';senhaLogin.type=mostrando?'password':'text';toggleSenhaLogin.setAttribute('aria-label',mostrando?'Mostrar senha':'Ocultar senha');toggleSenhaLogin.classList.toggle('ativo',!mostrando);});
</script>
<script>
(function () {
    const card = document.getElementById('authTransitionCard');
    const links = document.querySelectorAll('.auth-switch-link');

    requestAnimationFrame(() => {
        document.body.classList.add('auth-page-ready');
    });

    links.forEach(link => {
        link.addEventListener('click', function (event) {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

            event.preventDefault();
            const destino = this.href;

            card.classList.add('auth-to-cadastro');
            document.body.classList.add('auth-is-transitioning');

            setTimeout(() => {
                window.location.href = destino;
            }, 520);
        });
    });
})();
</script>
<?php if ($googleClientId !== ''):?>
<script src="https://accounts.google.com/gsi/client" async defer>

</script>
<script>
function cashPilotGoogleCredential(response) {

    const form = document.createElement('form');

    form.method = 'POST';

    form.action = '../actions/google_callback.php';

    const input = document.createElement('input');

    input.type = 'hidden';
 input.name = 'credential';
 input.value = response.credential || '';

    form.appendChild(input);

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = 'csrf_token';
    csrf.value = <?= json_encode(csrfToken(), JSON_UNESCAPED_SLASHES) ?>;
    form.appendChild(csrf);
 document.body.appendChild(form);
 form.submit();

}
</script>
<?php endif;?>
<script src="../assets/js/interface.js?v=13.4">

</script>
<script src="../assets/js/ui-controls.js?v=13.4.1">

</script>
</body>
</html>
