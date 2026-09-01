<?php
function cpUsuarioAdmin(PDO $pdo, int $uid): bool {
    try{
        $s=$pdo->prepare('SELECT is_admin FROM usuarios WHERE id=:uid');
        $s->execute(['uid'=>$uid]);
        return (bool)$s->fetchColumn();
    }catch(Throwable $e){ return false; }
}

function cpVideosAprender(PDO $pdo, string $perfil, ?string $categoria=null): array {
    $sql='SELECT v.*,COALESCE(p.percentual,0) percentual,COALESCE(p.segundos_assistidos,0) segundos_assistidos,COALESCE(p.concluido,0) concluido
          FROM aprender_videos v
          LEFT JOIN aprender_progresso p ON p.video_id=v.id AND p.usuario_id=:uid
          WHERE v.ativo=1 AND (v.perfil=:perfil OR v.perfil="ambos")';
    $params=['uid'=>usuarioLogadoId(),'perfil'=>$perfil];
    if($categoria!==null&&$categoria!==''){
        $sql.=' AND v.categoria=:categoria';
        $params['categoria']=$categoria;
    }
    $sql.=' ORDER BY v.ordem,v.id DESC';
    $s=$pdo->prepare($sql);$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC);
}

function cpTrilhasAprender(PDO $pdo, string $perfil, int $uid): array {
    $s=$pdo->prepare('SELECT * FROM aprender_trilhas WHERE ativo=1 AND (perfil=:perfil OR perfil="ambos") ORDER BY ordem,id');
    $s->execute(['perfil'=>$perfil]);$trilhas=$s->fetchAll(PDO::FETCH_ASSOC);
    foreach($trilhas as &$t){
        $v=$pdo->prepare('SELECT v.id,v.titulo,v.youtube_video_id,tv.ordem,COALESCE(p.percentual,0) percentual,COALESCE(p.concluido,0) concluido
                          FROM aprender_trilha_videos tv
                          JOIN aprender_videos v ON v.id=tv.video_id AND v.ativo=1
                          LEFT JOIN aprender_progresso p ON p.video_id=v.id AND p.usuario_id=:uid
                          WHERE tv.trilha_id=:tid ORDER BY tv.ordem,v.ordem,v.id');
        $v->execute(['uid'=>$uid,'tid'=>$t['id']]);$t['videos']=$v->fetchAll(PDO::FETCH_ASSOC);
        $total=count($t['videos']);$concluidos=0;foreach($t['videos'] as $x)if(!empty($x['concluido']))$concluidos++;
        $t['total']=$total;$t['concluidos']=$concluidos;$t['percentual']=$total?round($concluidos/$total*100):0;
    }
    unset($t);return $trilhas;
}

function cpVideoRelacionado(PDO $pdo, string $perfil, string $texto): ?array {
    try{
        $tokens=preg_split('/\s+/',mb_strtolower($texto));
        $stop=['como','quero','sobre','meus','minha','minhas','meu','estou','pode','qual','isso','isto','este','esta','essa','para','mais','onde','cashpilot','dados','atual','atuais','explique','analise','ajuda','ajudar'];
        $tokens=array_map(fn($x)=>preg_replace('/[^a-z0-9áàâãéèêíìîóòôõúùûç_-]/u','',$x),$tokens);
        $tokens=array_values(array_filter(array_unique($tokens),fn($x)=>mb_strlen($x)>=4 && !in_array($x,$stop,true)));
        if(!$tokens)return null;
        $parts=[];$params=['perfil'=>$perfil];
        foreach(array_slice($tokens,0,8) as $idx=>$tok){
            $k='t'.$idx;$parts[]="LOWER(CONCAT_WS(' ',titulo,categoria,tags)) LIKE :$k";$params[$k]='%'.$tok.'%';
        }
        $sql='SELECT id,titulo,descricao,youtube_video_id,categoria FROM aprender_videos WHERE ativo=1 AND (perfil=:perfil OR perfil="ambos") AND ('.implode(' OR ',$parts).') ORDER BY ordem,id DESC LIMIT 1';
        $s=$pdo->prepare($sql);$s->execute($params);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null;
    }catch(Throwable $e){return null;}
}
?>
