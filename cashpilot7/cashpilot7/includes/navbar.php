<?php
$paginaAtual=basename($_SERVER['PHP_SELF']);$empreendedor=usuarioLogadoTipo()==='mei';
$itensMenu=['dashboard.php'=>['label'=>'Dashboard','icone'=>'grid'],'receitas.php'=>['label'=>$empreendedor?'Entradas':'Receitas','icone'=>'arrow-up'],'despesas.php'=>['label'=>'Despesas','icone'=>'arrow-down']];
if($empreendedor)$itensMenu['negocio.php']=['label'=>'Negócio','icone'=>'briefcase'];else $itensMenu['metas.php']=['label'=>'Metas','icone'=>'target'];
$itensMenu['relatorios.php']=['label'=>'Relatórios','icone'=>'bar-chart'];
if(in_array($paginaAtual,['importar_revisao.php'],true))$paginaAtual='importar.php';
$avatar=$_SESSION['usuario_avatar']??'';$nomeUsuario=usuarioLogadoNome();$iniciais='';foreach(preg_split('/\s+/',trim($nomeUsuario)) as $parte){if($parte!=='')$iniciais.=mb_strtoupper(mb_substr($parte,0,1));if(mb_strlen($iniciais)>=2)break;}
?>
<div class="layout">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-topo">
        <a href="dashboard.php" class="logo-wrap" aria-label="CashPilot"><img src="../assets/img/logo-cashpilot-transparente.png" class="brand-logo-full" alt="CashPilot"><img src="../assets/img/logo-cashpilot-simbolo.png" class="brand-logo-mini" alt="CashPilot"></a>
        <button type="button" class="sidebar-toggle" id="sidebarToggle" title="Recolher menu" aria-label="Recolher menu"><span></span></button>
    </div>
    <nav class="sidebar-menu"><?php foreach($itensMenu as $arquivo=>$item):?><a href="<?=$arquivo?>" class="menu-item <?=$paginaAtual===$arquivo?'ativo':''?>" title="<?=limpar($item['label'])?>"><span class="menu-icone icone-<?=$item['icone']?>"></span><span class="menu-label"><?=limpar($item['label'])?></span></a><?php endforeach;?></nav>
    <div class="sidebar-rodape"><a href="perfil.php" class="menu-item perfil-menu <?=$paginaAtual==='perfil.php'?'ativo':''?>" title="Meu perfil"><?php if($avatar):?><img class="avatar avatar-mini" src="../<?=limpar($avatar)?>" alt="Foto de perfil"><?php else:?><span class="avatar avatar-mini avatar-iniciais"><?=limpar($iniciais?:'CP')?></span><?php endif;?><span class="menu-label"><?=limpar($nomeUsuario)?></span></a></div>
</aside>
<main class="conteudo">
<script>
document.addEventListener('DOMContentLoaded',()=>{const sidebar=document.getElementById('sidebar'),toggle=document.getElementById('sidebarToggle');if(!sidebar||!toggle)return;const chave='cashpilot_sidebar_recolhida';if(localStorage.getItem(chave)==='1')sidebar.classList.add('recolhida');const sync=()=>{toggle.title=sidebar.classList.contains('recolhida')?'Expandir menu':'Recolher menu';toggle.setAttribute('aria-label',toggle.title);};toggle.addEventListener('click',()=>{sidebar.classList.toggle('recolhida');localStorage.setItem(chave,sidebar.classList.contains('recolhida')?'1':'0');sync();});sync();});
</script>
