<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/email_service.php';

redirecionarSeLogado();

$uid = (int) ($_SESSION['reset_usuario_id'] ?? 0);
$email = (string) ($_SESSION['reset_email'] ?? '');

if (!$uid || $email === '') {
    header('Location: recuperar_senha.php');
    exit;
}

$pdo = conectar();
$reenvios = (int) ($_SESSION['reset_reenvios'] ?? 0);
$espera = $reenvios === 0 ? 30 : 300;
$segundosRestantes = cpSegundosParaReenvio(
    $pdo,
    $uid,
    'recuperacao_senha',
    $espera
);

$msg = $_SESSION['reset_msg'] ?? null;
$msgTipo = $_SESSION['reset_msg_tipo'] ?? 'erro';

unset($_SESSION['reset_msg'], $_SESSION['reset_msg_tipo']);

$partes = explode('@', $email, 2);
$usuarioEmail = $partes[0] ?? '';
$dominioEmail = $partes[1] ?? '';
$inicio = mb_substr($usuarioEmail, 0, min(2, mb_strlen($usuarioEmail)));
$emailMascarado = $inicio . str_repeat('•', max(3, mb_strlen($usuarioEmail) - 2))
    . ($dominioEmail !== '' ? '@' . $dominioEmail : '');
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar código · CashPilot</title>
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
                    <span class="auth-kicker">CÓDIGO DE SEGURANÇA</span>
                    <h1>Confira seu e-mail</h1>
                    <p>
                        Digite o código de 6 dígitos enviado para
                        <strong><?= limpar($emailMascarado) ?></strong>.
                    </p>
                </div>

                <?php if ($msg): ?>
                    <div class="alerta-mensagem <?= limpar($msgTipo) ?>">
                        <?= limpar($msg) ?>
                    </div>
                <?php endif; ?>

                <form action="../actions/recuperar_senha.php" method="POST">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="acao" value="confirmar_codigo">

                    <div class="form-grupo">
                        <label for="codigo">Código de 6 dígitos</label>
                        <input
                            class="cp-code-input cp141-code-input"
                            id="codigo"
                            name="codigo"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            required
                            autofocus
                            placeholder="000000"
                        >
                    </div>

                    <button
                        type="submit"
                        class="btn btn-primario btn-bloco auth-main-button"
                    >
                        Confirmar código
                    </button>
                </form>

                <div
                    class="cp141-resend-box"
                    data-resend-seconds="<?= (int) $segundosRestantes ?>"
                >
                    <p>Não recebeu o código?</p>

                    <form action="../actions/recuperar_senha.php" method="POST">
                        <?= csrfCampo() ?>
                        <input type="hidden" name="acao" value="reenviar">

                        <button
                            type="submit"
                            class="cp141-resend-button"
                            data-resend-button
                            <?= $segundosRestantes > 0 ? 'disabled' : '' ?>
                        >
                            <span data-resend-label>
                                <?= $segundosRestantes > 0
                                    ? 'Reenviar em ' . $segundosRestantes . 's'
                                    : 'Reenviar código' ?>
                            </span>
                        </button>
                    </form>

                    <small>
                        Após o primeiro reenvio, novos códigos ficam disponíveis
                        a cada 5 minutos.
                    </small>
                </div>

                <a class="cp141-back-link" href="recuperar_senha.php">
                    Usar outro e-mail
                </a>
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
                <h2>Falta pouco para recuperar seu acesso.</h2>
                <p>
                    O código confirma que você tem acesso ao endereço
                    de e-mail associado à conta.
                </p>

                <div class="cp141-auth-steps">
                    <span><b>1</b> Informe seu e-mail</span>
                    <span class="ativo"><b>2</b> Confirme o código</span>
                    <span><b>3</b> Crie uma nova senha</span>
                </div>

                <small>Nunca compartilhe seu código de segurança.</small>
            </div>
        </aside>
    </main>
</div>

<script src="../assets/js/auth.js?v=14.1">

</script>
</body>
</html>
