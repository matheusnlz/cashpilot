<?php
require_once __DIR__.'/../includes/auth.php';

require_once __DIR__.'/../database/conexao.php';

exigirPost();

validarCsrf();

$g=$_SESSION['google_link_pending']??null;

$uid=(int)($_SESSION['google_link_usuario']??0);

if(!$g||!$uid) {

    header('Location: ../pages/login.php');

    exit;

}

$pdo=conectar();

$stmt=$pdo->prepare('SELECT * FROM usuarios WHERE id=:uid');

$stmt->execute(['uid'=>$uid]);

$u=$stmt->fetch(PDO::FETCH_ASSOC);

if(!$u||!password_verify($_POST['senha']??'',$u['senha_hash'])) {

    $_SESSION['google_link_msg']='Senha incorreta.';

    header('Location: ../pages/vincular_google.php');

    exit;

}

$pdo->prepare('INSERT INTO usuario_oauth(usuario_id,provider,provider_user_id,email_provider) VALUES(:uid,"google",:sub,:email) ON DUPLICATE KEY UPDATE email_provider=VALUES(email_provider)')->execute(['uid'=>$uid,'sub'=>$g['sub'],'email'=>$g['email']]);

$pdo->prepare('UPDATE usuarios SET email_verificado=1,email_verificado_em=COALESCE(email_verificado_em,NOW()) WHERE id=:uid')->execute(['uid'=>$uid]);

unset($_SESSION['google_link_pending'],$_SESSION['google_link_usuario']);

session_regenerate_id(true);
renovarCsrf();

$_SESSION['usuario_id']=$uid;

$_SESSION['usuario_nome']=$u['nome'];

$_SESSION['usuario_username']=$u['username']??'';

$_SESSION['usuario_tipo']=$u['tipo_perfil'];

$_SESSION['usuario_avatar']=$u['avatar_path']??'';

$_SESSION['tema_preferido']=($u['tema_preferido']??'light')==='dark'?'dark':'light';

$_SESSION['onboarding_concluido']=(int)($u['onboarding_concluido']??1);

$_SESSION['email_verificado']=1;

$_SESSION['mostrar_transicao_login']=true;

header('Location: ../pages/transicao.php');

exit;
