<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../database/conexao.php';
exigirLogin();
exigirPost();
validarCsrf();
$pdo=conectar();
$uid=usuarioLogadoId();
$acao=$_POST['acao']??'';

if($acao==='excluir') {
    $s=$pdo->prepare('DELETE FROM copiloto_conversas WHERE id=:id AND usuario_id=:uid');
    $s->execute(['id'=>(int)($_POST['id']??0),'uid'=>$uid]);

}

header('Location: ../pages/copiloto.php');
exit;
