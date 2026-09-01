<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../database/conexao.php';
require_once __DIR__.'/../includes/dashboard_helpers.php';
require_once __DIR__.'/../includes/negocio_financeiro.php';
require_once __DIR__.'/../includes/pf_financeiro.php';
require_once __DIR__.'/../includes/aprender_helper.php';
require_once __DIR__.'/../includes/inteligencia_financeira.php';
require_once __DIR__.'/../includes/cashpilot14_financeiro.php';
exigirLogin();

$tituloPagina='RadarPilot';
$pdo=conectar();
$uid=usuarioLogadoId();
$mei=usuarioLogadoTipo()==='mei';
if($mei)cpSincronizarCustosRecorrentesMes($pdo,$uid);
else cpSincronizarRecorrenciasPF($pdo,$uid);

$i=date('Y-m-01');
$f=date('Y-m-t');
$ia=date('Y-m-01',strtotime('first day of last month'));
$fa=date('Y-m-t',strtotime('last day of last month'));

$r=cpTotalPeriodo($pdo,'receitas','data_receita',$uid,$i,$f);
$d=cpTotalPeriodo($pdo,'despesas','data_despesa',$uid,$i,$f);
$ra=cpTotalPeriodo($pdo,'receitas','data_receita',$uid,$ia,$fa);
$da=cpTotalPeriodo($pdo,'despesas','data_despesa',$uid,$ia,$fa);
$resultado=$r-$d;
$resAnt=$ra-$da;
$cats=cpCategoriasDespesas($pdo,$uid,$i,$f,8);
$catsAnt=cpCategoriasDespesas($pdo,$uid,$ia,$fa,20);
$mapAnt=[];
foreach($catsAnt as $c)$mapAnt[$c['categoria']]=(float)$c['total'];
$alertas=[];
$evolucao=[];

$varD=$da>0?($d-$da)/$da*100:null;
$varR=$ra>0?($r-$ra)/$ra*100:null;
$varRes=$resAnt!=0?($resultado-$resAnt)/abs($resAnt)*100:null;

if($resultado<0)$alertas[]=['vermelho','Resultado no negativo','As despesas superam as receitas em '.formatarMoeda(abs($resultado)).'.','Seu fluxo do mês está negativo.','Analise por que meu resultado ficou negativo e quais gastos devo investigar primeiro.','transacoes.php'];

if($varD!==null&&$varD>=15)$alertas[]=['amarelo','Despesas aceleraram','Você gastou '.number_format($varD,1,',','.').'% a mais que no mês anterior.','Diferença de '.formatarMoeda(abs($d-$da)).' no mês.','Explique o aumento das minhas despesas e as possíveis causas.','relatorios.php'];

if($varD!==null&&$varD<=-10)$alertas[]=['verde','Despesas diminuíram','As despesas caíram '.number_format(abs($varD),1,',','.').'% em relação ao mês anterior.','Uma redução de '.formatarMoeda(abs($d-$da)).'.','O que contribuiu para a queda das minhas despesas e como manter isso?','relatorios.php'];

foreach($cats as $c){
$at=(float)$c['total'];
$ant=$mapAnt[$c['categoria']]??0;
if($ant>0){
$v=($at-$ant)/$ant*100;
if(abs($v)>=20)$evolucao[]=['nome'=>$c['categoria'],'valor'=>$v,'atual'=>$at,'anterior'=>$ant];
}
}

if($cats&&$d>0){
$top=$cats[0];
$pct=(float)$top['total']/$d*100;
if($pct>=30)$alertas[]=['amarelo','Concentração em '.$top['categoria'],$top['categoria'].' representa '.number_format($pct,1,',','.').'% das despesas do mês.',formatarMoeda((float)$top['total']).' concentrados nessa categoria.','Analise minha categoria '.$top['categoria'].' e diga o que mudou.','transacoes.php'];
}

if($mei){
$comp=cpCompromissosMensais($pdo,$uid);
$vd=cpResumoVendas($pdo,$uid,$i,$f);

$prevCaixa=cpPrevisaoCaixaNegocio($pdo,$uid,30);

$prevDetalhada14=cp14ProjecaoCaixaDetalhada($pdo,$uid,30);

if($prevDetalhada14['menor_saldo']<0)$alertas[]=['vermelho','Menor caixa projetado','A projeção indica um ponto abaixo de zero próximo de '.date('d/m',strtotime($prevDetalhada14['menor_data'])).'.','Menor saldo estimado: '.formatarMoeda($prevDetalhada14['menor_saldo']).'.','Analise meu ponto de menor caixa projetado e quais compromissos estão pressionando esse período.','projecao_caixa.php'];

if($prevCaixa['caixa_projetado']<0)$alertas[]=['vermelho','Caixa projetado negativo','Mantendo a média recente de entradas e os compromissos já registrados, o caixa projetado para 30 dias ficaria em '.formatarMoeda($prevCaixa['caixa_projetado']).'.','É uma estimativa, não uma certeza. Vale revisar compromissos e novas entradas.','Analise minha previsão de caixa e crie um plano de ação para evitar caixa negativo.','desempenho.php'];

if($r>0&&$comp['total']/$r>=.5)$alertas[]=['vermelho','Estrutura recorrente pesada','Equipe, fornecedores e custos recorrentes equivalem a '.number_format($comp['total']/$r*100,0).'% das receitas do mês.',formatarMoeda($comp['total']).' em compromissos estimados.','Analise meus compromissos recorrentes e indique quais devo investigar.','custos.php'];
if($vd['receita_vendas']>0&&$vd['margem_bruta']<25)$alertas[]=['amarelo','Margem bruta baixa','A margem bruta das vendas registradas está em '.number_format($vd['margem_bruta'],1,',','.').'%.','Custos dos itens estão consumindo boa parte do preço de venda.','Analise minha margem e os produtos ou serviços que merecem revisão.','produtos_servicos.php'];
try{
$s=$pdo->prepare('SELECT nome,estoque_atual,estoque_minimo FROM produtos_servicos WHERE usuario_id=:uid AND ativo=1 AND tipo="produto" AND controlar_estoque=1 AND estoque_atual<=estoque_minimo ORDER BY estoque_atual ASC LIMIT 5');
$s->execute(['uid'=>$uid]);
foreach($s->fetchAll() as $x)$alertas[]=[((int)$x['estoque_atual']===0?'vermelho':'amarelo'),'Estoque baixo: '.$x['nome'],'Há '.$x['estoque_atual'].' unidade(s) disponíveis; mínimo definido: '.$x['estoque_minimo'].'.','O produto pode limitar novas vendas se não houver reposição.','Analise o impacto do estoque baixo de '.$x['nome'].' no meu negócio.','produtos_servicos.php'];
}
catch(Throwable $e){
}
}

else{

$cashScoreRadar=cpCashScore($pdo,$uid);
$reservaRadar=$cashScoreRadar['reserva'];

if($cashScoreRadar['score']<50)$alertas[]=['vermelho','CashScore em nível crítico','Seu CashScore atual é '.$cashScoreRadar['score'].'/100.','Resultado, orçamento, recorrências e reserva estão pressionando sua saúde financeira.','Explique meu CashScore e crie um plano de ação para melhorar os fatores mais fracos.','saude_financeira.php'];

elseif($cashScoreRadar['score']<70)$alertas[]=['amarelo','CashScore pode melhorar','Seu CashScore atual é '.$cashScoreRadar['score'].'/100.','Há espaço para melhorar hábitos e proteção financeira.','Explique quais fatores estão reduzindo meu CashScore e o que devo priorizar.','saude_financeira.php'];

$investRadar14=cp14ResumoInvestimentos($pdo,$uid);

if($investRadar14['valor_atual']>0&&$reservaRadar['cobertura_meses']<1)$alertas[]=['amarelo','Investimentos sem reserva suficiente','Você já acompanha investimentos no CashPilot, mas sua reserva registrada ainda cobre menos de 1 mês de gastos essenciais.','Investir não substitui proteção para imprevistos.','Relacione meus investimentos com minha reserva e explique o que devo priorizar sem indicar ativos.','investimentos.php'];

foreach($investRadar14['classes'] as $classeRadar14){
if(($classeRadar14['percentual']??0)>=70){
$alertas[]=['amarelo','Carteira muito concentrada',cp14NomeClasseInvestimento($classeRadar14['classe']).' representa '.number_format($classeRadar14['percentual'],0).'% da carteira cadastrada.','Concentração não significa necessariamente erro, mas merece ser compreendida no contexto dos seus objetivos.','Explique a concentração da minha carteira sem recomendar compra ou venda de ativos.','investimentos.php'];
break;
}
}

if($reservaRadar['gasto_essencial_medio']>0&&$reservaRadar['cobertura_meses']<1)$alertas[]=['amarelo','Reserva de emergência baixa','Sua reserva registrada cobre menos de 1 mês dos gastos essenciais estimados.','Uma despesa inesperada pode pressionar seu orçamento.','Analise minha reserva de emergência e sugira um ritmo realista de construção.','saude_financeira.php'];

try{
$s=$pdo->prepare('SELECT c.nome,c.limite_mensal,COALESCE(SUM(d.valor),0) gasto FROM categorias c LEFT JOIN despesas d ON d.categoria_id=c.id AND d.usuario_id=:u1 AND d.data_despesa BETWEEN :i AND :f WHERE c.usuario_id=:u2 AND c.tipo="despesa" AND c.limite_mensal IS NOT NULL GROUP BY c.id HAVING gasto>0');
$s->execute(['u1'=>$uid,'i'=>$i,'f'=>$f,'u2'=>$uid]);
foreach($s->fetchAll() as $o){
if($o['limite_mensal']>0&&$o['gasto']/$o['limite_mensal']>=.85)$alertas[]=[($o['gasto']>$o['limite_mensal']?'vermelho':'amarelo'),'Orçamento de '.$o['nome'].' pressionado','Você utilizou '.number_format($o['gasto']/$o['limite_mensal']*100,0).'% do limite da categoria.',formatarMoeda((float)$o['gasto']).' de '.formatarMoeda((float)$o['limite_mensal']).'.','Analise meu orçamento de '.$o['nome'].' e como ajustar o restante do mês.','orcamentos.php'];
}
}
catch(Throwable $e){
}
$rec=cpResumoRecorrenciasPF($pdo,$uid);
if($r>0&&$rec['mensal']/$r>=.25)$alertas[]=['amarelo','Recorrências relevantes','Suas recorrências cadastradas equivalem a '.number_format($rec['mensal']/$r*100,0).'% das receitas deste mês.',formatarMoeda($rec['mensal']).' estimados por mês.','Analise minhas recorrências e quais podem ser revistas.','recorrencias.php'];
if($r>0&&$resultado/$r>=.2)$alertas[]=['verde','Boa folga financeira','Seu resultado positivo representa '.number_format($resultado/$r*100,0).'% das receitas do mês.',formatarMoeda($resultado).' de resultado positivo.','Como posso usar essa folga sem prejudicar minhas metas?','planejamento.php'];
}

$textoRadar=implode(' ',array_map(fn($a)=>($a[1]??'').' '.($a[2]??''),$alertas));

$aulaRadar=cpVideoRelacionado($pdo,$mei?'mei':'pessoa_fisica',$textoRadar);

$filtro=$_GET['nivel']??'todos';
if(in_array($filtro,['vermelho','amarelo','verde','azul'],true))$alertas=array_values(array_filter($alertas,fn($x)=>$x[0]===$filtro));

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="page-head">
<div>
<span class="eyebrow">ANÁLISE AUTOMÁTICA</span>
<h1>RadarPilot</h1>
<p>O RadarPilot mostra o que mudou, o impacto e para onde ir.</p>
</div>
<div class="radar-filtros">
<a class="periodo <?=$filtro==='todos'?'ativo':''?>" href="radar.php">Todos</a>
<a class="periodo <?=$filtro==='vermelho'?'ativo':''?>" href="?nivel=vermelho">Problemas</a>
<a class="periodo <?=$filtro==='amarelo'?'ativo':''?>" href="?nivel=amarelo">Atenção</a>
<a class="periodo <?=$filtro==='verde'?'ativo':''?>" href="?nivel=verde">Positivos</a>
</div>
</div>
<div class="radar-resumo-v9">
<div class="cartao">
<small>Resultado atual</small>
<strong class="<?=$resultado>=0?'positivo':'negativo'?>"><?=formatarMoeda($resultado)?></strong>
</div>
<div class="cartao">
<small>Variação das despesas</small>
<strong><?=$varD===null?'Sem base':(($varD>=0?'+':'').number_format($varD,1,',','.').'%')?></strong>
</div>
<div class="cartao">
<small>Variação das receitas</small>
<strong><?=$varR===null?'Sem base':(($varR>=0?'+':'').number_format($varR,1,',','.').'%')?></strong>
</div>
</div>
<div class="grade-dupla" style="margin-top:20px">
<div class="radar-pagina"><?php if(!$alertas):?><div class="cartao estado-vazio">
<span>◉</span>
<h3>Nenhum alerta neste filtro</h3>
<p>O RadarPilot ganha precisão conforme os dados são registrados e classificados.</p>
</div><?php else:foreach($alertas as $a):?><article class="cartao radar-alerta-grande <?=$a[0]?>">
<span class="radar-semaforo">
</span>
<div>
<small><?=strtoupper($a[0])?></small>
<h3><?=limpar($a[1])?></h3>
<p><?=limpar($a[2])?></p>
<div class="radar-impacto">
<strong>Impacto</strong>
<span><?=limpar($a[3])?></span>
</div>
<div class="radar-alerta-acoes">
<button type="button" class="btn btn-secundario" data-copiloto-pergunta="<?=limpar($a[4])?>">✦ Entender com o Copiloto</button>
<button type="button" class="text-button" data-copiloto-pergunta="<?=limpar($a[4])?> Depois crie um plano de ação com 3 passos priorizados.">Criar plano</button>
<a href="<?=limpar($a[5])?>" class="link-limpar">Investigar dados →</a>
</div>
</div>
</article><?php endforeach;
endif;?></div>
<div class="cartao">
<span class="eyebrow">EVOLUÇÃO RECENTE</span>
<h3>Melhoras e pioras</h3>
<p class="secao-ajuda">Comparação entre as categorias deste mês e do anterior.</p><?php if(!$evolucao):?><p class="texto-vazio">Ainda não há mudanças relevantes suficientes.</p><?php else:usort($evolucao,fn($a,$b)=>abs($b['valor'])<=>abs($a['valor']));
foreach(array_slice($evolucao,0,8) as $e):?><div class="evolucao-radar">
<div>
<strong><?=limpar($e['nome'])?></strong>
<small><?=formatarMoeda($e['anterior'])?> → <?=formatarMoeda($e['atual'])?></small>
</div>
<span class="<?=$e['valor']>0?'negativo':'positivo'?>"><?=$e['valor']>0?'↑':'↓'?> <?=number_format(abs($e['valor']),1,',','.')?>%</span>
</div><?php endforeach;
endif;?></div>
</div>

<?php if($aulaRadar):?>
<section class="surface-card radar-learning">
<div class="radar-learning-thumb">
<img src="https://img.youtube.com/vi/<?=limpar($aulaRadar['youtube_video_id'])?>/mqdefault.jpg" alt="">
</div>
<div>
<span class="eyebrow">APRENDA SOBRE ISSO</span>
<h2><?=limpar($aulaRadar['titulo'])?></h2>
<p><?=limpar($aulaRadar['descricao']??'Este conteúdo foi relacionado aos alertas atuais do RadarPilot.')?></p>
<a href="aula.php?id=<?=$aulaRadar['id']?>" class="btn btn-secundario">Assistir aula</a>
</div>
</section>
<?php endif;?>
<?php require_once __DIR__.'/../includes/footer.php';?>
