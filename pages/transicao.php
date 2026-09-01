<?php

require_once __DIR__ . '/../includes/auth.php';

exigirLogin();

if (empty($_SESSION['mostrar_transicao_login'])) {
    header('Location: dashboard.php');
    exit;
}

unset($_SESSION['mostrar_transicao_login']);

$tema = 'light';

$destino = empty($_SESSION['onboarding_concluido'])
    ? 'boas_vindas.php'
    : 'dashboard.php';
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CashPilot</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=11.4.1">
<link rel="stylesheet" href="../assets/css/components.css?v=13.4">
<link rel="stylesheet" href="../assets/css/theme.css?v=13.4.1">
    <meta http-equiv="Cache-Control" content="no-store">
</head>
<body class="login-flight-body login-flight-v112 cp-auth-light">
<main class="login-flight-screen" aria-label="Entrando no CashPilot">
    <div class="login-flight-brand">
        <img src="../assets/img/logo-cashpilot-escura.png" alt="CashPilot">
    </div>

    <div class="login-flight-launch" aria-hidden="true">
        <span class="login-flight-runway"></span>
        <div class="login-flight-plane login-flight-plane-vertical">
            <svg viewBox="0 0 110 180" xmlns="http://www.w3.org/2000/svg">
                <g transform="translate(90 0) rotate(90)" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round" stroke-linecap="round">
                    <path d="M10 39h36l28-27h14L76 39h54l23-13h14l-13 20 13 13h-14l-23-10H77L89 64H75L47 49H10z"/>
                    <path d="M71 38h42"/>
                </g>
            </svg>
        </div>
        <span class="login-flight-thrust"></span>
    </div>

    <p>Preparando seu painel</p>
    <div class="login-flight-reveal" aria-hidden="true"></div>
</main>

<script>
const reduzirMovimento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const destino = <?= json_encode($destino) ?>;


setTimeout(() => {
    document.body.classList.add('login-flight-lifted');
}, reduzirMovimento ? 40 : 120);


setTimeout(() => {
    window.location.replace(destino);
}, reduzirMovimento ? 220 : 980);
</script>
<script src="../assets/js/interface.js?v=13.4">

</script>
<script src="../assets/js/ui-controls.js?v=13.4.1">

</script>
</body>
</html>
