<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();
header('Content-Type: application/json; charset=utf-8');
$metaId=(int)($_GET['id']??0);$usuarioId=usuarioLogadoId();
if($metaId<=0){echo json_encode(['erro'=>'Meta inválida'],JSON_UNESCAPED_UNICODE);exit;}
$pasta=escapeshellarg(__DIR__.'/../python');$python=PHP_OS_FAMILY==='Windows'?'python':'python3';$cmd='cd '.$pasta.' && '.$python.' previsao.py '.escapeshellarg((string)$usuarioId).' '.escapeshellarg((string)$metaId).' 2>/dev/null';$saida=@shell_exec($cmd);$dados=json_decode($saida??'',true);if(!is_array($dados)){echo json_encode(['erro'=>'A previsão não está disponível no momento.'],JSON_UNESCAPED_UNICODE);exit;}echo json_encode($dados,JSON_UNESCAPED_UNICODE);
