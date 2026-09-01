<?php
require_once __DIR__.'/../includes/auth.php';

require_once __DIR__.'/../database/conexao.php';

exigirLogin();

if(usuarioLogadoTipo()!=='mei'){
header('Location: dashboard.php');
exit;
}


$tituloPagina='Produtos e Serviços';
$pdo=conectar();
$uid=(int)usuarioLogadoId();

$s=$pdo->prepare('SELECT p.*,f.nome fornecedor_nome,
COALESCE(SUM(CASE WHEN v.id IS NOT NULL THEN vi.quantidade ELSE 0 END),0) qtd,
COALESCE(SUM(CASE WHEN v.id IS NOT NULL THEN vi.preco_unitario*vi.quantidade ELSE 0 END),0) faturamento,
COALESCE(SUM(CASE WHEN v.id IS NOT NULL THEN (vi.preco_unitario-vi.custo_unitario)*vi.quantidade ELSE 0 END),0) lucro
FROM produtos_servicos p
LEFT JOIN fornecedores f ON f.id=p.fornecedor_id
LEFT JOIN venda_itens vi ON vi.produto_servico_id=p.id
LEFT JOIN vendas v ON v.id=vi.venda_id AND v.usuario_id=:uv
WHERE p.usuario_id=:uid AND p.ativo=1
GROUP BY p.id,f.nome
ORDER BY faturamento DESC,p.nome');

$s->execute(['uv'=>$uid,'uid'=>$uid]);
$catalogo=$s->fetchAll(PDO::FETCH_ASSOC);


$forn=$pdo->prepare('SELECT id,nome FROM fornecedores WHERE usuario_id=:uid AND ativo=1 ORDER BY nome');
$forn->execute(['uid'=>$uid]);
$fornecedores=$forn->fetchAll(PDO::FETCH_ASSOC);


$editar=null;
$estoqueItem=null;
$historico=[];

if(isset($_GET['editar']))foreach($catalogo as $x)if((int)$x['id']===(int)$_GET['editar'])$editar=$x;

if(isset($_GET['estoque'])){
foreach($catalogo as $x)if((int)$x['id']===(int)$_GET['estoque'])$estoqueItem=$x;
if($estoqueItem){
$h=$pdo->prepare('SELECT m.*,f.nome fornecedor_nome FROM movimentacoes_estoque m LEFT JOIN fornecedores f ON f.id=m.fornecedor_id WHERE m.usuario_id=:uid AND m.produto_id=:pid ORDER BY m.data_movimentacao DESC,m.id DESC LIMIT 30');
$h->execute(['uid'=>$uid,'pid'=>$estoqueItem['id']]);
$historico=$h->fetchAll(PDO::FETCH_ASSOC);
}
}

$msg=$_SESSION['mensagem_negocio']??null;
unset($_SESSION['mensagem_negocio']);


require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="page-head">
<div>
<span class="eyebrow">CATÁLOGO & ESTOQUE</span>
<h1>Produtos e Serviços</h1>
<p>Gerencie catálogo, margem, fornecedor e estoque sem misturar funções.</p>
</div>
<button class="btn btn-primario" data-drawer-open="drawerProduto">＋ Novo item</button>
</div>
<?php if($msg):?><div class="alerta-mensagem sucesso"><?=limpar($msg)?></div><?php endif;?>

<section class="summary-strip">
<div>
<small>Itens ativos</small>
<strong><?=count($catalogo)?></strong>
</div>
<div>
<small>Estoque baixo</small>
<strong><?=count(array_filter($catalogo,fn($x)=>$x['tipo']==='produto'&&!empty($x['controlar_estoque'])&&(int)$x['estoque_atual']<=(int)$x['estoque_minimo']))?></strong>
</div>
<div>
<small>Faturamento do catálogo</small>
<strong><?=formatarMoeda(array_sum(array_map(fn($x)=>(float)$x['faturamento'],$catalogo)))?></strong>
</div>
<div>
<small>Mais vendido</small>
<strong><?=limpar(($catalogo[0]['nome']??'Sem vendas'))?></strong>
</div>
</section>
<section class="data-panel">
<div class="data-panel-head">
<div>
<h2>Catálogo</h2>
<p>Produtos e serviços cadastrados para vendas.</p>
</div>
</div>
 <?php if(!$catalogo):?><div class="estado-vazio clean-empty">
<span>▤</span>
<h3>Catálogo vazio</h3>
<p>Cadastre seu primeiro produto ou serviço.</p>
<button class="btn btn-secundario" data-drawer-open="drawerProduto">Adicionar item</button>
</div>
 <?php else:?><div class="catalog-grid-clean"><?php foreach($catalogo as $p):$pre=(float)$p['preco_venda'];
$cus=(float)$p['custo_unitario'];
$m=$pre>0?($pre-$cus)/$pre*100:0;
$baixo=$p['tipo']==='produto'&&!empty($p['controlar_estoque'])&&(int)$p['estoque_atual']<=(int)$p['estoque_minimo'];?>
 <article class="catalog-card <?=$baixo?'alert':''?>">
<div class="catalog-card-top">
<div>
<span class="soft-badge"><?=limpar($p['tipo']==='produto'?'Produto':'Serviço')?></span>
<h3><?=limpar($p['nome'])?></h3>
<p><?=limpar($p['fornecedor_nome']??($p['tipo']==='produto'?'Sem fornecedor':'Serviço'))?></p>
</div>
<div class="catalog-price"><?=formatarMoeda($pre)?></div>
</div>
<div class="catalog-metrics">
<span>
<small>Margem</small>
<strong><?=number_format($m,1,',','.')?>%</strong>
</span>
<span>
<small>Vendido</small>
<strong><?=(int)$p['qtd']?></strong>
</span>
<span>
<small>Faturamento</small>
<strong><?=formatarMoeda((float)$p['faturamento'])?></strong>
</span><?php if($p['tipo']==='produto'&&!empty($p['controlar_estoque'])):?><span>
<small>Estoque</small>
<strong class="<?=$baixo?'negativo':''?>"><?=(int)$p['estoque_atual']?> un.</strong>
</span><?php endif;?></div>
<div class="catalog-actions">
<a href="?editar=<?=$p['id']?>" class="text-button">Editar</a><?php if($p['tipo']==='produto'&&!empty($p['controlar_estoque'])):?><a href="?estoque=<?=$p['id']?>" class="text-button">Estoque</a><?php endif;?><form action="../actions/negocio.php" method="POST" data-confirm="Desativar este item?"><?=csrfCampo()?><input type="hidden" name="acao" value="remover_item">
<input type="hidden" name="id" value="<?=$p['id']?>">
<button class="excluir">Desativar</button>
</form>
</div>
</article><?php endforeach;?></div><?php endif;?>
</section>
<aside class="cp-drawer <?=$editar?'aberto':''?>" id="drawerProduto">
<div class="drawer-head">
<div>
<span class="eyebrow"><?=$editar?'EDIÇÃO':'CATÁLOGO'?></span>
<h2><?=$editar?'Editar item':'Novo produto ou serviço'?></h2>
</div>
<a href="produtos_servicos.php" class="drawer-close" data-drawer-close>×</a>
</div>
<div class="drawer-body">
<form action="../actions/negocio.php" method="POST"><?=csrfCampo()?><input type="hidden" name="acao" value="<?=$editar?'editar_item':'adicionar_item'?>"><?php if($editar):?><input type="hidden" name="id" value="<?=$editar['id']?>"><?php endif;?>
  <div class="form-grupo">
<label>Nome</label>
<input name="nome" required value="<?=limpar($editar['nome']??'')?>">
</div>
<div class="form-grupo">
<label>Tipo</label>
<select name="tipo" id="produtoTipo">
<option value="produto" <?=($editar['tipo']??'produto')==='produto'?'selected':''?>>Produto</option>
<option value="servico" <?=($editar['tipo']??'')==='servico'?'selected':''?>>Serviço</option>
</select>
</div>
<div class="form-linha">
<div class="form-grupo">
<label>Preço de venda</label>
<input type="number" step="0.01" min="0" name="preco_venda" required value="<?=limpar((string)($editar['preco_venda']??''))?>">
</div>
<div class="form-grupo">
<label>Custo unitário/execução</label>
<input type="number" step="0.01" min="0" name="custo_unitario" value="<?=limpar((string)($editar['custo_unitario']??0))?>">
</div>
</div>
<div id="produtoEstoqueCampos">
<div class="form-grupo">
<label>Fornecedor principal</label>
<select name="fornecedor_id">
<option value="">Sem fornecedor</option><?php foreach($fornecedores as $f):?><option value="<?=$f['id']?>" <?=($editar['fornecedor_id']??0)==$f['id']?'selected':''?>><?=limpar($f['nome'])?></option><?php endforeach;?></select>
</div>
<label class="check-card">
<input type="checkbox" name="controlar_estoque" id="ctrlEst" value="1" <?=!empty($editar['controlar_estoque'])?'checked':''?>>
<span>
<strong>Controlar estoque</strong>
<small>As vendas darão baixa automaticamente.</small>
</span>
</label>
<div class="form-linha" id="estoqueNumeros">
<div class="form-grupo">
<label>Estoque atual</label>
<input type="number" min="0" name="estoque_atual" value="<?=limpar((string)($editar['estoque_atual']??0))?>">
</div>
<div class="form-grupo">
<label>Estoque mínimo</label>
<input type="number" min="0" name="estoque_minimo" value="<?=limpar((string)($editar['estoque_minimo']??0))?>">
</div>
</div>
</div>
<button class="btn btn-primario btn-bloco"><?=$editar?'Salvar alterações':'Adicionar ao catálogo'?></button>
</form>
</div>
</aside>

<?php if($estoqueItem):?>
<aside class="cp-drawer aberto wide" id="drawerEstoque">
<div class="drawer-head">
<div>
<span class="eyebrow">ESTOQUE</span>
<h2><?=limpar($estoqueItem['nome'])?></h2>
<p><?= (int)$estoqueItem['estoque_atual']?> unidades disponíveis</p>
</div>
<a href="produtos_servicos.php" class="drawer-close" data-drawer-close>×</a>
</div>
<div class="drawer-body">
<div class="drawer-section">
<h3>Adicionar estoque</h3>
<form action="../actions/negocio.php" method="POST"><?=csrfCampo()?><input type="hidden" name="acao" value="entrada_estoque">
<input type="hidden" name="produto_id" value="<?=$estoqueItem['id']?>">
<div class="form-linha">
<div class="form-grupo">
<label>Quantidade</label>
<input type="number" min="1" name="quantidade" required>
</div>
<div class="form-grupo">
<label>Custo unitário</label>
<input type="number" step="0.01" min="0" name="custo_unitario" value="<?=limpar((string)$estoqueItem['custo_unitario'])?>">
</div>
</div>
<div class="form-grupo">
<label>Fornecedor</label>
<select name="fornecedor_id">
<option value="">Usar fornecedor do produto</option><?php foreach($fornecedores as $f):?><option value="<?=$f['id']?>" <?=($estoqueItem['fornecedor_id']??0)==$f['id']?'selected':''?>><?=limpar($f['nome'])?></option><?php endforeach;?></select>
</div>
<div class="form-grupo">
<label>Data</label>
<input type="date" name="data_movimentacao" value="<?=date('Y-m-d')?>">
</div>
<label class="check-card">
<input type="checkbox" name="registrar_despesa" value="1" checked>
<span>
<strong>Registrar compra como despesa</strong>
<small>Quantidade × custo unitário entra em Estoque e insumos.</small>
</span>
</label>
<button class="btn btn-primario btn-bloco">Adicionar estoque</button>
</form>
</div>
<div class="drawer-section">
<h3>Histórico</h3><?php if(!$historico):?><p class="texto-vazio">Nenhuma movimentação registrada.</p><?php else:?><div class="stock-history"><?php foreach($historico as $m):?><div>
<span class="stock-sign <?=((int)$m['quantidade'])>=0?'positivo':'negativo'?>"><?=((int)$m['quantidade'])>=0?'+':''?><?=$m['quantidade']?></span>
<div>
<strong><?=limpar(ucfirst($m['tipo']))?></strong>
<small><?=date('d/m/Y H:i',strtotime($m['data_movimentacao']))?><?=!empty($m['fornecedor_nome'])?' · '.limpar($m['fornecedor_nome']):''?><?=!empty($m['referencia'])?' · '.limpar($m['referencia']):''?></small>
</div>
</div><?php endforeach;?></div><?php endif;?></div>
</div>
</aside>
<?php endif;?>

<?php if($editar||$estoqueItem):?><script>
document.addEventListener('DOMContentLoaded',()=>{document.getElementById('drawerOverlay')?.classList.add('ativo');document.body.classList.add('drawer-aberto')});
</script><?php endif;?>
<script>
const pt=document.getElementById('produtoTipo'),pe=document.getElementById('produtoEstoqueCampos'),ce=document.getElementById('ctrlEst'),en=document.getElementById('estoqueNumeros');
function syncProduto(){
if(!pt)return;
pe.style.display=pt.value==='produto'?'block':'none';
if(en)en.style.display=ce?.checked?'grid':'none'}
pt?.addEventListener('change',syncProduto);
ce?.addEventListener('change',syncProduto);
syncProduto();
</script>
<?php require_once __DIR__.'/../includes/footer.php';?>
