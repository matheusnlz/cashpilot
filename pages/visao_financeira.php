<?php
require_once __DIR__.'/../includes/auth.php';

require_once __DIR__.'/../database/conexao.php';

require_once __DIR__.'/../includes/inteligencia_financeira.php';

require_once __DIR__.'/../includes/negocio_financeiro.php';

require_once __DIR__.'/../includes/cashpilot14_financeiro.php';

exigirLogin();

$tituloPagina='Visão Financeira';
$pdo=conectar();
$uid=(int)usuarioLogadoId();
$mei=usuarioLogadoTipo()==='mei';

if($mei){
cpSincronizarCustosRecorrentesMes($pdo,$uid);
$v=cp143VisaoFinanceiraMEI($pdo,$uid);
}
else{
$v=cp143VisaoFinanceiraPF($pdo,$uid);
$score=cpCashScore($pdo,$uid);
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="page-head">
<div>
<span class="eyebrow"><?=$mei?'PANORAMA DO NEGÓCIO':'PANORAMA INTEGRADO'?></span>
<h1>Visão Financeira</h1>
<p><?=$mei?'Entenda caixa, resultado e compromissos a partir do que já foi registrado.':'Veja em uma única leitura como fluxo, planejamento, reserva, metas e patrimônio se relacionam.'?></p>
</div>
<button type="button" class="btn btn-secundario" data-copiloto-pergunta="Analise minha Visão Financeira atual usando apenas meus dados do CashPilot. Destaque um ponto positivo, um ponto de atenção e uma ação prática.">✦ Explicar minha situação</button>
</div>
<section class="cp143-status-grid">
<?php if($mei):?>
<article>
<span>Resultado do mês</span>
<strong class="<?=$v['resultado']>=0?'positivo':'negativo'?>"><?=formatarMoeda($v['resultado'])?></strong>
<small><?=formatarMoeda($v['receitas'])?> recebidos · <?=formatarMoeda($v['despesas'])?> gastos</small>
</article>
<article>
<span>Caixa atual</span>
<strong><?=formatarMoeda((float)$v['projecao']['saldo_atual'])?></strong>
<small>Saldo realizado até hoje</small>
</article>
<article>
<span>Projeção 30 dias</span>
<strong class="<?=$v['projecao']['caixa_projetado']>=0?'positivo':'negativo'?>"><?=formatarMoeda((float)$v['projecao']['caixa_projetado'])?></strong>
<small>Estimativa, não garantia de resultado</small>
</article>
<article>
<span>Compromissos futuros</span>
<strong><?=formatarMoeda((float)$v['projecao']['compromissos_previstos'])?></strong>
<small>Saídas registradas nos próximos 30 dias</small>
</article>
<?php else:?>
<article>
<span>Fluxo do mês</span>
<strong class="<?=$v['resultado']>=0?'positivo':'negativo'?>"><?=formatarMoeda($v['resultado'])?></strong>
<small>Receitas menos despesas</small>
</article>
<article>
<span>Planejamento</span>
<strong><?=$v['categorias_planejadas']?($v['categorias_dentro'].'/'.$v['categorias_planejadas']):'—'?></strong>
<small><?=$v['categorias_planejadas']?'categorias dentro do limite':'Nenhum limite mensal definido'?></small>
</article>
<article>
<span>Reserva</span>
<strong><?=number_format((float)($v['reserva']['cobertura_meses']??0),1,',','.')?> mês(es)</strong>
<small><?=formatarMoeda((float)($v['reserva']['valor_atual']??0))?> registrados</small>
</article>
<article>
<span>CashScore</span>
<strong><?=$score['score']?>/100</strong>
<small><?=limpar($score['nivel'])?> · baseado nos dados registrados</small>
</article>
<?php endif;?>
</section>
<div class="cp143-insight-grid">
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">PONTOS POSITIVOS</span>
<h2>O que está funcionando</h2>
</div>
</div><?php if(!$v['positivos']):?><p class="texto-vazio">Ainda não há dados suficientes para destacar um ponto positivo.</p><?php else:?><div class="cp143-insight-list positivo-list"><?php foreach($v['positivos'] as $x):?><div>
<i>✓</i>
<p><?=limpar($x)?></p>
</div><?php endforeach;?></div><?php endif;?></section>
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">PONTOS DE ATENÇÃO</span>
<h2>O que merece acompanhamento</h2>
</div>
</div><?php if(!$v['alertas']):?><p class="texto-vazio">Nenhum alerta relevante com os dados atuais.</p><?php else:?><div class="cp143-insight-list alerta-list"><?php foreach($v['alertas'] as $x):?><div>
<i>!</i>
<p><?=limpar($x)?></p>
</div><?php endforeach;?></div><?php endif;?></section>
</div>
<?php if(!$mei):?>
<div class="cp143-insight-grid">
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">PATRIMÔNIO ACOMPANHADO</span>
<h2>O que o CashPilot consegue enxergar</h2>
</div>
<a class="link-limpar" href="investimentos.php">Investimentos →</a>
</div>
<div class="cp143-patrimonio">
<strong><?=formatarMoeda($v['patrimonio']['patrimonio_acompanhado'])?></strong>
<div>
<span>Saldo registrado <b><?=formatarMoeda($v['patrimonio']['saldo_financeiro'])?></b>
</span>
<span>Investimentos <b><?=formatarMoeda($v['patrimonio']['investimentos'])?></b>
</span>
</div>
</div>
<small><?=limpar($v['patrimonio']['observacao'])?></small>
</section>
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">OBJETIVOS</span>
<h2>Metas em andamento</h2>
</div>
<a class="link-limpar" href="metas.php">Abrir metas →</a>
</div><?php if(!$v['metas']):?><p class="texto-vazio">Nenhuma meta em andamento.</p><?php else:?><div class="cp143-goals"><?php foreach($v['metas'] as $m):?><div>
<div>
<strong><?=limpar($m['titulo'])?></strong>
<span><?=number_format($m['percentual'],0)?>%</span>
</div>
<div class="learning-progress">
<span style="width:<?=$m['percentual']?>%">
</span>
</div>
<small>Faltam <?=formatarMoeda($m['falta'])?><?php if($m['mensal_necessario']!==null):?> · cerca de <?=formatarMoeda($m['mensal_necessario'])?>/mês até o prazo<?php endif;?></small>
</div><?php endforeach;?></div><?php endif;?></section>
</div>
<?php else:?>
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">PROJEÇÃO</span>
<h2>Leitura dos próximos 30 dias</h2>
</div>
<a class="link-limpar" href="projecao_caixa.php">Ver projeção completa →</a>
</div>
<div class="cp143-business-flow">
<div>
<span>Saldo atual</span>
<strong><?=formatarMoeda((float)$v['projecao']['saldo_atual'])?></strong>
</div>
<b>+</b>
<div>
<span>Entradas estimadas</span>
<strong><?=formatarMoeda((float)$v['projecao']['receita_prevista'])?></strong>
</div>
<b>−</b>
<div>
<span>Saídas registradas</span>
<strong><?=formatarMoeda((float)$v['projecao']['compromissos_previstos'])?></strong>
</div>
<b>=</b>
<div>
<span>Caixa projetado</span>
<strong><?=formatarMoeda((float)$v['projecao']['caixa_projetado'])?></strong>
</div>
</div>
<p class="secao-ajuda"><?=limpar($v['projecao']['observacao'])?></p>
</section>
<?php endif;?>
<?php require_once __DIR__.'/../includes/footer.php';?>
