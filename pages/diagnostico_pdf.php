<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/python_helper.php';

exigirLogin();

$python = caminhoPythonCashPilot();

$comandoVersao = escapeshellarg($python) . ' --version 2>&1';
exec($comandoVersao, $saidaVersao, $codigoVersao);

$comandoPdf = escapeshellarg($python)
    . ' -c "import pypdf; print(pypdf.__version__)" 2>&1';

exec($comandoPdf, $saidaPdf, $codigoPdf);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico PDF · CashPilot</title>
</head>
<body style="font-family:Arial,sans-serif;padding:32px">
    <h1>Diagnóstico do leitor PDF</h1>

    <p>
        <strong>Python configurado:</strong>
        <?= htmlspecialchars($python, ENT_QUOTES, 'UTF-8') ?>
    </p>

    <p>
        <strong>Python:</strong>
        <?= $codigoVersao === 0
            ? htmlspecialchars(implode(' ', $saidaVersao), ENT_QUOTES, 'UTF-8')
            : 'Falhou' ?>
    </p>

    <p>
        <strong>pypdf:</strong>
        <?= $codigoPdf === 0
            ? htmlspecialchars(implode(' ', $saidaPdf), ENT_QUOTES, 'UTF-8')
            : 'Falhou' ?>
    </p>
<script src="../assets/js/interface.js?v=13.4">

</script>
<script src="../assets/js/ui-controls.js?v=13.4.1">

</script>
</body>
</html>
