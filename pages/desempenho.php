<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../database/conexao.php';
require_once __DIR__.'/../includes/inteligencia_financeira.php';
require_once __DIR__.'/../includes/negocio_financeiro.php';
exigirLogin();
if(usuarioLogadoTipo()!=='mei'){
header('Location: dashboard.php');
exit;
}

$tituloPagina='Desempenho';
$pdo=conectar();
$uid=(int)usuarioLogadoId();
cpSincronizarCustosRecorrentesMes($pdo,$uid);

$periodo=(int)($_GET['periodo']??1);
if(!in_array($periodo,[1,3,6,12],true))$periodo=1;
$inicio=date('Y-m-01',strtotime('-'.($periodo-1).' months'));
$fim=date('Y-m-t');

$d=cpDesempenhoNegocio($pdo,$uid,$inicio,$fim);
$prev=cpPrevisaoCaixaNegocio($pdo,$uid,30);

$itens=$d['itens'];
$porQtd=$itens;
$porLucro=$itens;
$porMargem=$itens;

usort($porQtd,fn($a,$b)=>(float)$b['quantidade']<=>(float)$a['quantidade']);
usort($porLucro,fn($a,$b)=>(float)$b['lucro']<=>(float)$a['lucro']);
usort($porMargem,fn($a,$b)=>(float)$b['margem']<=>(float)$a['margem']);

$forn=$d['fornecedores'];
$custTotal=$d['custos']['fixos']+$d['custos']['variaveis'];

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="page-head">
<div>
<span class="eyebrow">DESEMPENHO EMPRESARIAL</span>
<h1>Entenda o que move seu negócio</h1>
<p>Vendas, margem, produtos, estrutura de custos, fornecedores e projeção em um painel separado dos relatórios contábeis.</p>
</div>
<div class="periodos-grafico"><?php foreach([1=>'1M',3=>'3M',6=>'6M',12=>'12M'] as $v=>$l):?><a class="periodo <?=$periodo===$v?'ativo':''?>" href="?periodo=<?=$v?>"><?=$l?></a><?php endforeach;?></div>
</div>
<section class="business-kpis">
<article>
<span>Faturamento de vendas</span>
<strong><?=formatarMoeda($d['resumo']['faturamento'])?></strong>
<small><?=$d['resumo']['vendas']?> venda(s)</small>
</article>
<article>
<span>Ticket médio</span>
  <?php if ((int) $d['resumo']['vendas'] > 0):?>
   <strong><?=formatarMoeda($d['resumo']['ticket'])?></strong>
<small>Faturamento ÷ vendas</small>
  <?php else:?>
   <span class="cp-insufficient">Dados insuficientes</span>
<small class="cp-insufficient-note">Registre vendas para calcular o ticket médio.</small>
  <?php endif;?>
 </article>
<article>
<span>Lucro bruto</span>
  <?php if ((int) $d['resumo']['vendas'] > 0):?>
   <strong><?=formatarMoeda($d['resumo']['lucro'])?></strong>
<small>Antes dos custos operacionais</small>
  <?php else:?>
   <span class="cp-insufficient">Dados insuficientes</span>
<small class="cp-insufficient-note">Cadastre vendas e custos associados.</small>
  <?php endif;?>
 </article>
<article>
<span>Margem bruta</span>
  <?php if ((int) $d['resumo']['vendas'] > 0 && (float) $d['resumo']['faturamento'] > 0):?>
   <strong><?=number_format($d['resumo']['margem'],1,',','.')?>%</strong>
<small>Nas vendas cadastradas</small>
  <?php else:?>
   <span class="cp-insufficient">Dados insuficientes</span>
<small class="cp-insufficient-note">A margem aparece quando existem vendas e custos válidos.</small>
  <?php endif;?>
 </article>
</section>
<div class="dashboard-grid-main">
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">PRODUTOS & SERVIÇOS</span>
<h2>Ranking de desempenho</h2>
</div>
<div class="periodos-grafico">
<button class="rel-tipo ativo" data-rank="faturamento">Faturamento</button>
<button class="rel-tipo" data-rank="quantidade">Vendidos</button>
<button class="rel-tipo" data-rank="lucro">Lucro</button>
<button class="rel-tipo" data-rank="margem">Margem</button>
</div>
</div>
<div class="performance-table" id="rankingDesempenho">
</div>
</section>
<section class="surface-card forecast-card">
<div class="section-title">
<div>
<span class="eyebrow">PREVISÃO DE CAIXA</span>
<h2>Próximos 30 dias</h2>
</div>
<button class="text-button" data-copiloto-pergunta="Analise minha previsão de caixa dos próximos 30 dias. Diferencie o que é realizado do que é estimado e diga o principal risco.">✦ Analisar</button>
</div>
<div class="forecast-flow">
<div>
<span>Saldo atual</span>
<strong><?=formatarMoeda($prev['saldo_atual'])?></strong>
</div>
<i>+</i>
<div>
<span>Receita prevista</span>
<strong><?=formatarMoeda($prev['receita_prevista'])?></strong>
</div>
<i>−</i>
<div>
<span>Compromissos</span>
<strong><?=formatarMoeda($prev['compromissos_previstos'])?></strong>
</div>
</div>
<div class="forecast-result">
<span>Caixa projetado</span>
<strong class="<?=$prev['caixa_projetado']>=0?'positivo':'negativo'?>"><?=formatarMoeda($prev['caixa_projetado'])?></strong>
</div>
<p class="secao-ajuda">Estimativa baseada em <?=$prev['metodo']?>. Não é garantia de faturamento futuro.</p>
</section>
</div>
<div class="dashboard-grid-secondary">
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">ESTRUTURA</span>
<h2>Custos fixos x variáveis</h2>
</div>
</div>
<div class="cost-split">
<div>
<strong><?=formatarMoeda($d['custos']['fixos'])?></strong>
<span>Fixos</span>
</div>
<div>
<strong><?=formatarMoeda($d['custos']['variaveis'])?></strong>
<span>Variáveis</span>
</div>
</div>
<div class="stacked-bar">
<span style="width:<?=$custTotal>0?$d['custos']['fixos']/$custTotal*100:0?>%">
</span>
<i style="width:<?=$custTotal>0?$d['custos']['variaveis']/$custTotal*100:0?>%">
</i>
</div>
</section>
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">FORNECEDORES</span>
<h2>Participação nos custos</h2>
</div>
<button class="text-button" data-copiloto-pergunta="Analise meus fornecedores, identifique concentração de custos e diga se algum merece atenção.">✦ Analisar</button>
</div>
 <?php if(!$forn):?><p class="texto-vazio">Sem pagamentos de fornecedores no período.</p><?php else:?><div class="supplier-ranking"><?php $tf=array_sum(array_map(fn($x)=>(float)$x['total'],$forn));
foreach(array_slice($forn,0,6) as $x):?><div>
<div>
<strong><?=limpar($x['nome'])?></strong>
<small><?=$x['pagamentos']?> pagamento(s)</small>
</div>
<span><?=formatarMoeda((float)$x['total'])?> · <?=number_format($tf>0?(float)$x['total']/$tf*100:0,0)?>%</span>
</div><?php endforeach;?></div><?php endif;?>
</section>
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">COPILOTO</span>
<h2>Transforme análise em ação</h2>
</div>
</div>
<p class="secao-ajuda">Use o Copiloto para explicar margem, custos, precificação ou previsão e depois transformar a análise em um plano de ação.</p>
<button class="btn btn-secundario btn-bloco" data-copiloto-pergunta="Analise meu desempenho empresarial deste período e proponha 3 ações práticas priorizadas.">Criar análise prática</button>
</section>
</div>
<script>
const dadosRank={

 faturamento:<?=json_encode(array_slice($itens,0,12),JSON_UNESCAPED_UNICODE)?>,
 quantidade:<?=json_encode(array_slice($porQtd,0,12),JSON_UNESCAPED_UNICODE)?>,
 lucro:<?=json_encode(array_slice($porLucro,0,12),JSON_UNESCAPED_UNICODE)?>,
 margem:<?=json_encode(array_slice($porMargem,0,12),JSON_UNESCAPED_UNICODE)?>
}
;

const box=document.getElementById('rankingDesempenho'),money=v=>Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});

function renderRank(tipo){
const dados=dadosRank[tipo]||[];
box.innerHTML=dados.length?dados.map((x,i)=>`<div class="performance-row">
<b>${i+1}</b>
<div>
<strong>${String(x.nome_item).replace(/[&<>]/g,'')}</strong>
<small>${x.quantidade} vendido(s)</small>
</div>
<span>${money(x.faturamento)}</span>
<span>${money(x.lucro)}</span>
<span>${Number(x.margem||0).toFixed(1).replace('.',',')}%</span>
</div>`).join(''):'<p class="texto-vazio">Sem vendas suficientes para o ranking.</p>'}

document.querySelectorAll('[data-rank]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-rank]').forEach(x=>x.classList.remove('ativo'));b.classList.add('ativo');renderRank(b.dataset.rank)}));
renderRank('faturamento');
</script>
<?php require_once __DIR__.'/../includes/footer.php';?>
