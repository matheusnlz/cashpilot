<?php
require_once __DIR__.'/../includes/auth.php';

require_once __DIR__.'/../database/conexao.php';

exigirLogin();


$tituloPagina='Receitas';
$pdo=conectar();
$uid=(int)usuarioLogadoId();

$cat=$pdo->prepare('SELECT id,nome FROM categorias WHERE usuario_id=:uid AND tipo="receita" ORDER BY nome');
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


$where=' WHERE r.usuario_id=:uid';
$params=['uid'=>$uid];

if($q!==''){
$where.=' AND r.descricao LIKE :q';
$params['q']='%'.$q.'%';
}

if($fcat>0){
$where.=' AND r.categoria_id=:cat';
$params['cat']=$fcat;
}

if($fconta>0){
$where.=' AND r.conta_id=:conta';
$params['conta']=$fconta;
}

if($ini!==''){
$where.=' AND r.data_receita>=:ini';
$params['ini']=$ini;
}

if($fim!==''){
$where.=' AND r.data_receita<=:fim';
$params['fim']=$fim;
}


$c=$pdo->prepare('SELECT COUNT(*) FROM receitas r'.$where);
$c->execute($params);
$total=(int)$c->fetchColumn();
$paginas=max(1,(int)ceil($total/$porPagina));
$pagina=min($pagina,$paginas);
$offset=($pagina-1)*$porPagina;

$t=$pdo->prepare('SELECT COALESCE(SUM(r.valor),0) FROM receitas r'.$where);
$t->execute($params);
$totalPeriodo=(float)$t->fetchColumn();

$sql='SELECT r.id,r.valor,r.descricao,r.data_receita,r.venda_id,c.nome categoria_nome,co.nome conta_nome FROM receitas r LEFT JOIN categorias c ON c.id=r.categoria_id LEFT JOIN contas co ON co.id=r.conta_id'.$where.' ORDER BY r.data_receita DESC,r.id DESC LIMIT '.$porPagina.' OFFSET '.$offset;

$s=$pdo->prepare($sql);
$s->execute($params);
$receitas=$s->fetchAll(PDO::FETCH_ASSOC);


$edicao=null;

if(isset($_GET['editar'])){
$e=$pdo->prepare('SELECT * FROM receitas WHERE id=:id AND usuario_id=:uid AND venda_id IS NULL');
$e->execute(['id'=>(int)$_GET['editar'],'uid'=>$uid]);
$edicao=$e->fetch(PDO::FETCH_ASSOC)?:null;
}


require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="page-head">
<div>
<span class="eyebrow">ENTRADAS</span>
<h1>Receitas</h1>
<p>Registre entradas e acompanhe de onde vem seu dinheiro.</p>
</div>
<button class="btn btn-primario" type="button" data-drawer-open="drawerReceita">＋ Nova receita</button>
</div>
<section class="summary-strip">
<div>
<small>Total no período</small>
<strong class="positivo"><?=formatarMoeda($totalPeriodo)?></strong>
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
<form method="GET" class="filter-bar" autocomplete="off">
<div class="filter-search">
<span>⌕</span>
<input name="descricao" value="<?=limpar($q)?>" placeholder="Pesquisar receita">
</div>
<select name="categoria_filtro">
<option value="0">Todas as categorias</option><?php foreach($categorias as $c):?><option value="<?=$c['id']?>" <?=$fcat===$c['id']?'selected':''?>><?=limpar($c['nome'])?></option><?php endforeach;?></select>
<select name="conta_filtro">
<option value="0">Todas as contas</option><?php foreach($contas as $c):?><option value="<?=$c['id']?>" <?=$fconta===$c['id']?'selected':''?>><?=limpar($c['nome'])?></option><?php endforeach;?></select>
<input type="date" name="data_inicio" value="<?=limpar($ini)?>" aria-label="Data inicial">
<input type="date" name="data_fim" value="<?=limpar($fim)?>" aria-label="Data final">
<button class="btn btn-secundario">Filtrar</button>
 <?php if($q||$fcat||$fconta||$ini||$fim):?><a class="filter-clear" href="receitas.php">Limpar</a><?php endif;?>
</form>
<section class="data-panel">
<div class="data-panel-head">
<div>
<h2>Movimentações</h2>
<p>Últimas receitas registradas no CashPilot.</p>
</div>
</div>
 <?php if(!$receitas):?><div class="estado-vazio clean-empty">
<span class="cp-empty-icon"><?=cpIcon('arrow-down-left')?></span>
<h3>Nenhuma receita encontrada</h3>
<p>Cadastre sua primeira receita ou ajuste os filtros.</p>
<button class="btn btn-secundario" data-drawer-open="drawerReceita">Adicionar receita</button>
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
</div>
  <?php foreach($receitas as $r):?><div class="table-row">
<div class="cell-main">
<strong><?=limpar($r['descricao'])?></strong><?php if(!empty($r['venda_id'])):?><small>Venda vinculada</small><?php endif;?></div>
<div>
<span class="soft-badge"><?=limpar($r['categoria_nome']??'Sem categoria')?></span>
</div>
<div><?=limpar($r['conta_nome']??'Sem conta')?></div>
<div><?=date('d/m/Y',strtotime($r['data_receita']))?></div>
<div class="positivo money-cell"><?=formatarMoeda((float)$r['valor'])?></div>
<div class="row-actions"><?php if(empty($r['venda_id'])):?><a href="?editar=<?=$r['id']?>">Editar</a>
<form action="../actions/receitas.php" method="POST" data-confirm="Excluir esta receita?"><?=csrfCampo()?><input type="hidden" name="acao" value="excluir">
<input type="hidden" name="id" value="<?=$r['id']?>">
<button class="excluir">Excluir</button>
</form><?php else:?><span class="muted">Automática</span><?php endif;?></div>
</div><?php endforeach;?>
 </div><?php endif;?>
</section>

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

<aside class="cp-drawer <?=$edicao?'aberto':''?>" id="drawerReceita">
<div class="drawer-head">
<div>
<span class="eyebrow"><?=$edicao?'EDIÇÃO':'NOVA MOVIMENTAÇÃO'?></span>
<h2><?=$edicao?'Editar receita':'Nova receita'?></h2>
</div>
<a href="receitas.php" class="drawer-close" data-drawer-close>×</a>
</div>
<div class="drawer-body">
<form action="../actions/receitas.php" method="POST" autocomplete="off"><?=csrfCampo()?><input type="hidden" name="acao" value="<?=$edicao?'editar':'criar'?>"><?php if($edicao):?><input type="hidden" name="id" value="<?=$edicao['id']?>"><?php endif;?>
   <div class="form-grupo">
<label>Descrição</label>
<input name="descricao" required value="<?=limpar($edicao['descricao']??'')?>" placeholder="Ex.: Salário">
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
<button type="button" class="text-button" id="toggleNovaCatReceita">＋ Nova categoria</button>
</div>
<select name="categoria_id"><?php foreach($categorias as $c):?><option value="<?=$c['id']?>" <?=($edicao['categoria_id']??0)==$c['id']?'selected':''?>><?=limpar($c['nome'])?></option><?php endforeach;?></select>
</div>
<div class="inline-new-category" id="novaCatReceita" hidden>
<p>Crie a categoria primeiro; o formulário da receita ficará aberto.</p>
<div class="inline-fields">
<input id="nomeCatReceita" placeholder="Nome da categoria">
<input id="corCatReceita" type="color" value="#2F5D62">
<button type="button" class="btn btn-secundario" id="salvarCatReceita">Criar</button>
</div>
<small id="statusCatReceita">
</small>
</div>
<div class="form-grupo">
<label>Conta</label>
<select name="conta_id">
<option value="">Sem conta</option><?php foreach($contas as $c):?><option value="<?=$c['id']?>" <?=($edicao['conta_id']??0)==$c['id']?'selected':''?>><?=limpar($c['nome'])?></option><?php endforeach;?></select>
</div>
<div class="form-grupo">
<label>Data</label>
<input type="date" name="data_receita" required value="<?=limpar($edicao['data_receita']??date('Y-m-d'))?>">
</div>
<button class="btn btn-primario btn-bloco"><?=$edicao?'Salvar alterações':'Adicionar receita'?></button>
</form>
</div>
</aside>
<?php if($edicao):?><script>
document.addEventListener('DOMContentLoaded',()=>{document.getElementById('drawerOverlay')?.classList.add('ativo');document.body.classList.add('drawer-aberto')});
</script><?php endif;?>
<script>
const tcr=document.getElementById('toggleNovaCatReceita'),ncr=document.getElementById('novaCatReceita');
tcr?.addEventListener('click',()=>ncr.hidden=!ncr.hidden);

document.getElementById('salvarCatReceita')?.addEventListener('click',async()=>{const nome=document.getElementById('nomeCatReceita').value.trim(),status=document.getElementById('statusCatReceita');
if(!nome)return;
const fd=new FormData();
fd.append('acao','criar_ajax');
fd.append('tipo','receita');
fd.append('nome',nome);
fd.append('cor',document.getElementById('corCatReceita').value);
fd.append('csrf_token',<?=json_encode(csrfToken())?>);
try{const r=await fetch('../actions/categorias.php',{method:'POST',body:fd});
const d=await r.json();
if(d.ok){
const s=document.querySelector('#drawerReceita select[name="categoria_id"]');
s.add(new Option(d.nome,d.id,true,true));
status.textContent='Categoria criada.';
}else status.textContent=d.mensagem||'Não foi possível criar.';
}
catch(e){
status.textContent='Não foi possível criar.';
}});

</script>
<?php require_once __DIR__.'/../includes/footer.php';?>
