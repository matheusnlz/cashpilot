<?php
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../database/conexao.php';
require_once __DIR__.'/../includes/aprender_helper.php';
exigirLogin();

$tituloPagina='Aula';
$pdo=conectar();
$uid=(int)usuarioLogadoId();
$perfil=usuarioLogadoTipo()==='mei'?'mei':'pessoa_fisica';
$id=(int)($_GET['id']??0);

$s=$pdo->prepare('SELECT v.*,COALESCE(p.percentual,0) percentual,COALESCE(p.segundos_assistidos,0) segundos_assistidos FROM aprender_videos v LEFT JOIN aprender_progresso p ON p.video_id=v.id AND p.usuario_id=:uid WHERE v.id=:id AND v.ativo=1 AND (v.perfil=:perfil OR v.perfil="ambos")');
$s->execute(['uid'=>$uid,'id'=>$id,'perfil'=>$perfil]);
$v=$s->fetch(PDO::FETCH_ASSOC);
if(!$v){
header('Location: aprender.php');
exit;
}

$s=$pdo->prepare('SELECT t.id,t.titulo,tv.ordem FROM aprender_trilha_videos tv JOIN aprender_trilhas t ON t.id=tv.trilha_id WHERE tv.video_id=:id AND t.ativo=1 LIMIT 1');
$s->execute(['id'=>$id]);
$trilha=$s->fetch(PDO::FETCH_ASSOC);

$trilhaVideos=[];
$anterior=null;
$proximo=null;

if($trilha){

    $tv=$pdo->prepare('SELECT v.id,v.titulo,tv.ordem FROM aprender_trilha_videos tv JOIN aprender_videos v ON v.id=tv.video_id AND v.ativo=1 WHERE tv.trilha_id=:tid ORDER BY tv.ordem,v.ordem,v.id');

    $tv->execute(['tid'=>$trilha['id']]);
$trilhaVideos=$tv->fetchAll(PDO::FETCH_ASSOC);

    foreach($trilhaVideos as $idx=>$item){
if((int)$item['id']===$id){
$anterior=$trilhaVideos[$idx-1]??null;
$proximo=$trilhaVideos[$idx+1]??null;
break;
}
}
}

$rel=cpVideoRelacionado($pdo,$perfil,($v['tags']??'').' '.($v['categoria']??''));

require_once __DIR__.'/../includes/header.php';
require_once __DIR__.'/../includes/navbar.php';?>
<div class="page-head">
<div>
<a class="back-link" href="aprender.php">← Voltar para Aprender</a>
<span class="eyebrow"><?=limpar($v['categoria'])?></span>
<h1><?=limpar($v['titulo'])?></h1>
<p><?=limpar($v['descricao']??'')?></p>
</div>
</div>
<div class="lesson-layout">
<section class="lesson-main">
<div class="video-shell">
<div id="ytPlayer">
</div>
</div>
<div class="lesson-progress-card cartao">
<div>
<strong>Seu progresso</strong>
<small id="lessonProgressText"><?=number_format((float)$v['percentual'],0)?>% assistido</small>
</div>
<div class="learning-progress">
<span id="lessonProgressBar" style="width:<?=min(100,(float)$v['percentual'])?>%">
</span>
</div>
</div>
  <?php if($trilha):?><nav class="lesson-nav"><?php if($anterior):?><a href="aula.php?id=<?=$anterior['id']?>">← <?=limpar($anterior['titulo'])?></a><?php else:?><span>
</span><?php endif;?><?php if($proximo):?><a href="aula.php?id=<?=$proximo['id']?>"><?=limpar($proximo['titulo'])?> →</a><?php endif;?></nav><?php endif;?>
 </section>
<aside class="lesson-aside">
   <?php if($trilha):?><div class="cartao">
<span class="eyebrow">TRILHA</span>
<h3><?=limpar($trilha['titulo'])?></h3>
<p class="secao-ajuda">Esta aula faz parte de uma sequência organizada.</p>
</div><?php endif;?>
   <div class="cartao">
<span class="eyebrow">COPILOTO</span>
<h3>Converse sobre a aula</h3>
<p class="secao-ajuda">Use o Copiloto se quiser relacionar este assunto aos seus próprios dados.</p>
<button class="btn btn-secundario btn-bloco" data-copiloto-pergunta="Estou assistindo à aula <?=limpar($v['titulo'])?>. Explique como esse assunto se relaciona com meus dados atuais no CashPilot.">✦ Perguntar ao Copiloto</button>
</div>
</aside>
</div>
<script src="https://www.youtube.com/iframe_api">

</script>
<script>
let player,timer;
const videoId=<?=json_encode($v['youtube_video_id'])?>,csrf=<?=json_encode(csrfToken())?>,videoDbId=<?=$v['id']?>,startAt=<?=(int)$v['segundos_assistidos']?>;

function onYouTubeIframeAPIReady(){
player=new YT.Player('ytPlayer',{videoId,playerVars:{rel:0,modestbranding:1,start:startAt},events:{onStateChange:onPlayerStateChange,onReady:()=>{}}});
}

function saveProgress(){
if(!player||typeof player.getCurrentTime!=='function')return;
const segundos=Math.floor(player.getCurrentTime()||0),duracao=Math.floor(player.getDuration()||0);
if(duracao<=0)return;
const fd=new FormData();
fd.append('acao','progresso');
fd.append('csrf_token',csrf);
fd.append('video_id',videoDbId);
fd.append('segundos',segundos);
fd.append('duracao',duracao);
fetch('../actions/aprender.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{if(d.ok){document.getElementById('lessonProgressBar').style.width=Math.min(100,d.percentual)+'%';document.getElementById('lessonProgressText').textContent=Math.round(d.percentual)+'% assistido';}}).catch(()=>{});
}

function onPlayerStateChange(e){
clearInterval(timer);
if(e.data===YT.PlayerState.PLAYING)timer=setInterval(saveProgress,10000);
if(e.data===YT.PlayerState.PAUSED||e.data===YT.PlayerState.ENDED)saveProgress();
}

window.addEventListener('beforeunload',saveProgress);
</script>
<?php require_once __DIR__.'/../includes/footer.php';?>
