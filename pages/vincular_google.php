<?php
require_once __DIR__.'/../includes/auth.php';
if(empty($_SESSION['google_link_pending'])||empty($_SESSION['google_link_usuario'])){
header('Location: login.php');
exit;
}
$g=$_SESSION['google_link_pending'];
$msg=$_SESSION['google_link_msg']??null;
unset($_SESSION['google_link_msg']);?><!DOCTYPE html>
<html lang="pt-br" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vincular Google · CashPilot</title>
<link rel="stylesheet" href="../assets/css/style.css?v=13.2">
<link rel="stylesheet" href="../assets/css/components.css?v=13.4">
<link rel="stylesheet" href="../assets/css/theme.css?v=13.4.1">
</head>
<body class="auth-body-transition cp-auth-light auth-page-ready">
<div class="auth-tela">
<main class="auth-caixa cp-code-card">
<img src="../assets/img/logo-cashpilot-v13.png" class="cp-code-logo" alt="CashPilot">
<span class="auth-kicker">CONTA ENCONTRADA</span>
<h1>Vincular sua conta Google</h1>
<p class="auth-subtitulo">Já existe uma conta CashPilot com <strong><?=limpar($g['email']??'')?></strong>. Confirme sua senha atual para vincular o Google com segurança.</p><?php if($msg):?><div class="alerta-mensagem erro"><?=limpar($msg)?></div><?php endif;?><form action="../actions/vincular_google.php" method="POST"><?=csrfCampo()?><div class="form-grupo">
<label>Senha atual do CashPilot</label>
<input type="password" name="senha" required>
</div>
<button class="btn btn-primario btn-bloco">Vincular Google</button>
</form>
<a class="back-link cp-code-back" href="login.php">Cancelar</a>
</main>
</div>
<script src="../assets/js/interface.js?v=13.4">

</script>
<script src="../assets/js/ui-controls.js?v=13.4.1">

</script>
</body>
</html>