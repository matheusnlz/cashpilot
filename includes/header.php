<!DOCTYPE html>
<html lang="pt-br" data-theme="<?= limpar($_SESSION['tema_preferido'] ?? 'light') ?>">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

        <title>
            <?= isset($tituloPagina) ? limpar($tituloPagina) . ' | CashPilot' : 'CashPilot' ?>
             </title>

        <link rel="icon" href="../assets/icons/favicon.ico">
        <link rel="icon" type="image/png" sizes="32x32" href="../assets/icons/favicon-32x32.png">
        <link rel="apple-touch-icon" href="../assets/icons/apple-touch-icon.png">
        <link rel="manifest" href="../assets/icons/site.webmanifest">

        <script>
            (function() {
                try {
                    const temaServidor =
                        <?= json_encode($_SESSION['tema_preferido'] ?? 'light') ?>;
                    const temaLocal = localStorage.getItem('cashpilot_tema');
                    const tema =
                        temaServidor === 'dark' || temaServidor === 'light' ?
                        temaServidor :
                        (temaLocal === 'dark' ? 'dark' : 'light');
                    document.documentElement.setAttribute('data-theme', tema);
                    localStorage.setItem('cashpilot_tema', tema);
                } catch (erro) {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            })();
        </script>

        <link rel="stylesheet" href="../assets/css/style.css?v=13.0">
        <link rel="stylesheet" href="../assets/css/components.css?v=13.4">
        <link rel="stylesheet" href="../assets/css/theme.css?v=13.4.1">
        <link rel="stylesheet" href="../assets/css/modules.css?v=14.3.1">
        <link rel="stylesheet" href="../assets/css/reports.css?v=14.5">
        <link rel="stylesheet" href="../assets/css/responsive.css?v=14.8.2">
        <link rel="stylesheet" href="../assets/css/cashpilot15.css?v=15.2.2">
        <link rel="stylesheet" href="../assets/css/cashpilot16.css?v=16">
        <link rel="stylesheet" href="../assets/css/cashpilot16_3.css?v=16.6">
        <link rel="stylesheet" href="../assets/css/cashpilot16_8.css?v=16.9">
        <link rel="stylesheet" href="../assets/css/cashpilot17.css?v=17.1">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js">

        </script>
    </head>

    <body class="<?= limpar($classeBody ?? '') ?>">
