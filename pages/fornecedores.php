<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../database/conexao.php';
require_once __DIR__.'/../includes/negocio_financeiro.php';
exigirLogin();
if(usuarioLogadoTipo()!=='mei'){
header('Location: dashboard.php');
exit;
}

$tituloPagina='Fornecedores';
$pdo=conectar();
$uid=(int)usuarioLogadoId();
cpSincronizarCustosRecorrentesMes($pdo,$uid);
$s=$pdo->prepare('SELECT f.*,COUNT(p.id) produtos_vinculados FROM fornecedores f LEFT JOIN produtos_servicos p ON p.fornecedor_id=f.id AND p.usuario_id=f.usuario_id AND p.ativo=1 WHERE f.usuario_id=:uid AND f.ativo=1 GROUP BY f.id ORDER BY f.nome');
$s->execute(['uid'=>$uid]);
$lista=$s->fetchAll(PDO::FETCH_ASSOC);
$editar=null;
if(isset($_GET['editar']))foreach($lista as $x)if((int)$x['id']===(int)$_GET['editar'])$editar=$x;

$comp=cpCompromissosMensais($pdo,$uid);
$maiorFornecedor=null;
if($lista){
$maiorFornecedor=$lista[0];
foreach($lista as $fx){
if((float)$fx['valor_padrao']>(float)$maiorFornecedor['valor_padrao'])$maiorFornecedor=$fx;
}
}

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="page-head">
<div>
<span class="eyebrow">PARCEIROS & COMPRAS</span>
<h1>Fornecedores</h1>
<p>Frequência, custo e produtos vinculados em uma visão organizada.</p>
</div>
<button class="btn btn-primario" data-drawer-open="drawerFornecedor">＋ Novo fornecedor</button>
</div>
<section class="summary-strip">
<div>
<small>Fornecedores ativos</small>
<strong><?=count($lista)?></strong>
</div>
<div>
<small>Compromisso mensal estimado</small>
<strong><?=formatarMoeda((float)$comp['fornecedores'])?></strong>
</div>
<div>
<small>Maior cobrança cadastrada</small>
<strong><?=limpar($maiorFornecedor['nome']??'—')?></strong>
</div>
</section>
<section class="data-panel">
<div class="data-panel-head">
<div>
<h2>Fornecedores ativos</h2>
<p>Os pagamentos recorrentes alimentam seus compromissos futuros.</p>
</div>
</div><?php if(!$lista):?><div class="estado-vazio clean-empty">
<span>◇</span>
<h3>Nenhum fornecedor</h3>
<p>Cadastre fornecedores para estruturar melhor seus custos.</p>
</div><?php else:?><div class="supplier-grid"><?php foreach($lista as $f):$per=$f['periodicidade']??($f['recorrente']?'mensal':'pontual');?><article class="supplier-card">
<div>
<span class="soft-badge"><?=limpar(ucfirst($per))?></span>
<h3><?=limpar($f['nome'])?></h3>
<p><?=limpar($f['descricao']?:'Sem descrição')?></p>
</div>
<div class="supplier-data">
<span>
<small>Por cobrança</small>
<strong><?=formatarMoeda((float)$f['valor_padrao'])?></strong>
</span>
<span>
<small>Produtos vinculados</small>
<strong><?=(int)$f['produtos_vinculados']?></strong>
</span>
<span>
<small>Vencimento base</small>
<strong>Dia <?=$f['dia_vencimento']?></strong>
</span>
</div>
<div class="catalog-actions">
<a href="?editar=<?=$f['id']?>">Editar</a>
<form action="../actions/negocio.php" method="POST" data-confirm="Desativar fornecedor?"><?=csrfCampo()?><input type="hidden" name="acao" value="remover_fornecedor">
<input type="hidden" name="id" value="<?=$f['id']?>">
<button class="excluir">Desativar</button>
</form>
</div>
</article><?php endforeach;?></div><?php endif;?></section>
<aside class="cp-drawer <?=$editar?'aberto':''?>" id="drawerFornecedor">
<div class="drawer-head">
<div>
<span class="eyebrow"><?=$editar?'EDIÇÃO':'FORNECEDOR'?></span>
<h2><?=$editar?'Editar fornecedor':'Novo fornecedor'?></h2>
</div>
<a href="fornecedores.php" class="drawer-close" data-drawer-close>×</a>
</div>
<div class="drawer-body">
<form action="../actions/negocio.php" method="POST"><?=csrfCampo()?><input type="hidden" name="acao" value="<?=$editar?'editar_fornecedor':'adicionar_fornecedor'?>"><?php if($editar):?><input type="hidden" name="id" value="<?=$editar['id']?>"><?php endif;?><div class="form-grupo">
<label>Nome</label>
<input name="nome" required value="<?=limpar($editar['nome']??'')?>">
</div>
<div class="form-grupo">
<label>O que fornece?</label>
<input name="descricao" value="<?=limpar($editar['descricao']??'')?>" placeholder="Ex.: bebidas, embalagens, internet">
</div>
<div class="form-grupo">
<label>Valor esperado por cobrança</label>
<input type="number" step="0.01" min="0" name="valor_padrao" required value="<?=limpar((string)($editar['valor_padrao']??''))?>">
</div>
<div class="form-linha">
<div class="form-grupo">
<label>Frequência</label>
<select name="periodicidade" id="periodicidadeFornecedor"><?php $at=$editar['periodicidade']??(!empty($editar['recorrente'])?'mensal':'pontual');
foreach(['pontual'=>'Pontual','semanal'=>'Semanal','quinzenal'=>'A cada 15 dias','mensal'=>'Mensal','outro'=>'A cada X dias'] as $v=>$l):?><option value="<?=$v?>" <?=$at===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select>
</div>
<div class="form-grupo">
<label>Dia base</label>
<input type="number" min="1" max="28" name="dia_vencimento" value="<?=limpar((string)($editar['dia_vencimento']??10))?>">
</div>
</div>
<div class="form-grupo" id="intervaloFornecedor">
<label>Intervalo em dias</label>
<input type="number" min="2" max="180" name="intervalo_dias" value="<?=limpar((string)($editar['intervalo_dias']??30))?>">
</div>
<button class="btn btn-primario btn-bloco"><?=$editar?'Salvar alterações':'Adicionar fornecedor'?></button>
</form>
</div>
</aside>
<?php if($editar):?><script>
document.addEventListener('DOMContentLoaded',()=>{document.getElementById('drawerOverlay')?.classList.add('ativo');document.body.classList.add('drawer-aberto')});
</script><?php endif;?>
<script>
const pf=document.getElementById('periodicidadeFornecedor'),inf=document.getElementById('intervaloFornecedor');
function sf(){
if(inf)inf.hidden=pf.value!=='outro'}
pf?.addEventListener('change',sf);
sf();
</script>
<?php require_once __DIR__.'/../includes/footer.php';?>
