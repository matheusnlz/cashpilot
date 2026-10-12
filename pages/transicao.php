<?php

require_once __DIR__ . '/../includes/auth.php';

exigirLogin();

if (empty($_SESSION['mostrar_transicao_login'])) {
    header('Location: dashboard.php');
    exit();
}

unset($_SESSION['mostrar_transicao_login']);

$tema = 'light';

$destino = empty($_SESSION['onboarding_concluido']) ? 'boas_vindas.php' : 'dashboard.php';
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
        <link rel="stylesheet" href="../assets/css/cashpilot16_3.css?v=16.6">
        <meta http-equiv="Cache-Control" content="no-store">
    </head>

    <body class="login-flight-body login-flight-v112 cp-auth-light">
        <main class="login-flight-screen" aria-label="Entrando no CashPilot">
            <div class="login-flight-brand">
                <img src="../assets/img/logo-cashpilot-transparente.png" alt="CashPilot">
            </div>

            <div class="login-flight-launch" aria-hidden="true">
                <span class="login-flight-runway"></span>
                <div class="login-flight-plane login-flight-plane-vertical">
                    <svg viewBox="0 0 120 190" xmlns="http://www.w3.org/2000/svg" role="img"
                        aria-label="Avião CashPilot">
                        <defs>
                            <linearGradient id="cpPlaneGreen" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0" stop-color="#6fd9a2" />
                                <stop offset="0.52" stop-color="#3eaf7c" />
                                <stop offset="1" stop-color="#216b5d" />
                            </linearGradient>
                            <linearGradient id="cpPlaneGlass" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0" stop-color="#effff7" stop-opacity=".96" />
                                <stop offset="1" stop-color="#bcebd1" stop-opacity=".78" />
                            </linearGradient>
                        </defs>
                        <g filter="url(#none)">
                            <!-- Silhueta original inspirada em um jato comercial visto de cima. -->
                            <path
                                d="M60 5c-5.7 0-9.2 6.1-10.1 17.8L47.8 58 13.2 80.8c-4.2 2.8-6.3 6.7-5.7 10.4.4 2.4 2 4 4.8 4.8l34.6-8.1-1.7 48.4-16.7 15.2c-3.8 3.5-5.2 7.8-3.4 10.8 1.4 2.4 4.5 3.2 8.6 2.2L60 156l26.3 8.5c4.1 1 7.2.2 8.6-2.2 1.8-3 .4-7.3-3.4-10.8l-16.7-15.2-1.7-48.4 34.6 8.1c2.8-.8 4.4-2.4 4.8-4.8.6-3.7-1.5-7.6-5.7-10.4L72.2 58l-2.1-35.2C69.2 11.1 65.7 5 60 5Z"
                                fill="url(#cpPlaneGreen)" stroke="#195b4f" stroke-width="2.2"
                                stroke-linejoin="round" />
                            <path
                                d="M60 14c-2.3 0-3.9 4.1-4.4 11.7l-1.1 22.6h11l-1.1-22.6C63.9 18.1 62.3 14 60 14Z"
                                fill="url(#cpPlaneGlass)" opacity=".95" />
                            <path d="M51.2 73.5 20 88.2l29.9-5.8m18.9-8.9L100 88.2l-29.9-5.8"
                                fill="none" stroke="#d8f6e5" stroke-width="2.1"
                                stroke-linecap="round" opacity=".72" />
                            <path d="M60 153V52" fill="none" stroke="#d8f6e5" stroke-width="1.8"
                                stroke-linecap="round" opacity=".5" />
                            <path d="M49.1 141.5 60 138l10.9 3.5" fill="none" stroke="#d8f6e5"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                opacity=".62" />
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
