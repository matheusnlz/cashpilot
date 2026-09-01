<?php
require_once __DIR__.'/../includes/auth.php';
 require_once __DIR__.'/../database/conexao.php';
 require_once __DIR__.'/../includes/relatorios_financeiros.php';
 exigirLogin();

$tituloPagina='Relatórios';
$pdo=conectar();
$uid=usuarioLogadoId();
$mei=usuarioLogadoTipo()==='mei';
$p=cpRelPeriodo($_GET);
$inicio=$p['inicio'];
$fim=$p['fim'];
$res=cpRelResumo($pdo,$uid,$inicio,$fim,$mei);
$evol=cpRelEvolucao($pdo,$uid,$inicio,$fim);
$cmp=cpRelComparacao($pdo,$uid,$inicio,$fim);

$vr=cpRelVariacao($res['receitas'],$cmp['anterior']['receitas']);
$vd=cpRelVariacao($res['despesas'],$cmp['anterior']['despesas']);
$vresult=cpRelVariacao($res['resultado'],$cmp['anterior']['resultado']);
$maior=$res['gastos'][0]??null;
$origem=$res['entradas'][0]??null;

$token=$_SESSION['csrf_token']??'';

require_once __DIR__.'/../includes/header.php';
 require_once __DIR__.'/../includes/navbar.php';?>
<div class="topo-pagina rel145-topo">
<div>
<h1>Relatórios</h1>
<p><?= $mei?'Acompanhe faturamento, custos, resultado e evolução do negócio.':'Entenda receitas, despesas, evolução e comportamento financeiro em um só lugar.'?></p>
</div>
<div class="topo-acoes">
<a class="btn btn-secundario" target="_blank" href="relatorio_impressao.php?<?=http_build_query(['preset'=>$p['preset'],'data_inicio'=>$inicio,'data_fim'=>$fim])?>">Imprimir / PDF</a>
<a class="btn btn-primario" href="../actions/exportar.php?<?=http_build_query(['token'=>$token,'preset'=>$p['preset'],'data_inicio'=>$inicio,'data_fim'=>$fim])?>">Exportar CSV</a>
</div>
</div>
<section class="surface-card rel145-filtros">
<form method="GET">
<div>
<label>Período</label>
<select name="preset" id="relPreset">
<option value="mes_atual" <?=$p['preset']==='mes_atual'?'selected':''?>>Mês atual</option>
<option value="mes_anterior" <?=$p['preset']==='mes_anterior'?'selected':''?>>Mês anterior</option>
<option value="3m" <?=$p['preset']==='3m'?'selected':''?>>Últimos 3 meses</option>
<option value="6m" <?=$p['preset']==='6m'?'selected':''?>>Últimos 6 meses</option>
<option value="12m" <?=$p['preset']==='12m'?'selected':''?>>Últimos 12 meses</option>
<option value="personalizado" <?=$p['preset']==='personalizado'?'selected':''?>>Personalizado</option>
</select>
</div>
<div class="rel145-custom">
<label>De</label>
<input type="date" name="data_inicio" value="<?=limpar($inicio)?>">
</div>
<div class="rel145-custom">
<label>Até</label>
<input type="date" name="data_fim" value="<?=limpar($fim)?>">
</div>
<button class="btn btn-secundario">Aplicar</button>
</form>
<small><?=limpar(cpRelNomePeriodo($inicio,$fim))?></small>
</section>
<div class="grade-resumo rel145-kpis">
<?php foreach([['Receitas',$res['receitas'],$vr,'positivo'],['Despesas',$res['despesas'],$vd,'negativo'],['Resultado',$res['resultado'],$vresult,$res['resultado']>=0?'positivo':'negativo'],[$mei?'Margem do período':'Taxa de economia',$res['tx'],null,'']] as $idx=>$k):?>
<div class="cartao cartao-metrica">
<div class="rotulo"><?=limpar($k[0])?></div>
<div class="valor <?=$k[3]?>"><?=$idx===3?number_format($k[1],1,',','.').'%':formatarMoeda($k[1])?></div><?php if($k[2]!==null):?><div class="variacao <?=$k[2]>=0?($idx===1?'negativo':'positivo'):($idx===1?'positivo':'negativo')?>"><?= $k[2]>=0?'↑':'↓'?> <?=number_format(abs($k[2]),1,',','.')?>% vs. período anterior</div><?php else:?><div class="variacao">Baseado apenas nos lançamentos registrados</div><?php endif;?></div>
<?php endforeach;?>
</div>
<div class="grade-dupla rel145-grid">
<section class="cartao">
<div class="section-title">
<div>
<span class="eyebrow">EVOLUÇÃO</span>
<h2><?= $mei?'Faturamento, despesas e resultado':'Receitas, despesas e resultado'?></h2>
</div>
</div>
<div class="container-grafico rel145-chart">
<canvas id="rel145Evolucao">
</canvas>
</div>
</section>
<section class="cartao">
<div class="section-title">
<div>
<span class="eyebrow">DISTRIBUIÇÃO</span>
<h2>Para onde o dinheiro foi?</h2>
</div>
<div class="periodos-grafico">
<button class="rel-tipo ativo" type="button" data-tipo="gastos">Despesas</button>
<button class="rel-tipo" type="button" data-tipo="entradas">Receitas</button>
</div>
</div>
<div class="container-grafico rel145-chart">
<canvas id="graficoCategorias">
</canvas>
</div>
<div id="relDetalhes" class="rel145-detalhes">
</div>
</section>
</div>
<div class="grade-dupla rel145-grid">
<section class="cartao">
<span class="eyebrow">LEITURA DO PERÍODO</span>
<h2>Principais sinais</h2>
<div class="rel145-insights">
<div>
<strong><?=limpar($origem['categoria']??'Sem dados')?></strong>
<span><?= $mei?'Principal origem de faturamento':'Principal origem de renda'?><?= $origem?' · '.formatarMoeda((float)$origem['total']):''?></span>
</div>
<div>
<strong><?=limpar($maior['categoria']??'Sem dados')?></strong>
<span>Maior categoria de despesa<?= $maior?' · '.formatarMoeda((float)$maior['total']):''?></span>
</div>
<div>
<strong><?= $res['resultado']>=0?'Período positivo':'Período negativo'?></strong>
<span><?= $res['resultado']>=0?'Entrou mais do que saiu.':'As despesas superaram as entradas.'?></span>
</div>
</div>
</section>
<section class="cartao">
<span class="eyebrow"><?=$mei?'NEGÓCIO':'OBJETIVOS E PATRIMÔNIO'?></span>
<h2><?=$mei?'Estrutura cadastrada':'Progresso além do mês'?></h2><?php if($mei):?><div class="rel145-secondary">
<div>
<span>Custo mensal da equipe</span>
<strong><?=formatarMoeda($res['extras']['custo_equipe']??0)?></strong>
</div>
<div>
<span>Custos cadastrados</span>
<strong><?=formatarMoeda($res['extras']['custos_cadastrados']??0)?></strong>
</div>
</div><?php else:?><div class="rel145-secondary">
<div>
<span>Investimentos acompanhados</span>
<strong><?=formatarMoeda($res['extras']['investimentos_atual']??0)?></strong>
</div>
<div>
<span>Metas em andamento</span>
<strong><?= (int)($res['extras']['metas_ativas']??0)?></strong>
</div>
<div>
<span>Valor acumulado nas metas</span>
<strong><?=formatarMoeda($res['extras']['metas_atual']??0)?></strong>
</div>
</div><?php endif;?></section>
</div>
<section class="surface-card rel145-comparacao">
<div class="section-title">
<div>
<span class="eyebrow">COMPARAÇÃO AUTOMÁTICA</span>
<h2>Período atual × período anterior equivalente</h2>
<p class="secao-ajuda"><?=limpar(cpRelNomePeriodo($cmp['inicio_anterior'],$cmp['fim_anterior']))?> → <?=limpar(cpRelNomePeriodo($inicio,$fim))?></p>
</div>
</div>
<div class="compare-kpis"><?php foreach(['receitas'=>'Receitas','despesas'=>'Despesas','resultado'=>'Resultado'] as $key=>$label):$a=$cmp['anterior'][$key];
$b=$cmp['atual'][$key];
$d=$b-$a;
$v=cpRelVariacao($b,$a);?><div>
<small><?=$label?></small>
<div>
<span><?=formatarMoeda($a)?></span>
<b>→</b>
<strong><?=formatarMoeda($b)?></strong>
</div>
<em class="<?=($key==='despesas'?($d<=0?'positivo':'negativo'):($d>=0?'positivo':'negativo'))?>"><?=$d>=0?'+':'-'?><?=formatarMoeda(abs($d))?><?=$v!==null?' · '.number_format(abs($v),1,',','.').'%':''?></em>
</div><?php endforeach;?></div>
</section>
<script src="../assets/js/dashboard.js">

</script>
<script src="../assets/js/relatorios.js">

</script>
<script>
const dadosEvolucao=<?=json_encode($evol,JSON_UNESCAPED_UNICODE)?>, dadosGastos=<?=json_encode($res['gastos'],JSON_UNESCAPED_UNICODE)?>, dadosEntradas=<?=json_encode($res['entradas'],JSON_UNESCAPED_UNICODE)?>;

inicializarGraficoEvolucao(dadosEvolucao,'rel145Evolucao');
 inicializarGraficoCategorias(dadosGastos);
 inicializarRelatorioAlternancia(dadosGastos,dadosEntradas);

const preset=document.getElementById('relPreset');
 const toggle=()=>document.querySelectorAll('.rel145-custom').forEach(x=>x.style.display=preset.value==='personalizado'?'block':'none');
 preset.addEventListener('change',toggle);
 toggle();
</script>
<?php require_once __DIR__.'/../includes/footer.php';?>
