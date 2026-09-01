<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../database/conexao.php';
require_once __DIR__.'/../includes/inteligencia_financeira.php';
exigirLogin();
if(usuarioLogadoTipo()!=='pessoa_fisica'){
header('Location: dashboard.php');
exit;
}

$tituloPagina='Saúde Financeira';
$pdo=conectar();
$uid=(int)usuarioLogadoId();
$score=cpCashScore($pdo,$uid);
$reserva=$score['reserva'];
$desafios=cpDesafiosEconomia($pdo,$uid);
$msg=$_SESSION['mensagem_saude']??null;
unset($_SESSION['mensagem_saude']);

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="page-head">
<div>
<span class="eyebrow">SAÚDE FINANCEIRA</span>
<h1>Seu panorama financeiro</h1>
<p>Score, reserva e desafios em uma visão única. Os cálculos usam dados registrados no CashPilot.</p>
</div>
<button class="btn btn-secundario" data-copiloto-pergunta="Explique meu CashScore atual, minha reserva de emergência e diga qual ação teria maior impacto agora.">✦ Analisar com Copiloto</button>
</div>
<?php if($msg):?><div class="alerta-mensagem sucesso"><?=limpar($msg)?></div><?php endif;?>

<div class="health-grid">
<section class="surface-card score-card">
<div class="score-ring" style="--score:<?=$score['score']?>">
<div>
<strong><?=$score['score']?></strong>
<span>/100</span>
</div>
</div>
<div class="score-copy">
<span class="eyebrow">CASHSCORE</span>
<h2><?=limpar($score['nivel'])?></h2>
<p>Uma leitura objetiva de resultado, orçamentos, recorrências, reserva e metas.</p>
</div>
</section>
<section class="surface-card reserve-card">
<div class="section-title">
<div>
<span class="eyebrow">RESERVA DE EMERGÊNCIA</span>
<h2><?=number_format($reserva['cobertura_meses'],1,',','.')?> mês(es) de cobertura</h2>
</div>
<button class="text-button" data-drawer-open="drawerReserva">Atualizar</button>
</div>
<div class="reserve-values">
<strong><?=formatarMoeda($reserva['valor_atual'])?></strong>
<span>de <?=formatarMoeda($reserva['valor_objetivo'])?> como referência</span>
</div>
<div class="learning-progress">
<span style="width:<?=min(100,$reserva['percentual'])?>%">
</span>
</div>
<small>Gasto essencial médio estimado: <?=formatarMoeda($reserva['gasto_essencial_medio'])?>/mês · objetivo de <?=$reserva['meses_objetivo']?> meses.</small>
</section>
</div>
<section class="surface-card section-block">
<div class="section-title">
<div>
<span class="eyebrow">POR QUE ESSA NOTA?</span>
<h2>Fatores do CashScore</h2>
</div>
<a href="orcamentos.php" class="link-limpar">Ajustar orçamentos →</a>
</div>
<div class="score-factors"><?php foreach($score['fatores'] as $f):?><div class="<?=limpar($f[0])?>">
<span><?=($f[3]??0)>0?'+':''?><?=limpar((string)$f[3])?></span>
<div>
<strong><?=limpar($f[1])?></strong>
<p><?=limpar($f[2])?></p>
</div>
</div><?php endforeach;?></div>
</section>
<section class="surface-card section-block">
<div class="section-title">
<div>
<span class="eyebrow">DESAFIOS DE ECONOMIA</span>
<h2>Transforme intenção em acompanhamento</h2>
</div>
<button class="btn btn-secundario" data-drawer-open="drawerDesafio">＋ Novo desafio</button>
</div>
<?php $ativos=array_values(array_filter($desafios,fn($x)=>$x['status']!=='cancelado'));
if(!$ativos):?><div class="mini-empty">
<p>Nenhum desafio criado ainda.</p>
</div><?php else:?><div class="challenge-grid"><?php foreach($ativos as $d):$pct=(float)$d['valor_objetivo']>0?min(100,(float)$d['valor_economizado']/(float)$d['valor_objetivo']*100):0;
$rest=max(0,(strtotime($d['data_fim'])-time())/86400);?><article>
<div>
<span class="soft-badge"><?=limpar($d['status']==='concluido'?'Concluído':'Em andamento')?></span>
<h3><?=limpar($d['titulo'])?></h3>
</div>
<strong><?=formatarMoeda((float)$d['valor_economizado'])?> <small>/ <?=formatarMoeda((float)$d['valor_objetivo'])?></small>
</strong>
<div class="learning-progress">
<span style="width:<?=$pct?>%">
</span>
</div>
<p><?=number_format($pct,0)?>% · <?=max(0,(int)ceil($rest))?> dia(s) restantes</p><?php if($d['status']==='ativo'):?><form action="../actions/saude_financeira.php" method="POST" class="challenge-update"><?=csrfCampo()?><input type="hidden" name="acao" value="atualizar_desafio">
<input type="hidden" name="id" value="<?=$d['id']?>">
<input type="number" step="0.01" min="0" name="valor_economizado" value="<?=limpar((string)$d['valor_economizado'])?>">
<button class="btn btn-secundario">Atualizar</button>
</form><?php endif;?></article><?php endforeach;?></div><?php endif;?>
</section>
<aside class="cp-drawer" id="drawerReserva">
<div class="drawer-head">
<div>
<span class="eyebrow">RESERVA</span>
<h2>Atualizar reserva</h2>
</div>
<button class="drawer-close" data-drawer-close>×</button>
</div>
<div class="drawer-body">
<form action="../actions/saude_financeira.php" method="POST"><?=csrfCampo()?><input type="hidden" name="acao" value="salvar_reserva">
<div class="form-grupo">
<label>Valor reservado atualmente</label>
<input type="number" step="0.01" min="0" name="valor_atual" value="<?=limpar((string)$reserva['valor_atual'])?>">
</div>
<div class="form-grupo">
<label>Meses de cobertura desejados</label>
<input type="number" min="1" max="24" name="meses_objetivo" value="<?=$reserva['meses_objetivo']?>">
</div>
<button class="btn btn-primario btn-bloco">Salvar reserva</button>
</form>
</div>
</aside>
<aside class="cp-drawer" id="drawerDesafio">
<div class="drawer-head">
<div>
<span class="eyebrow">DESAFIO</span>
<h2>Novo desafio de economia</h2>
</div>
<button class="drawer-close" data-drawer-close>×</button>
</div>
<div class="drawer-body">
<form action="../actions/saude_financeira.php" method="POST"><?=csrfCampo()?><input type="hidden" name="acao" value="criar_desafio">
<div class="form-grupo">
<label>Nome</label>
<input name="titulo" required placeholder="Ex.: Economizar no delivery">
</div>
<div class="form-grupo">
<label>Valor a economizar</label>
<input type="number" step="0.01" min="0.01" name="valor_objetivo" required>
</div>
<div class="form-grupo">
<label>Duração</label>
<select name="dias">
<option value="7">7 dias</option>
<option value="30" selected>30 dias</option>
<option value="60">60 dias</option>
<option value="90">90 dias</option>
</select>
</div>
<button class="btn btn-primario btn-bloco">Criar desafio</button>
</form>
</div>
</aside>
<?php require_once __DIR__.'/../includes/footer.php';?>
