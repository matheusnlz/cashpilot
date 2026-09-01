<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../database/conexao.php';
require_once __DIR__.'/../includes/negocio_financeiro.php';
exigirLogin();
if(usuarioLogadoTipo()!=='mei'){
header('Location: dashboard.php');
exit;
}

$tituloPagina='Custos';
$pdo=conectar();
$uid=(int)usuarioLogadoId();
cpSincronizarCustosRecorrentesMes($pdo,$uid);
$s=$pdo->prepare('SELECT * FROM custos_negocio WHERE usuario_id=:uid AND ativo=1 ORDER BY recorrente DESC,descricao');
$s->execute(['uid'=>$uid]);
$lista=$s->fetchAll(PDO::FETCH_ASSOC);
$editar=null;
if(isset($_GET['editar']))foreach($lista as $x)if((int)$x['id']===(int)$_GET['editar'])$editar=$x;
$rec=array_sum(array_map(fn($x)=>$x['recorrente']?(float)$x['valor']:0,$lista));
$fixos=array_sum(array_map(fn($x)=>(($x['natureza']??'fixo')==='fixo')?(float)$x['valor']:0,$lista));
$variaveis=array_sum(array_map(fn($x)=>(($x['natureza']??'fixo')==='variavel')?(float)$x['valor']:0,$lista));

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="page-head">
<div>
<span class="eyebrow">ESTRUTURA DE CUSTOS</span>
<h1>Custos</h1>
<p>Custos do negócio separados de funcionários e fornecedores.</p>
</div>
<button class="btn btn-primario" data-drawer-open="drawerCusto">＋ Novo custo</button>
</div>
<section class="summary-strip">
<div>
<small>Custos ativos</small>
<strong><?=count($lista)?></strong>
</div>
<div>
<small>Recorrente mensal</small>
<strong><?=formatarMoeda($rec)?></strong>
</div>
<div>
<small>Custos fixos</small>
<strong><?=formatarMoeda($fixos)?></strong>
</div>
<div>
<small>Custos variáveis</small>
<strong><?=formatarMoeda($variaveis)?></strong>
</div>
</section>
<section class="data-panel"><?php if(!$lista):?><div class="estado-vazio clean-empty">
<span>▧</span>
<h3>Nenhum custo cadastrado</h3>
<p>Registre aluguel, sistemas, marketing e outros custos.</p>
</div><?php else:?><div class="cost-list"><?php foreach($lista as $c):?><div class="cost-row">
<div>
<strong><?=limpar($c['descricao'])?></strong>
<small><?=limpar(ucfirst($c['natureza']??'fixo'))?> · <?=$c['recorrente']?'Recorrente · dia '.$c['dia_vencimento']:'Pontual'?></small>
</div>
<strong><?=formatarMoeda((float)$c['valor'])?></strong>
<div class="row-actions">
<a href="?editar=<?=$c['id']?>">Editar</a>
<form action="../actions/negocio.php" method="POST" data-confirm="Desativar custo?"><?=csrfCampo()?><input type="hidden" name="acao" value="remover_custo">
<input type="hidden" name="id" value="<?=$c['id']?>">
<button class="excluir">Desativar</button>
</form>
</div>
</div><?php endforeach;?></div><?php endif;?></section>
<aside class="cp-drawer <?=$editar?'aberto':''?>" id="drawerCusto">
<div class="drawer-head">
<div>
<span class="eyebrow"><?=$editar?'EDIÇÃO':'CUSTO'?></span>
<h2><?=$editar?'Editar custo':'Novo custo'?></h2>
</div>
<a href="custos.php" class="drawer-close" data-drawer-close>×</a>
</div>
<div class="drawer-body">
<form action="../actions/negocio.php" method="POST"><?=csrfCampo()?><input type="hidden" name="acao" value="<?=$editar?'editar_custo':'adicionar_custo'?>"><?php if($editar):?><input type="hidden" name="id" value="<?=$editar['id']?>"><?php endif;?><div class="form-grupo">
<label>Descrição</label>
<input name="descricao" required value="<?=limpar($editar['descricao']??'')?>">
</div>
<div class="form-grupo">
<label>Valor</label>
<input type="number" step="0.01" min="0.01" name="valor" required value="<?=limpar((string)($editar['valor']??''))?>">
</div>
<div class="form-grupo">
<label>Natureza do custo</label>
<select name="natureza">
<option value="fixo" <?=($editar['natureza']??'fixo')==='fixo'?'selected':''?>>Fixo</option>
<option value="variavel" <?=($editar['natureza']??'fixo')==='variavel'?'selected':''?>>Variável</option>
</select>
<small class="secao-ajuda">Fixo tende a se repetir; variável muda conforme vendas, consumo ou operação.</small>
</div>
<div class="form-grupo">
<label>Dia do vencimento</label>
<input type="number" min="1" max="28" name="dia_vencimento" value="<?=limpar((string)($editar['dia_vencimento']??10))?>">
</div>
<label class="check-card">
<input type="checkbox" name="recorrente" value="1" <?=!isset($editar['recorrente'])||$editar['recorrente']?'checked':''?>>
<span>
<strong>Custo recorrente mensal</strong>
<small>Entra automaticamente nos compromissos do negócio.</small>
</span>
</label>
<button class="btn btn-primario btn-bloco"><?=$editar?'Salvar alterações':'Adicionar custo'?></button>
</form>
</div>
</aside>
<?php if($editar):?><script>
document.addEventListener('DOMContentLoaded',()=>{document.getElementById('drawerOverlay')?.classList.add('ativo');document.body.classList.add('drawer-aberto')});
</script><?php endif;?>
<?php require_once __DIR__.'/../includes/footer.php';?>
