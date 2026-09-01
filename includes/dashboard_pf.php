<?php
require_once __DIR__.'/pf_financeiro.php';

require_once __DIR__.'/inteligencia_financeira.php';

require_once __DIR__.'/cashpilot14_financeiro.php';

cpSincronizarRecorrenciasPF($pdo,$usuarioId);


$inicio=date('Y-m-01');
$fim=date('Y-m-t');
$ia=date('Y-m-01',strtotime('first day of last month'));
$fa=date('Y-m-t',strtotime('last day of last month'));

$receitas=cpTotalPeriodo($pdo,'receitas','data_receita',$usuarioId,$inicio,$fim);

$despesas=cpTotalPeriodo($pdo,'despesas','data_despesa',$usuarioId,$inicio,$fim);

$receitasAnt=cpTotalPeriodo($pdo,'receitas','data_receita',$usuarioId,$ia,$fa);

$despesasAnt=cpTotalPeriodo($pdo,'despesas','data_despesa',$usuarioId,$ia,$fa);

$resultado=$receitas-$despesas;
$saldo=cpSaldoGeral($pdo,$usuarioId);

$mensal=cpDadosMensais($pdo,$usuarioId,12);
$diario=cpDadosDiariosMes($pdo,$usuarioId);

$cats=cpCategoriasDespesas($pdo,$usuarioId,$inicio,$fim,5);
$rec=cpResumoRecorrenciasPF($pdo,$usuarioId);
$cashScoreDash=cpCashScore($pdo,$usuarioId);
$patrimonio14=cp14PatrimonioPF($pdo,$usuarioId);
$investimentos14=cp14ResumoInvestimentos($pdo,$usuarioId);


$planejamentoDash=cp14ResumoPlanejamento($pdo,$usuarioId,date('Y-m'));

$orc=[];
$limTotal=0;
$gastoLimite=0;

foreach($planejamentoDash['categorias'] as $catPlano){

    if((float)$catPlano['planejado']<=0)continue;

    $orc[]=['nome'=>$catPlano['nome'],'limite_mensal'=>(float)$catPlano['planejado'],'gasto'=>(float)$catPlano['realizado']];

    $limTotal+=(float)$catPlano['planejado'];
$gastoLimite+=(float)$catPlano['realizado'];
}


$s=$pdo->prepare('SELECT titulo,valor_meta,valor_atual,prazo FROM metas WHERE usuario_id=:uid AND concluida=0 ORDER BY prazo IS NULL,prazo ASC LIMIT 3');
$s->execute(['uid'=>$usuarioId]);
$metas=$s->fetchAll(PDO::FETCH_ASSOC);


$s=$pdo->prepare('SELECT * FROM recorrencias_pf WHERE usuario_id=:uid AND ativo=1 ORDER BY proxima_data LIMIT 6');
$s->execute(['uid'=>$usuarioId]);
$proximos=$s->fetchAll(PDO::FETCH_ASSOC);


$varD=$despesasAnt>0?($despesas-$despesasAnt)/$despesasAnt*100:null;

$horaAtual=(int)date('G');

$saudacaoDashboard=$horaAtual<12?'Bom dia':($horaAtual<18?'Boa tarde':'Boa noite');

$primeiroNome=explode(' ',trim(usuarioLogadoNome()))[0]??usuarioLogadoNome();

$fraseDashboard='Acompanhe seu mês, seus planos e o que merece atenção.';

if($despesasAnt>0){

    $dif=$despesas-$despesasAnt;

    if(abs($dif)>0.01){

        $pct=abs($dif/$despesasAnt*100);

        $fraseDashboard=$dif<0
            ? 'Suas despesas estão '.number_format($pct,1,',','.').'% menores que no mesmo período do mês anterior.'
            : 'Suas despesas estão '.number_format($pct,1,',','.').'% maiores que no mesmo período do mês anterior.';
}
}

$radar=[];

if($resultado<0)$radar[]=['vermelho','Resultado negativo','As despesas superam as receitas em '.formatarMoeda(abs($resultado)).'.'];

if($varD!==null&&$varD>=15)$radar[]=['amarelo','Despesas aceleraram','Você gastou '.number_format($varD,1,',','.').'% a mais que no mês anterior.'];

foreach($orc as $o)if((float)$o['limite_mensal']>0 && (float)$o['gasto']/(float)$o['limite_mensal']>=.85)$radar[]=[((float)$o['gasto']>(float)$o['limite_mensal']?'vermelho':'amarelo'),'Orçamento de '.$o['nome'],number_format((float)$o['gasto']/(float)$o['limite_mensal']*100,0).'% do limite já utilizado.'];

if($receitas>0&&$resultado/$receitas>=.2)$radar[]=['verde','Boa folga','Seu resultado positivo representa '.number_format($resultado/$receitas*100,0).'% das receitas.'];?>
<div class="page-head dashboard-head">
<div>
<span class="eyebrow">VISÃO FINANCEIRA</span>
<h1><?=limpar($saudacaoDashboard)?>, <?=limpar($primeiroNome)?>.</h1>
<p><?=limpar($fraseDashboard)?></p>
</div>
<div class="cp14-head-actions">
<a class="btn btn-secundario" href="visao_financeira.php">Visão Financeira</a>
<button class="btn btn-secundario" data-copiloto-pergunta="Explique meu mês atual em linguagem simples. Mostre o principal ponto positivo, o principal risco e uma ação prática.">✦ Explicar meu mês</button>
</div>
</div>
<section class="dashboard-primary">
<article class="hero-balance">
<span>Resultado do mês</span>
<strong class="<?=$resultado>=0?'positivo':'negativo'?>"><?=formatarMoeda($resultado)?></strong>
<p><?=$resultado>=0?'Você recebeu mais do que gastou até agora.':'Seus gastos estão acima das receitas neste mês.'?></p>
<a href="transacoes.php">Ver transações →</a>
</article>
<div class="metric-clean">
<span>Receitas</span>
<strong><?=formatarMoeda($receitas)?></strong>
<small><?= $receitasAnt>0 ? (($receitas-$receitasAnt)>=0?'↑ ':'↓ ').number_format(abs(($receitas-$receitasAnt)/$receitasAnt*100),1,',','.').'% vs. mês anterior' : 'Sem comparação anterior'?></small>
</div>
<div class="metric-clean">
<span>Despesas</span>
<strong><?=formatarMoeda($despesas)?></strong>
<small><?= $varD!==null ? (($varD>=0?'↑ ':'↓ ').number_format(abs($varD),1,',','.').'% vs. mês anterior') : 'Sem comparação anterior'?></small>
</div>
<a class="metric-clean metric-link" href="saude_financeira.php">
<span>CashScore</span>
<strong><?=$cashScoreDash['score']?>/100</strong>
<small><?=limpar($cashScoreDash['nivel'])?> · abrir saúde financeira</small>
</a>
</section>
<div class="dashboard-grid-main">
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">EVOLUÇÃO</span>
<h2>Receitas x Despesas</h2>
</div>
<div class="periodos-grafico">
<button class="periodo ativo" data-meses="1">1M</button>
<button class="periodo" data-meses="3">3M</button>
<button class="periodo" data-meses="6">6M</button>
<button class="periodo" data-meses="12">12M</button>
</div>
</div>
<div class="container-grafico">
<canvas id="graficoEvolucao">
</canvas>
</div>
</section>
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">PRÓXIMOS PAGAMENTOS</span>
<h2>O que vem pela frente</h2>
</div>
<a href="transacoes.php?visual=calendario" class="link-limpar">Calendário →</a>
</div>
  <?php if(!$proximos):?><div class="mini-empty">
<p>Nenhuma recorrência futura cadastrada.</p>
<a href="recorrencias.php">Adicionar recorrência</a>
</div>
  <?php else:?><div class="upcoming-list"><?php foreach($proximos as $p):?><div>
<div class="date-chip">
<b><?=date('d',strtotime($p['proxima_data']))?></b>
<small><?=mesAbreviadoPt($p['proxima_data'])?></small>
</div>
<div>
<strong><?=limpar($p['nome'])?></strong>
<small><?=limpar($p['tipo']==='assinatura'?'Assinatura':'Recorrência')?></small>
</div>
<strong><?=formatarMoeda((float)$p['valor'])?></strong>
</div><?php endforeach;?></div><?php endif;?>
 </section>
</div>
<div class="dashboard-grid-secondary">
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">PLANEJAMENTO</span>
<h2>Planejado x realizado</h2>
</div>
<a href="planejamento.php" class="link-limpar">Gerenciar →</a>
</div>
  <?php if($limTotal<=0):?><div class="mini-empty">
<p>Defina limites por categoria no planejamento mensal.</p>
<a href="planejamento.php">Criar planejamento</a>
</div><?php else:$pct=min(100,$gastoLimite/$limTotal*100);?><div class="big-progress">
<div>
<strong><?=formatarMoeda($gastoLimite)?></strong>
<span> de <?=formatarMoeda($limTotal)?></span>
</div>
<b><?=number_format($pct,0)?>%</b>
</div>
<div class="learning-progress budget-progress">
<span style="width:<?=$pct?>%">
</span>
</div>
<small><?=formatarMoeda(max(0,$limTotal-$gastoLimite))?> ainda disponíveis nos limites definidos.</small><?php endif;?>
 </section>
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">METAS</span>
<h2>Seus objetivos</h2>
</div>
<a href="metas.php" class="link-limpar">Abrir metas →</a>
</div>
  <?php if(!$metas):?><div class="mini-empty">
<p>Você ainda não possui metas em andamento.</p>
<a href="metas.php">Criar meta</a>
</div><?php else:?><div class="goal-mini-list"><?php foreach($metas as $m):$pct=(float)$m['valor_meta']>0?min(100,(float)$m['valor_atual']/(float)$m['valor_meta']*100):0;?><div>
<div>
<strong><?=limpar($m['titulo'])?></strong>
<span><?=number_format($pct,0)?>%</span>
</div>
<div class="learning-progress">
<span style="width:<?=$pct?>%">
</span>
</div>
<small><?=formatarMoeda((float)$m['valor_atual'])?> de <?=formatarMoeda((float)$m['valor_meta'])?></small>
</div><?php endforeach;?></div><?php endif;?>
 </section>
<section class="surface-card cp14-patrimony-card">
<div class="section-title">
<div>
<span class="eyebrow">PATRIMÔNIO ACOMPANHADO</span>
<h2>Saldo + investimentos</h2>
</div>
<a href="investimentos.php" class="link-limpar">Investimentos →</a>
</div>
<div class="cp14-patrimony-value">
<strong><?=formatarMoeda($patrimonio14['patrimonio_acompanhado'])?></strong>
<span>valor financeiro acompanhado no CashPilot</span>
</div>
<div class="cp14-patrimony-split">
<div>
<span>Saldo registrado</span>
<strong><?=formatarMoeda($patrimonio14['saldo_financeiro'])?></strong>
</div>
<div>
<span>Investimentos</span>
<strong><?=formatarMoeda($patrimonio14['investimentos'])?></strong>
</div>
</div>
<small><?=limpar($patrimonio14['observacao'])?></small>
</section>
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">RADARPILOT</span>
<h2>O que merece atenção</h2>
</div>
<a href="radar.php" class="link-limpar">Ver radar →</a>
</div>
  <?php if(!$radar):?><p class="texto-vazio">Nenhum alerta relevante com os dados atuais.</p><?php else:?><div class="radar-compact"><?php foreach(array_slice($radar,0,3) as $r):?><div class="<?=$r[0]?>">
<i>
</i>
<div>
<strong><?=limpar($r[1])?></strong>
<p><?=limpar($r[2])?></p>
</div>
</div><?php endforeach;?></div><?php endif;?>
 </section>
</div>
<script src="../assets/js/dashboard.js">

</script>
<script>
inicializarGraficoEvolucao({mensal:<?=json_encode($mensal,JSON_UNESCAPED_UNICODE)?>,diario:<?=json_encode($diario,JSON_UNESCAPED_UNICODE)?>});
</script>
