<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();
$token = $_GET['token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) { http_response_code(403); exit('Solicitação inválida.'); }
$pdo=conectar();$uid=usuarioLogadoId();
$stmt=$pdo->prepare("(SELECT 'receita' tipo,descricao,valor,data_receita data,c.nome categoria FROM receitas r LEFT JOIN categorias c ON c.id=r.categoria_id WHERE r.usuario_id=:a) UNION ALL (SELECT 'despesa' tipo,descricao,valor,data_despesa data,c.nome categoria FROM despesas d LEFT JOIN categorias c ON c.id=d.categoria_id WHERE d.usuario_id=:b) ORDER BY data DESC");$stmt->execute(['a'=>$uid,'b'=>$uid]);$mov=$stmt->fetchAll();
$nome='cashpilot_exportacao_'.date('Y-m-d').'.csv';header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$nome.'"');echo "\xEF\xBB\xBF";$out=fopen('php://output','w');fputcsv($out,['Tipo','Descrição','Categoria','Valor','Data'],';');foreach($mov as $m)fputcsv($out,[$m['tipo'],$m['descricao'],$m['categoria']??'Outros',number_format((float)$m['valor'],2,',','.'),date('d/m/Y',strtotime($m['data']))],';');fclose($out);exit;
