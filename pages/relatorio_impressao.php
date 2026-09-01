<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../database/conexao.php';
require_once __DIR__.'/../includes/relatorios_financeiros.php';
exigirLogin();
$pdo=conectar();
$uid=usuarioLogadoId();
$mei=usuarioLogadoTipo()==='mei';
$p=cpRelPeriodo($_GET);
$r=cpRelResumo($pdo,$uid,$p['inicio'],$p['fim'],$mei);
$e=cpRelEvolucao($pdo,$uid,$p['inicio'],$p['fim']);
$nome=usuarioLogadoNome();?><!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Relatório CashPilot</title>
<style>
body{
font-family:Arial,sans-serif;
color:#222;
margin:32px}
.head{
display:flex;
justify-content:space-between;
border-bottom:2px solid #2f5d62;
padding-bottom:16px}
.brand{
font-size:26px;
font-weight:800}
.muted{
color:#6b7280}
.kpis{
display:grid;
grid-template-columns:repeat(4,1fr);
gap:12px;
margin:24px 0}
.k{
border:1px solid #ddd;
border-radius:10px;
padding:14px}
.k small{
color:#777}
.k strong{
display:block;
font-size:20px;
margin-top:7px}
table{
width:100%;
border-collapse:collapse;
margin-top:14px}
th,td{
padding:9px;
border-bottom:1px solid #ddd;
text-align:left}
th{
font-size:12px;
color:#666}
h2{
margin-top:28px;
font-size:18px}
.actions{
margin-bottom:18px}
.actions button{
padding:9px 14px}
.note{
margin-top:28px;
font-size:11px;
color:#777}
@media print{
.actions{
display:none}
body{
margin:12mm}
.kpis{
break-inside:avoid}
}
</style>
</head>
<body>
<div class="actions">
<button onclick="window.print()">Imprimir / Salvar como PDF</button>
</div>
<div class="head">
<div>
<div class="brand">CashPilot</div>
<div class="muted"><?= $mei?'Relatório do negócio':'Relatório financeiro pessoal'?></div>
</div>
<div>
<strong><?=htmlspecialchars($nome)?></strong>
<br>
<span class="muted"><?=htmlspecialchars(cpRelNomePeriodo($p['inicio'],$p['fim']))?></span>
</div>
</div>
<div class="kpis">
<div class="k">
<small>Receitas</small>
<strong><?=formatarMoeda($r['receitas'])?></strong>
</div>
<div class="k">
<small>Despesas</small>
<strong><?=formatarMoeda($r['despesas'])?></strong>
</div>
<div class="k">
<small>Resultado</small>
<strong><?=formatarMoeda($r['resultado'])?></strong>
</div>
<div class="k">
<small><?=$mei?'Margem':'Taxa de economia'?></small>
<strong><?=number_format($r['tx'],1,',','.')?>%</strong>
</div>
</div>
<h2>Despesas por categoria</h2>
<table>
<thead>
<tr>
<th>Categoria</th>
<th>Lançamentos</th>
<th>Total</th>
<th>Participação</th>
</tr>
</thead>
<tbody><?php foreach($r['gastos'] as $x):$pct=$r['despesas']>0?(float)$x['total']/$r['despesas']*100:0;?><tr>
<td><?=htmlspecialchars($x['categoria'])?></td>
<td><?=(int)$x['quantidade']?></td>
<td><?=formatarMoeda((float)$x['total'])?></td>
<td><?=number_format($pct,1,',','.')?>%</td>
</tr><?php endforeach;?></tbody>
</table>
<h2>Evolução mensal</h2>
<table>
<thead>
<tr>
<th>Mês</th>
<th>Receitas</th>
<th>Despesas</th>
<th>Resultado</th>
</tr>
</thead>
<tbody><?php foreach($e as $x):?><tr>
<td><?=htmlspecialchars($x['mes'])?></td>
<td><?=formatarMoeda($x['receitas'])?></td>
<td><?=formatarMoeda($x['despesas'])?></td>
<td><?=formatarMoeda($x['resultado'])?></td>
</tr><?php endforeach;?></tbody>
</table>
<p class="note">Relatório gerado pelo CashPilot com base apenas nos dados cadastrados no sistema. Não substitui documentação contábil oficial.</p>
</body>
</html>
