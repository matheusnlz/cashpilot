<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../database/conexao.php';
require_once __DIR__.'/../includes/negocio_financeiro.php';
exigirLogin();
if(usuarioLogadoTipo()!=='mei'){
header('Location: dashboard.php');
exit;
}

$tituloPagina='Funcionários';
$pdo=conectar();
$uid=(int)usuarioLogadoId();
cpSincronizarCustosRecorrentesMes($pdo,$uid);
$s=$pdo->prepare('SELECT * FROM funcionarios WHERE usuario_id=:uid AND ativo=1 ORDER BY nome');
$s->execute(['uid'=>$uid]);
$lista=$s->fetchAll(PDO::FETCH_ASSOC);
$editar=null;
if(isset($_GET['editar']))foreach($lista as $x)if((int)$x['id']===(int)$_GET['editar'])$editar=$x;
$custo=array_sum(array_map(fn($x)=>(float)$x['salario_base']+(float)$x['outros_custos'],$lista));

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="page-head">
<div>
<span class="eyebrow">EQUIPE</span>
<h1>Funcionários</h1>
<p>Veja a equipe e o custo mensal sem misturar cadastro com consulta.</p>
</div>
<button class="btn btn-primario" data-drawer-open="drawerFuncionario">＋ Novo funcionário</button>
</div>
<section class="summary-strip">
<div>
<small>Equipe ativa</small>
<strong><?=count($lista)?></strong>
</div>
<div>
<small>Custo mensal da equipe</small>
<strong><?=formatarMoeda($custo)?></strong>
</div>
<div>
<small>Próximo ciclo</small>
<strong><?=date('m/Y',strtotime('+1 month'))?></strong>
</div>
<div>
<small>Custo médio por pessoa</small>
<strong><?=formatarMoeda(count($lista)?$custo/count($lista):0)?></strong>
</div>
</section>
<section class="data-panel">
<div class="data-panel-head">
<div>
<h2>Equipe ativa</h2>
<p>Funcionários cadastrados alimentam automaticamente os custos recorrentes.</p>
</div>
</div><?php if(!$lista):?><div class="estado-vazio clean-empty">
<span>♙</span>
<h3>Nenhum funcionário</h3>
<p>Cadastre sua equipe para acompanhar o impacto mensal.</p>
</div><?php else:?><div class="people-grid"><?php foreach($lista as $f):?><article class="person-card">
<div class="person-avatar"><?=limpar(mb_strtoupper(mb_substr($f['nome'],0,1)))?></div>
<div class="person-info">
<h3><?=limpar($f['nome'])?></h3>
<p><?=limpar($f['cargo']?:'Sem cargo')?></p>
<div>
<span>Custo mensal</span>
<strong><?=formatarMoeda((float)$f['salario_base']+(float)$f['outros_custos'])?></strong>
</div>
<small>Pagamento dia <?=$f['dia_pagamento']?></small>
</div>
<div class="person-actions">
<a href="?editar=<?=$f['id']?>">Editar</a>
<form action="../actions/negocio.php" method="POST" data-confirm="Desativar funcionário?"><?=csrfCampo()?><input type="hidden" name="acao" value="remover_funcionario">
<input type="hidden" name="id" value="<?=$f['id']?>">
<button class="excluir">Desativar</button>
</form>
</div>
</article><?php endforeach;?></div><?php endif;?></section>
<aside class="cp-drawer <?=$editar?'aberto':''?>" id="drawerFuncionario">
<div class="drawer-head">
<div>
<span class="eyebrow"><?=$editar?'EDIÇÃO':'EQUIPE'?></span>
<h2><?=$editar?'Editar funcionário':'Novo funcionário'?></h2>
</div>
<a href="funcionarios.php" class="drawer-close" data-drawer-close>×</a>
</div>
<div class="drawer-body">
<form action="../actions/negocio.php" method="POST"><?=csrfCampo()?><input type="hidden" name="acao" value="<?=$editar?'editar_funcionario':'adicionar_funcionario'?>"><?php if($editar):?><input type="hidden" name="id" value="<?=$editar['id']?>"><?php endif;?><div class="form-grupo">
<label>Nome</label>
<input name="nome" required value="<?=limpar($editar['nome']??'')?>">
</div>
<div class="form-grupo">
<label>Cargo/Função</label>
<input name="cargo" value="<?=limpar($editar['cargo']??'')?>">
</div>
<div class="form-linha">
<div class="form-grupo">
<label>Salário/base</label>
<input type="number" step="0.01" min="0" name="salario_base" required value="<?=limpar((string)($editar['salario_base']??''))?>">
</div>
<div class="form-grupo">
<label>Outros custos mensais</label>
<input type="number" step="0.01" min="0" name="outros_custos" value="<?=limpar((string)($editar['outros_custos']??0))?>">
</div>
</div>
<div class="form-grupo">
<label>Dia de pagamento</label>
<input type="number" min="1" max="28" name="dia_pagamento" value="<?=limpar((string)($editar['dia_pagamento']??5))?>">
</div>
<button class="btn btn-primario btn-bloco"><?=$editar?'Salvar alterações':'Adicionar funcionário'?></button>
</form>
</div>
</aside>
<?php if($editar):?><script>
document.addEventListener('DOMContentLoaded',()=>{document.getElementById('drawerOverlay')?.classList.add('ativo');document.body.classList.add('drawer-aberto')});
</script><?php endif;?>
<?php require_once __DIR__.'/../includes/footer.php';?>
