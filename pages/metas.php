<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../database/conexao.php';
exigirLogin();
if(usuarioLogadoTipo()==='mei'){
header('Location: dashboard.php');
exit;
}

$tituloPagina='Metas';
$pdo=conectar();
$uid=usuarioLogadoId();
$s=$pdo->prepare('SELECT * FROM metas WHERE usuario_id=:uid ORDER BY concluida,prazo IS NULL,prazo ASC,data_criacao DESC');
$s->execute(['uid'=>$uid]);
$metas=$s->fetchAll();
$selecionadaId=(int)($_GET['meta']??($metas[0]['id']??0));
$meta=null;
foreach($metas as $m)if((int)$m['id']===$selecionadaId){
$meta=$m;
break;
}
$movs=[];
$mediaAporte=0;
if($meta){
try{
$s=$pdo->prepare('SELECT * FROM meta_movimentacoes WHERE meta_id=:mid AND usuario_id=:uid ORDER BY data_movimentacao DESC,id DESC');
$s->execute(['mid'=>$meta['id'],'uid'=>$uid]);
$movs=$s->fetchAll();
$aportes=array_filter($movs,fn($x)=>$x['tipo']==='aporte');
$mediaAporte=$aportes?array_sum(array_map(fn($x)=>(float)$x['valor'],$aportes))/max(1,count($aportes)):0;
}
catch(Throwable $e){
}
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="topo-pagina">
<div>
<span class="eyebrow">PLANEJAMENTO PESSOAL</span>
<h1>Metas</h1>
<p>Cada objetivo tem seu próprio plano, histórico e apoio do Copiloto.</p>
</div>
<button class="btn btn-primario" type="button" data-drawer-open="drawerNovaMeta">+ Nova meta</button>
</div>
<aside class="cp-drawer" id="drawerNovaMeta">
<div class="drawer-head">
<div>
<span class="eyebrow">NOVO OBJETIVO</span>
<h2>Criar meta</h2>
<p>Defina o valor, o que já foi guardado e um prazo quando fizer sentido.</p>
</div>
<button type="button" class="drawer-close" data-drawer-close aria-label="Fechar">×</button>
</div>
<div class="drawer-body">
<form action="../actions/metas.php" method="POST" autocomplete="off">
            <?=csrfCampo()?>
            <input type="hidden" name="acao" value="criar">
<div class="form-grupo">
<label>Nome da meta</label>
<input name="titulo" required placeholder="Ex.: Viagem">
</div>
<div class="form-grupo">
<label>Valor objetivo</label>
<input type="number" step="0.01" min="0.01" name="valor_meta" required>
</div>
<div class="form-grupo">
<label>Valor já guardado</label>
<input type="number" step="0.01" min="0" name="valor_atual" value="0">
</div>
<div class="form-grupo">
<label>Prazo desejado</label>
<input type="date" name="prazo">
</div>
<button class="btn btn-primario btn-bloco">Criar meta</button>
</form>
</div>
</aside>
<div class="metas-app">
<aside class="metas-lista cartao">
<div class="metas-lista-titulo">
<strong>Seus objetivos</strong>
<span><?=count($metas)?></span>
</div><?php if(!$metas):?><p class="texto-vazio">Crie sua primeira meta.</p><?php else:foreach($metas as $m):$pct=$m['valor_meta']>0?min(100,$m['valor_atual']/$m['valor_meta']*100):0;?><a class="meta-contato <?=($meta&&$m['id']==$meta['id'])?'ativo':''?>" href="?meta=<?=$m['id']?>">
<div class="meta-contato-icone">◎</div>
<div class="meta-contato-texto">
<strong><?=limpar($m['titulo'])?></strong>
<small><?=formatarMoeda((float)$m['valor_atual'])?> / <?=formatarMoeda((float)$m['valor_meta'])?></small>
<div class="meta-contato-barra">
<i style="width:<?=$pct?>%">
</i>
</div>
</div>
<b><?=number_format($pct,0)?>%</b>
</a><?php endforeach;
endif;?></aside>
<section class="meta-detalhe cartao"><?php if(!$meta):?><div class="meta-empty">
<span>◎</span>
<h3>Selecione uma meta</h3>
<p>Abra um objetivo para acompanhar o plano em detalhes.</p>
</div><?php else:$pct=$meta['valor_meta']>0?min(100,$meta['valor_atual']/$meta['valor_meta']*100):0;
$falta=max(0,$meta['valor_meta']-$meta['valor_atual']);
$meses=$mediaAporte>0?(int)ceil($falta/$mediaAporte):null;
$necessarioPrazo=null;
if($meta['prazo']&&strtotime($meta['prazo'])>time()){
$diff=(new DateTime())->diff(new DateTime($meta['prazo']));
$mp=max(1,$diff->y*12+$diff->m+($diff->d>0?1:0));
$necessarioPrazo=$falta/$mp;
}?>
<div class="meta-detalhe-topo">
<div>
<span class="eyebrow">META ATIVA</span>
<h2><?=limpar($meta['titulo'])?></h2>
<p><?=formatarMoeda((float)$meta['valor_atual'])?> de <?=formatarMoeda((float)$meta['valor_meta'])?></p>
</div>
<div class="meta-circulo" style="--p:<?=$pct?>">
<strong><?=number_format($pct,0)?>%</strong>
</div>
</div>
<div class="meta-kpis">
<div>
<span>Falta</span>
<strong><?=formatarMoeda($falta)?></strong>
</div>
<div>
<span>Prazo</span>
<strong><?=$meta['prazo']?date('d/m/Y',strtotime($meta['prazo'])):'Sem prazo'?></strong>
</div>
<div>
<span>Ritmo médio</span>
<strong><?=$mediaAporte>0?formatarMoeda($mediaAporte).'/aporte':'Ainda sem histórico'?></strong>
</div>
<div>
<span>Previsão</span>
<strong><?=$meses!==null?$meses.' aporte(s) no ritmo médio':'Aguardando aportes'?></strong>
</div>
</div>
<div class="meta-copiloto">
<div>
<span class="copiloto-mini-avatar">✦</span>
<div>
<strong>Copiloto da meta</strong>
<p><?php if($necessarioPrazo!==null):?>Para atingir o prazo, você precisa direcionar aproximadamente <b><?=formatarMoeda($necessarioPrazo)?></b> por mês.<?php elseif($meses!==null):?>Mantendo o ritmo médio dos aportes, ainda seriam necessários aproximadamente <b><?=$meses?></b> aportes semelhantes.<?php else:?>Registre alguns aportes para eu conseguir comparar seu ritmo com o objetivo.<?php endif;?></p>
</div>
</div>
<button type="button" class="btn btn-copiloto-meta" data-copiloto-pergunta="Analise especificamente minha meta <?=limpar($meta['titulo'])?>. Objetivo <?=formatarMoeda((float)$meta['valor_meta'])?>, acumulado <?=formatarMoeda((float)$meta['valor_atual'])?>, faltam <?=formatarMoeda($falta)?><?= $meta['prazo'] ? ', prazo '.date('d/m/Y',strtotime($meta['prazo'])) : ''?>. Use também meus gastos do CashPilot e sugira um plano realista.">Conversar sobre esta meta</button>
</div>
<div class="meta-mov-area">
<div>
<h3>Movimentar meta</h3>
<p class="secao-ajuda">Registre aportes e retiradas em vez de editar o saldo manualmente.</p>
</div>
<form action="../actions/metas.php" method="POST" autocomplete="off" class="meta-mov-form"><?=csrfCampo()?><input type="hidden" name="id" value="<?=$meta['id']?>">
<select name="acao">
<option value="aporte">Adicionar dinheiro</option>
<option value="retirada">Retirar dinheiro</option>
</select>
<input type="number" step="0.01" min="0.01" name="valor" placeholder="R$ 0,00" required>
<input name="observacao" placeholder="Observação opcional">
<input type="date" name="data_movimentacao" value="<?=date('Y-m-d')?>">
<button class="btn btn-primario">Registrar</button>
</form>
</div>
<div class="meta-historico">
<h3>Histórico</h3><?php if(!$movs):?><p class="texto-vazio">Nenhuma movimentação registrada.</p><?php else:foreach(array_slice($movs,0,12) as $x):?><div class="meta-historico-item">
<span class="<?=$x['tipo']==='aporte'?'positivo':'negativo'?>"><?=$x['tipo']==='aporte'?'+':'-'?> <?=formatarMoeda((float)$x['valor'])?></span>
<div>
<strong><?=date('d/m/Y',strtotime($x['data_movimentacao']))?></strong>
<small><?=limpar($x['observacao']?:ucfirst($x['tipo']))?></small>
</div>
</div><?php endforeach;
endif;?></div>
<form action="../actions/metas.php" method="POST" data-confirm="Excluir esta meta?" class="meta-danger"><?=csrfCampo()?><input type="hidden" name="acao" value="excluir">
<input type="hidden" name="id" value="<?=$meta['id']?>">
<button class="excluir">Excluir meta</button>
</form>
<?php endif;?></section>
</div>

<?php require_once __DIR__.'/../includes/footer.php';?>
