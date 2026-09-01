<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../database/conexao.php';
exigirLogin();
if(usuarioLogadoTipo()!=='pessoa_fisica'){
header('Location: dashboard.php');
exit;
}

$tituloPagina='Orçamentos';
$pdo=conectar();
$uid=usuarioLogadoId();
$i=date('Y-m-01');
$f=date('Y-m-t');

$s=$pdo->prepare('SELECT c.id,c.nome,c.cor,c.limite_mensal,COALESCE(SUM(d.valor),0) gasto FROM categorias c LEFT JOIN despesas d ON d.categoria_id=c.id AND d.usuario_id=:u1 AND d.data_despesa BETWEEN :i AND :f WHERE c.usuario_id=:u2 AND c.tipo="despesa" GROUP BY c.id ORDER BY c.nome');
$s->execute(['u1'=>$uid,'i'=>$i,'f'=>$f,'u2'=>$uid]);
$cats=$s->fetchAll();
$msg=$_SESSION['mensagem_pf']??null;
unset($_SESSION['mensagem_pf']);

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="topo-pagina">
<div>
<span class="eyebrow">PLANEJAMENTO DE GASTOS</span>
<h1>Orçamentos</h1>
<p>Defina limites por categoria e acompanhe o quanto ainda pode gastar.</p>
</div>
<button type="button" class="btn btn-secundario" data-copiloto-pergunta="Com base nos meus últimos meses, sugira um orçamento mensal por categoria e explique suas prioridades.">✦ Sugerir com Copiloto</button>
</div>
<?php if($msg):?><div class="alerta-mensagem sucesso"><?=limpar($msg)?></div><?php endif;?>
<form action="../actions/pf.php" method="POST" autocomplete="off"><?=csrfCampo()?><input type="hidden" name="acao" value="salvar_orcamento">
<div class="orcamento-grid">
<?php foreach($cats as $c):$lim=(float)($c['limite_mensal']??0);
$g=(float)$c['gasto'];
$pct=$lim>0?min(130,$g/$lim*100):0;?>
<div class="cartao orcamento-item">
<div class="orcamento-cab">
<span class="cat-dot" style="background:<?=limpar($c['cor']??'#777')?>">
</span>
<div>
<strong><?=limpar($c['nome'])?></strong>
<small><?=formatarMoeda($g)?> gastos neste mês</small>
</div>
<b class="<?=$lim>0&&$g>$lim?'negativo':''?>"><?=$lim>0?number_format($g/$lim*100,0).'%':'Sem limite'?></b>
</div>
<div class="barra-progresso">
<div class="preenchido <?=$lim>0&&$g>$lim?'barra-erro':''?>" style="width:<?=min(100,$pct)?>%">
</div>
</div>
<div class="form-grupo" style="margin-top:12px">
<label>Limite mensal</label>
<input type="number" step="0.01" min="0" name="limites[<?=$c['id']?>]" value="<?=$c['limite_mensal']!==null?limpar((string)$c['limite_mensal']):''?>" placeholder="Sem limite">
</div>
</div>
<?php endforeach;?></div>
<button class="btn btn-primario">Salvar orçamentos</button>
</form>
<?php require_once __DIR__.'/../includes/footer.php';?>
