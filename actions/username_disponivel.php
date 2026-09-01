<?php
require_once __DIR__.'/../includes/auth.php';

require_once __DIR__.'/../database/conexao.php';

header('Content-Type: application/json; charset=utf-8');

$raw=mb_strtolower(trim((string)($_GET['username']??'')));

$raw=ltrim($raw,'@');

$valido=(bool)preg_match('/^[a-z0-9._]{3,20}$/',$raw);

if(!$valido) {
    echo json_encode(['disponivel'=>false,'valido'=>false,'mensagem'=>'Use 3 a 20 caracteres: letras, números, ponto ou _.'],JSON_UNESCAPED_UNICODE);
    exit;

}

$pdo=conectar();
$uid=usuarioLogadoId();

$sql='SELECT id FROM usuarios WHERE username=:u'.($uid?' AND id<>:uid':'').' LIMIT 1';

$s=$pdo->prepare($sql);
$p=['u'=>$raw];
if($uid)$p['uid']=$uid;
$s->execute($p);

$disp=!$s->fetch();

echo json_encode(['disponivel'=>$disp,'valido'=>true,'username'=>$raw,'mensagem'=>$disp?'Nome de usuário disponível.':'Este nome de usuário já está em uso.'],JSON_UNESCAPED_UNICODE);
