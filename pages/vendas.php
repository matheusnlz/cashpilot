<?php
require_once __DIR__.'/../includes/auth.php';

require_once __DIR__.'/../database/conexao.php';

exigirLogin();

if(usuarioLogadoTipo()!=='mei'){
header('Location: dashboard.php');
exit;
}


$tituloPagina='Vendas';
$pdo=conectar();
$uid=(int)usuarioLogadoId();


$s=$pdo->prepare('SELECT v.id,v.data_venda,v.valor_bruto,v.custo_total,v.observacao,
GROUP_CONCAT(CONCAT(vi.nome_item," x",vi.quantidade) ORDER BY vi.id SEPARATOR ", ") itens
FROM vendas v
LEFT JOIN venda_itens vi ON vi.venda_id=v.id
WHERE v.usuario_id=:uid
GROUP BY v.id
ORDER BY v.data_venda DESC,v.id DESC
LIMIT 100');

$s->execute(['uid'=>$uid]);
$vendas=$s->fetchAll(PDO::FETCH_ASSOC);


$s=$pdo->prepare('SELECT id,nome,tipo,preco_venda,custo_unitario,estoque_atual,controlar_estoque FROM produtos_servicos WHERE usuario_id=:uid AND ativo=1 ORDER BY nome');

$s->execute(['uid'=>$uid]);
$catalogoVenda=$s->fetchAll(PDO::FETCH_ASSOC);


$fat=array_sum(array_map(fn($x)=>(float)$x['valor_bruto'],$vendas));

$custo=array_sum(array_map(fn($x)=>(float)$x['custo_total'],$vendas));

$margem=$fat>0?($fat-$custo)/$fat*100:0;

$msg=$_SESSION['mensagem_venda']??null;
unset($_SESSION['mensagem_venda']);


require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>

<div class="page-head">
<div>
<span class="eyebrow">COMERCIAL</span>
<h1>Vendas</h1>
<p>Monte vendas com vários itens e atualize receita, margem e estoque automaticamente.</p>
</div>
<button class="btn btn-primario" data-drawer-open="drawerVenda">＋ Registrar venda</button>
</div>
<?php if($msg):?><div class="alerta-mensagem sucesso"><?=limpar($msg)?></div><?php endif;?>

<section class="summary-strip">
<div>
<small>Vendas listadas</small>
<strong><?=count($vendas)?></strong>
</div>
<div>
<small>Faturamento</small>
<strong><?=formatarMoeda($fat)?></strong>
</div>
<div>
<small>Margem bruta</small>
<strong><?=number_format($margem,1,',','.')?>%</strong>
</div>
</section>
<section class="data-panel">
<div class="data-panel-head">
<div>
<h2>Histórico</h2>
<p>Vendas registradas com produtos e serviços vinculados.</p>
</div>
</div>
 <?php if(!$vendas):?><div class="estado-vazio clean-empty">
<span class="cp-empty-icon"><?=cpIcon('cart')?></span>
<h3>Nenhuma venda registrada</h3>
<p>Cadastre itens no catálogo e registre a primeira venda.</p>
<button class="btn btn-secundario" data-drawer-open="drawerVenda">Registrar venda</button>
</div>
 <?php else:?><div class="responsive-list">
<div class="table-row table-head vendas-row">
<span>Data</span>
<span>Itens</span>
<span>Receita</span>
<span>Custo</span>
<span>Margem</span>
<span>
</span>
</div>
 <?php foreach($vendas as $v):$vb=(float)$v['valor_bruto'];
$ct=(float)$v['custo_total'];
$m=$vb>0?($vb-$ct)/$vb*100:0;?><div class="table-row vendas-row">
<div><?=date('d/m/Y',strtotime($v['data_venda']))?></div>
<div class="cell-main">
<strong><?=limpar($v['itens']?:'Venda')?></strong><?php if($v['observacao']):?><small><?=limpar($v['observacao'])?></small><?php endif;?></div>
<div class="positivo money-cell"><?=formatarMoeda($vb)?></div>
<div><?=formatarMoeda($ct)?></div>
<div><?=number_format($m,1,',','.')?>%</div>
<div>
</div>
</div><?php endforeach;?></div><?php endif;?>
</section>
<aside class="cp-drawer wide" id="drawerVenda">
<div class="drawer-head">
<div>
<span class="eyebrow">NOVA VENDA</span>
<h2>Montar venda</h2>
<p>Escolha quantidades diferentes para cada item.</p>
</div>
<button class="drawer-close" data-drawer-close type="button">×</button>
</div>
<div class="drawer-body">
 <?php if(!$catalogoVenda):?><div class="estado-vazio clean-empty">
<span class="cp-empty-icon"><?=cpIcon('box')?></span>
<h3>Catálogo vazio</h3>
<p>Cadastre produtos ou serviços antes de vender.</p>
<a class="btn btn-secundario" href="produtos_servicos.php">Abrir catálogo</a>
</div>
 <?php else:?><form action="../actions/vendas.php" method="POST" autocomplete="off" id="vendaForm"><?=csrfCampo()?>
   <div class="sale-builder">
    <?php foreach($catalogoVenda as $i):?><div class="sale-builder-item" data-preco="<?=limpar((string)($i['preco_venda']??0))?>" data-custo="<?=limpar((string)($i['custo_unitario']??0))?>">
<div>
<strong><?=limpar($i['nome']??'Item sem nome')?></strong>
<small><?=limpar(($i['tipo']??'produto')==='produto'?'Produto':'Serviço')?> · <?=formatarMoeda((float)($i['preco_venda']??0))?><?php if(($i['tipo']??'')==='produto'&&!empty($i['controlar_estoque'])):?> · estoque <?=(int)($i['estoque_atual']??0)?><?php endif;?></small>
</div>
<div class="qtd-control">
<button type="button" class="qmenos">−</button>
<input type="number" min="0" name="itens[<?=(int)($i['id']??0)?>]" value="0" class="qinput">
<button type="button" class="qmais">+</button>
</div>
</div><?php endforeach;?>
   </div>
<div class="sale-total-box">
<div>
<span>Itens</span>
<strong id="vQtd">0</strong>
</div>
<div>
<span>Receita</span>
<strong id="vTotal">R$ 0,00</strong>
</div>
<div>
<span>Custo</span>
<strong id="vCusto">R$ 0,00</strong>
</div>
<div>
<span>Margem</span>
<strong id="vMargem">0%</strong>
</div>
</div>
<div class="form-linha">
<div class="form-grupo">
<label>Data</label>
<input type="date" name="data_venda" value="<?=date('Y-m-d')?>" required>
</div>
<div class="form-grupo">
<label>Observação</label>
<input name="observacao" placeholder="Opcional">
</div>
</div>
<button class="btn btn-primario btn-bloco">Registrar venda</button>
</form><?php endif;?>
 </div>
</aside>
<script>
const moeda=v=>Number(v).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});

function calcularVenda(){
let q=0,t=0,c=0;
document.querySelectorAll('.sale-builder-item').forEach(el=>{const n=Math.max(0,+el.querySelector('.qinput').value||0);q+=n;t+=n*(+el.dataset.preco||0);c+=n*(+el.dataset.custo||0)});
document.getElementById('vQtd').textContent=q;
document.getElementById('vTotal').textContent=moeda(t);
document.getElementById('vCusto').textContent=moeda(c);
document.getElementById('vMargem').textContent=(t>0?((t-c)/t*100):0).toFixed(1).replace('.',',')+'%';
}

document.querySelectorAll('.sale-builder-item').forEach(el=>{const i=el.querySelector('.qinput');el.querySelector('.qmais').onclick=()=>{i.value=(+i.value||0)+1;calcularVenda()};el.querySelector('.qmenos').onclick=()=>{i.value=Math.max(0,(+i.value||0)-1);calcularVenda()};i.addEventListener('input',calcularVenda)});
calcularVenda();
</script>
<?php require_once __DIR__.'/../includes/footer.php';?>
