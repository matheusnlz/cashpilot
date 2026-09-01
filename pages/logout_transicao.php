<!DOCTYPE html>
<html lang="pt-br" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Saindo · CashPilot</title>
<link rel="stylesheet" href="../assets/css/style.css?v=13.2">
<link rel="stylesheet" href="../assets/css/components.css?v=13.4">
<link rel="stylesheet" href="../assets/css/theme.css?v=13.4.1">
<meta http-equiv="Cache-Control" content="no-store">
</head>
<body class="logout-flight-body cp-auth-light">
<main class="logout-flight-screen">
<img src="../assets/img/logo-cashpilot-v13.png" class="logout-flight-logo" alt="CashPilot">
<div class="logout-flight-plane" aria-hidden="true">✈</div>
<p>Até a próxima</p>
</main>
<script>
try{
localStorage.removeItem('cashpilot_tema')}
catch(e){
}
setTimeout(()=>document.body.classList.add('logout-flight-go'),100);
setTimeout(()=>location.replace('login.php'),760);
</script>
<script src="../assets/js/interface.js?v=13.4">

</script>
<script src="../assets/js/ui-controls.js?v=13.4.1">

</script>
</body>
</html>