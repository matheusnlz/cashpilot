<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../database/conexao.php';
require_once __DIR__.'/../includes/negocio_financeiro.php';
exigirLogin();

$tituloPagina='Despesas';
$pdo=conectar();
$uid=(int)usuarioLogadoId();
if(usuarioLogadoTipo()==='mei')cpSincronizarCustosRecorrentesMes($pdo,$uid);

$cat=$pdo->prepare('SELECT id,nome FROM categorias WHERE usuario_id=:uid AND tipo="despesa" ORDER BY nome');
$cat->execute(['uid'=>$uid]);
$categorias=$cat->fetchAll(PDO::FETCH_ASSOC);

$ct=$pdo->prepare('SELECT id,nome FROM contas WHERE usuario_id=:uid ORDER BY padrao DESC,nome');
$ct->execute(['uid'=>$uid]);
$contas=$ct->fetchAll(PDO::FETCH_ASSOC);

$q=trim($_GET['descricao']??'');
$fcat=(int)($_GET['categoria_filtro']??0);
$fconta=(int)($_GET['conta_filtro']??0);
$ini=$_GET['data_inicio']??'';
$fim=$_GET['data_fim']??'';
$pagina=max(1,(int)($_GET['pagina']??1));
$porPagina=20;

$where=' WHERE d.usuario_id=:uid';
$params=['uid'=>$uid];
if($q!==''){
$where.=' AND d.descricao LIKE :q';
$params['q']='%'.$q.'%';
}
if($fcat>0){
$where.=' AND d.categoria_id=:cat';
$params['cat']=$fcat;
}
if($fconta>0){
$where.=' AND d.conta_id=:conta';
$params['conta']=$fconta;
}
if($ini!==''){
$where.=' AND d.data_despesa>=:ini';
$params['ini']=$ini;
}
if($fim!==''){
$where.=' AND d.data_despesa<=:fim';
$params['fim']=$fim;
}

$c=$pdo->prepare('SELECT COUNT(*) FROM despesas d'.$where);
$c->execute($params);
$total=(int)$c->fetchColumn();
$paginas=max(1,(int)ceil($total/$porPagina));
$pagina=min($pagina,$paginas);
$offset=($pagina-1)*$porPagina;
$t=$pdo->prepare('SELECT COALESCE(SUM(d.valor),0) FROM despesas d'.$where);
$t->execute($params);
$totalPeriodo=(float)$t->fetchColumn();

$sql='SELECT d.id,d.valor,d.descricao,d.data_despesa,d.origem_tipo,c.nome categoria_nome,co.nome conta_nome FROM despesas d LEFT JOIN categorias c ON c.id=d.categoria_id LEFT JOIN contas co ON co.id=d.conta_id'.$where.' ORDER BY d.data_despesa DESC,d.id DESC LIMIT '.$porPagina.' OFFSET '.$offset;
$s=$pdo->prepare($sql);
$s->execute($params);
$despesas=$s->fetchAll(PDO::FETCH_ASSOC);

$edicao=null;
if(isset($_GET['editar'])){
$e=$pdo->prepare('SELECT * FROM despesas WHERE id=:id AND usuario_id=:uid AND origem_tipo="manual"');
$e->execute(['id'=>(int)$_GET['editar'],'uid'=>$uid]);
$edicao=$e->fetch(PDO::FETCH_ASSOC)?:null;
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="page-head">
<div>
<span class="eyebrow">SAÍDAS</span>
<h1>Despesas</h1>
<p>Entenda para onde seu dinheiro está indo sem poluir a tela.</p>
</div>
<button class="btn btn-primario" data-drawer-open="drawerDespesa">＋ Nova despesa</button>
</div>
<section class="summary-strip">
<div>
<small>Total no período</small>
<strong class="negativo"><?=formatarMoeda($totalPeriodo)?></strong>
</div>
<div>
<small>Movimentações</small>
<strong><?=$total?></strong>
</div>
<div>
<small>Página</small>
<strong><?=$pagina?> de <?=$paginas?></strong>
</div>
</section>
<form method="GET" class="filter-bar">
<div class="filter-search">
<span>⌕</span>
<input name="descricao" value="<?=limpar($q)?>" placeholder="Pesquisar despesa">
</div>
<select name="categoria_filtro">
<option value="0">Todas as categorias</option><?php foreach($categorias as $c):?><option value="<?=$c['id']?>" <?=$fcat===$c['id']?'selected':''?>><?=limpar($c['nome'])?></option><?php endforeach;?></select>
<select name="conta_filtro">
<option value="0">Todas as contas</option><?php foreach($contas as $c):?><option value="<?=$c['id']?>" <?=$fconta===$c['id']?'selected':''?>><?=limpar($c['nome'])?></option><?php endforeach;?></select>
<input type="date" name="data_inicio" value="<?=limpar($ini)?>">
<input type="date" name="data_fim" value="<?=limpar($fim)?>">
<button class="btn btn-secundario">Filtrar</button><?php if($q||$fcat||$fconta||$ini||$fim):?><a class="filter-clear" href="despesas.php">Limpar</a><?php endif;?></form>
<section class="data-panel">
<div class="data-panel-head">
<div>
<h2>Movimentações</h2>
<p>Despesas manuais e lançamentos gerados pelo sistema.</p>
</div>
</div>
<?php if(!$despesas):?><div class="estado-vazio clean-empty">
<span class="cp-empty-icon"><?=cpIcon('arrow-up-right')?></span>
<h3>Nenhuma despesa encontrada</h3>
<p>Cadastre sua primeira despesa ou ajuste os filtros.</p>
<button class="btn btn-secundario" data-drawer-open="drawerDespesa">Adicionar despesa</button>
</div>
<?php else:?><div class="responsive-list">
<div class="table-row table-head">
<span>Descrição</span>
<span>Categoria</span>
<span>Conta</span>
<span>Data</span>
<span>Valor</span>
<span>
</span>
</div><?php foreach($despesas as $d):?><div class="table-row">
<div class="cell-main">
<strong><?=limpar($d['descricao'])?></strong><?php if(($d['origem_tipo']??'manual')!=='manual'):?><small><?=limpar(ucfirst(str_replace('_',' ',$d['origem_tipo'])))?></small><?php endif;?></div>
<div>
<span class="soft-badge"><?=limpar($d['categoria_nome']??'Sem categoria')?></span>
</div>
<div><?=limpar($d['conta_nome']??'Sem conta')?></div>
<div><?=date('d/m/Y',strtotime($d['data_despesa']))?></div>
<div class="negativo money-cell"><?=formatarMoeda((float)$d['valor'])?></div>
<div class="row-actions"><?php if(($d['origem_tipo']??'manual')==='manual'):?><a href="?editar=<?=$d['id']?>">Editar</a>
<form action="../actions/despesas.php" method="POST" data-confirm="Excluir esta despesa?"><?=csrfCampo()?><input type="hidden" name="acao" value="excluir">
<input type="hidden" name="id" value="<?=$d['id']?>">
<button class="excluir">Excluir</button>
</form><?php else:?><span class="muted">Automática</span><?php endif;?></div>
</div><?php endforeach;?></div><?php endif;?></section>
<?php if($paginas>1):?>
<nav class="pagination-clean">
<?php if($pagina>1):?>
<a href="?<?=http_build_query(array_merge($_GET,['pagina'=>$pagina-1]))?>">←</a>
<?php endif;?>
<?php for($p=max(1,$pagina-2);$p<=min($paginas,$pagina+2);$p++):?>
<a class="<?=$p===$pagina?'ativo':''?>" href="?<?=http_build_query(array_merge($_GET,['pagina'=>$p]))?>"><?=$p?>
</a>
<?php endfor;?>
<?php if($pagina<$paginas):?>
<a href="?<?=http_build_query(array_merge($_GET,['pagina'=>$pagina+1]))?>">→</a>
<?php endif;?>
</nav>
<?php endif;?>
<aside class="cp-drawer <?=$edicao?'aberto':''?>" id="drawerDespesa">
<div class="drawer-head">
<div>
<span class="eyebrow"><?=$edicao?'EDIÇÃO':'NOVA MOVIMENTAÇÃO'?></span>
<h2><?=$edicao?'Editar despesa':'Nova despesa'?></h2>
</div>
<a href="despesas.php" class="drawer-close" data-drawer-close>×</a>
</div>
<div class="drawer-body">
<form action="../actions/despesas.php" method="POST"><?=csrfCampo()?><input type="hidden" name="acao" value="<?=$edicao?'editar':'criar'?>"><?php if($edicao):?><input type="hidden" name="id" value="<?=$edicao['id']?>"><?php endif;?><div class="form-grupo">
<label>Descrição</label>
<input name="descricao" required value="<?=limpar($edicao['descricao']??'')?>">
</div>
<div class="form-grupo">
<label>Valor</label>
<div class="money-input">
<span>R$</span>
<input type="number" step="0.01" min="0.01" name="valor" required value="<?=limpar((string)($edicao['valor']??''))?>">
</div>
</div>
<div class="form-grupo">
<div class="label-row">
<label>Categoria</label>
<button type="button" class="text-button" id="toggleNovaCatDespesa">＋ Nova categoria</button>
</div>
<select name="categoria_id"><?php foreach($categorias as $c):?><option value="<?=$c['id']?>" <?=($edicao['categoria_id']??0)==$c['id']?'selected':''?>><?=limpar($c['nome'])?></option><?php endforeach;?></select>
</div>
<div class="inline-new-category" id="novaCatDespesa" hidden>
<div class="inline-fields">
<input id="nomeCatDespesa" placeholder="Nome da categoria">
<input id="corCatDespesa" type="color" value="#B5654A">
<button type="button" class="btn btn-secundario" id="salvarCatDespesa">Criar</button>
</div>
<small id="statusCatDespesa">
</small>
</div>
<div class="form-grupo">
<label>Conta</label>
<select name="conta_id">
<option value="">Sem conta</option><?php foreach($contas as $c):?><option value="<?=$c['id']?>" <?=($edicao['conta_id']??0)==$c['id']?'selected':''?>><?=limpar($c['nome'])?></option><?php endforeach;?></select>
</div>
<div class="form-grupo">
<label>Data</label>
<input type="date" name="data_despesa" value="<?=limpar($edicao['data_despesa']??date('Y-m-d'))?>" required>
</div>
<button class="btn btn-primario btn-bloco"><?=$edicao?'Salvar alterações':'Adicionar despesa'?></button>
</form>
</div>
</aside>
<?php if($edicao):?><script>
document.addEventListener('DOMContentLoaded',()=>{document.getElementById('drawerOverlay')?.classList.add('ativo');document.body.classList.add('drawer-aberto')});
</script><?php endif;?>
<script>
const tcd=document.getElementById('toggleNovaCatDespesa'),ncd=document.getElementById('novaCatDespesa');
tcd?.addEventListener('click',()=>ncd.hidden=!ncd.hidden);
document.getElementById('salvarCatDespesa')?.addEventListener('click',async()=>{const nome=document.getElementById('nomeCatDespesa').value.trim(),status=document.getElementById('statusCatDespesa');
if(!nome)return;
const fd=new FormData();
fd.append('acao','criar_ajax');
fd.append('tipo','despesa');
fd.append('nome',nome);
fd.append('cor',document.getElementById('corCatDespesa').value);
fd.append('csrf_token',<?=json_encode(csrfToken())?>);
try{const r=await fetch('../actions/categorias.php',{method:'POST',body:fd});
const d=await r.json();
if(d.ok){
const s=document.querySelector('#drawerDespesa select[name="categoria_id"]');
s.add(new Option(d.nome,d.id,true,true));
status.textContent='Categoria criada.';
}else status.textContent=d.mensagem||'Não foi possível criar.';
}
catch(e){
status.textContent='Não foi possível criar.';
}});

</script>
<?php require_once __DIR__.'/../includes/footer.php';?>
