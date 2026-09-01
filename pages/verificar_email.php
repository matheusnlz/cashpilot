<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/email_service.php';

exigirLogin();

$pdo = conectar();
$uid = (int) usuarioLogadoId();

$stmt = $pdo->prepare(
    'SELECT email, email_verificado
     FROM usuarios
     WHERE id = :uid'
);
$stmt->execute(['uid' => $uid]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

if (!empty($usuario['email_verificado'])) {
    header('Location: perfil.php');
    exit;
}

$reenvios = (int) ($_SESSION['email_reenvios'] ?? 0);
$espera = $reenvios === 0 ? 30 : 300;
$segundosRestantes = cpSegundosParaReenvio(
    $pdo,
    $uid,
    'confirmacao_email',
    $espera
);

$msg = $_SESSION['email_msg'] ?? ($_SESSION['email_aviso_envio'] ?? null);
$msgTipo = $_SESSION['email_msg_tipo'] ?? 'erro';

unset(
    $_SESSION['email_msg'],
    $_SESSION['email_msg_tipo'],
    $_SESSION['email_aviso_envio']
);
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar e-mail · CashPilot</title>
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
                    <span class="auth-kicker">CONFIRMAÇÃO DE E-MAIL</span>
                    <h1>Verifique seu e-mail</h1>
                    <p>
                        Digite o código de 6 dígitos enviado para
                        <strong><?= limpar($usuario['email'] ?? '') ?></strong>.
                    </p>
                </div>

                <?php if ($msg): ?>
                    <div class="alerta-mensagem <?= limpar($msgTipo) ?>">
                        <?= limpar($msg) ?>
                    </div>
                <?php endif; ?>

                <form action="../actions/email_verificacao.php" method="POST">
                    <?= csrfCampo() ?>
                    <input type="hidden" name="acao" value="confirmar">

                    <div class="form-grupo">
                        <label for="codigo">Código de 6 dígitos</label>
                        <input
                            class="cp-code-input cp141-code-input"
                            id="codigo"
                            name="codigo"
                            inputmode="numeric"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            autocomplete="one-time-code"
                            required
                            autofocus
                            placeholder="000000"
                        >
                    </div>

                    <button
                        class="btn btn-primario btn-bloco auth-main-button"
                        type="submit"
                    >
                        Verificar e-mail
                    </button>
                </form>

                <div
                    class="cp141-resend-box"
                    data-resend-seconds="<?= (int) $segundosRestantes ?>"
                >
                    <p>Não recebeu o código?</p>

                    <form action="../actions/email_verificacao.php" method="POST">
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
                        Primeiro reenvio após 30 segundos. Depois disso,
                        o intervalo passa a ser de 5 minutos.
                    </small>
                </div>

                <a class="cp141-back-link" href="dashboard.php">
                    Confirmar mais tarde
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
                <span class="auth-brand-kicker">CONTA PROTEGIDA</span>
                <h2>Uma etapa rápida para proteger seu acesso.</h2>
                <p>
                    A confirmação do endereço ajuda a manter sua conta segura
                    e permite recuperar o acesso quando necessário.
                </p>

                <div class="cp141-verification-points">
                    <span>✓ Código protegido no banco</span>
                    <span>✓ Validade de 15 minutos</span>
                    <span>✓ Limite de tentativas</span>
                </div>

                <small>Nunca compartilhe códigos recebidos por e-mail.</small>
            </div>
        </aside>
    </main>
</div>

<script src="../assets/js/auth.js?v=14.1">

</script>
</body>
</html>
