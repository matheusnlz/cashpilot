<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../database/conexao.php';
require_once __DIR__.'/../includes/pf_financeiro.php';
exigirLogin();
if(usuarioLogadoTipo()!=='pessoa_fisica'){
header('Location: dashboard.php');
exit;
}

$tituloPagina='Recorrências';
$pdo=conectar();
$uid=usuarioLogadoId();
cpSincronizarRecorrenciasPF($pdo,$uid);
$res=cpResumoRecorrenciasPF($pdo,$uid);

$s=$pdo->prepare('SELECT r.*,c.nome categoria FROM recorrencias_pf r LEFT JOIN categorias c ON c.id=r.categoria_id WHERE r.usuario_id=:uid AND r.ativo=1 ORDER BY r.tipo DESC,r.proxima_data,r.nome');
$s->execute(['uid'=>$uid]);
$recorrenciasAtivas=$s->fetchAll(PDO::FETCH_ASSOC);

$s=$pdo->prepare('SELECT id,nome FROM categorias WHERE usuario_id=:uid AND tipo="despesa" ORDER BY nome');
$s->execute(['uid'=>$uid]);
$cats=$s->fetchAll();
$edit=null;
if(isset($_GET['editar'])){
foreach($recorrenciasAtivas as $x)if((int)$x['id']===(int)$_GET['editar'])$edit=$x;
}

$msg=$_SESSION['mensagem_pf']??null;
unset($_SESSION['mensagem_pf']);
require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="topo-pagina">
<div>
<span class="eyebrow">COMPROMISSOS PESSOAIS</span>
<h1>Recorrências</h1>
<p>Organize contas, mensalidades e assinaturas que se repetem.</p>
</div>
</div><?php if($msg):?><div class="alerta-mensagem sucesso"><?=limpar($msg)?></div><?php endif;?>
<div class="grade-tripla">
<div class="cartao cartao-metrica">
<div class="rotulo">Recorrências / mês</div>
<div class="valor"><?=formatarMoeda($res['mensal'])?></div>
</div>
<div class="cartao cartao-metrica">
<div class="rotulo">Assinaturas / mês</div>
<div class="valor"><?=formatarMoeda($res['assinaturas_mensal'])?></div>
</div>
<div class="cartao cartao-metrica">
<div class="rotulo">Assinaturas / ano</div>
<div class="valor"><?=formatarMoeda($res['assinaturas_anual'])?></div>
</div>
</div>
<div class="grade-dupla" style="margin-top:20px">
<div class="cartao">
<h3>Seus compromissos</h3><?php if(empty($recorrenciasAtivas)):?><div class="estado-vazio">
<span>◌</span>
<h3>Nenhuma recorrência</h3>
<p>Cadastre uma conta fixa ou assinatura.</p>
</div><?php else:?><div class="lista-recorrencias"><?php foreach($recorrenciasAtivas as $r):?><div class="recorrencia-linha">
<div>
<strong><?=limpar($r['nome'])?></strong>
<small><?=limpar($r['tipo']==='assinatura'?'Assinatura':'Despesa recorrente')?> · <?=limpar($r['categoria']??'Outros')?> · próxima <?=date('d/m/Y',strtotime($r['proxima_data']))?></small>
</div>
<div class="recorrencia-acoes">
<strong><?=formatarMoeda((float)$r['valor'])?></strong>
<a href="?editar=<?=$r['id']?>">Editar</a>
<form action="../actions/pf.php" method="POST"><?=csrfCampo()?><input type="hidden" name="acao" value="remover_recorrencia">
<input type="hidden" name="id" value="<?=$r['id']?>">
<button class="excluir">Remover</button>
</form>
</div>
</div><?php endforeach;?></div><?php endif;?></div>
<div class="cartao">
<h3><?=$edit?'Editar recorrência':'Nova recorrência'?></h3>
<form action="../actions/pf.php" method="POST" autocomplete="off"><?=csrfCampo()?><input type="hidden" name="acao" value="<?=$edit?'editar_recorrencia':'adicionar_recorrencia'?>"><?php if($edit):?><input type="hidden" name="id" value="<?=$edit['id']?>"><?php endif;?><div class="form-grupo">
<label>Nome</label>
<input name="nome" required value="<?=limpar($edit['nome']??'')?>">
</div>
<div class="form-linha">
<div class="form-grupo">
<label>Tipo</label>
<select name="tipo">
<option value="despesa" <?=($edit['tipo']??'')==='despesa'?'selected':''?>>Conta/Despesa</option>
<option value="assinatura" <?=($edit['tipo']??'')==='assinatura'?'selected':''?>>Assinatura</option>
</select>
</div>
<div class="form-grupo">
<label>Valor</label>
<input type="number" step="0.01" min="0.01" name="valor" required value="<?=limpar((string)($edit['valor']??''))?>">
</div>
</div>
<div class="form-grupo">
<label>Categoria</label>
<select name="categoria_id"><?php foreach($cats as $c):?><option value="<?=$c['id']?>" <?=($edit['categoria_id']??0)==$c['id']?'selected':''?>><?=limpar($c['nome'])?></option><?php endforeach;?></select>
</div>
<div class="form-linha">
<div class="form-grupo">
<label>Frequência</label>
<select name="periodicidade" id="perRec">
<option value="mensal">Mensal</option>
<option value="semanal">Semanal</option>
<option value="quinzenal">A cada 15 dias</option>
<option value="anual">Anual</option>
<option value="outro">A cada X dias</option>
</select>
</div>
<div class="form-grupo">
<label>Dia de vencimento</label>
<input type="number" min="1" max="28" name="dia_vencimento" value="<?=limpar((string)($edit['dia_vencimento']??10))?>">
</div>
</div>
<div class="form-grupo" id="interRec" style="display:none">
<label>Intervalo em dias</label>
<input type="number" min="2" max="365" name="intervalo_dias" value="<?=limpar((string)($edit['intervalo_dias']??30))?>">
</div><?php if(!$edit):?><div class="form-grupo">
<label>Primeiro lançamento</label>
<input type="date" name="proxima_data" value="<?=date('Y-m-d')?>">
</div><?php endif;?><button class="btn btn-primario btn-bloco"><?=$edit?'Salvar alterações':'Adicionar recorrência'?></button>
</form>
</div>
</div>
<script>
const p=document.getElementById('perRec'),i=document.getElementById('interRec');
function s(){
i.style.display=p.value==='outro'?'block':'none'}
p.addEventListener('change',s);
<?php if($edit):?>p.value=<?=json_encode($edit['periodicidade'])?>;
<?php endif;
?>s();
</script>
<?php require_once __DIR__.'/../includes/footer.php';?>
