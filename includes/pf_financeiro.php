<?php
function cpCategoriaPF(PDO $pdo, int $uid, string $nome='Outros'): ?int {
    $s=$pdo->prepare('SELECT id FROM categorias WHERE usuario_id=:uid AND tipo="despesa" AND nome=:nome LIMIT 1');
    $s->execute(['uid'=>$uid,'nome'=>$nome]);
    $id=$s->fetchColumn();
    if($id!==false) return (int)$id;
    $s=$pdo->prepare('SELECT id FROM categorias WHERE usuario_id=:uid AND tipo="despesa" ORDER BY id LIMIT 1');
    $s->execute(['uid'=>$uid]);
    $id=$s->fetchColumn();
    return $id!==false?(int)$id:null;
}

function cpProximaDataRecorrencia(string $base, string $periodicidade, ?int $intervalo=null): string {
    $d=new DateTime($base);
    match($periodicidade){
        'semanal'=>$d->modify('+7 days'),
        'quinzenal'=>$d->modify('+15 days'),
        'anual'=>$d->modify('+1 year'),
        'outro'=>$d->modify('+'.max(2,(int)($intervalo?:30)).' days'),
        default=>$d->modify('+1 month'),
    };
    return $d->format('Y-m-d');
}

function cpSincronizarRecorrenciasPF(PDO $pdo, int $uid): void {
    if(usuarioLogadoTipo()!=='pessoa_fisica') return;
    try{
        $s=$pdo->prepare('SELECT * FROM recorrencias_pf WHERE usuario_id=:uid AND ativo=1 ORDER BY id');
        $s->execute(['uid'=>$uid]);
        $hoje=date('Y-m-d');
        foreach($s->fetchAll() as $r){
            $proxima=$r['proxima_data'] ?: date('Y-m-').str_pad((string)max(1,min(28,(int)$r['dia_vencimento'])),2,'0',STR_PAD_LEFT);
            if($proxima>$hoje) continue;
            $seguranca=0;
            while($proxima<=$hoje && $seguranca<24){
                $dup=$pdo->prepare('SELECT id FROM despesas WHERE usuario_id=:uid AND origem_tipo="recorrencia_pf" AND origem_id=:oid AND data_despesa=:data LIMIT 1');
                $dup->execute(['uid'=>$uid,'oid'=>$r['id'],'data'=>$proxima]);
                if(!$dup->fetchColumn()){
                    $cat=$r['categoria_id'] ?: cpCategoriaPF($pdo,$uid,$r['tipo']==='assinatura'?'Assinaturas':'Outros');
                    $i=$pdo->prepare('INSERT INTO despesas (usuario_id,categoria_id,valor,descricao,data_despesa,origem_tipo,origem_id,origem_referencia) VALUES (:uid,:cat,:valor,:descricao,:data,"recorrencia_pf",:oid,:ref)');
                    $i->execute(['uid'=>$uid,'cat'=>$cat,'valor'=>$r['valor'],'descricao'=>($r['tipo']==='assinatura'?'Assinatura · ':'Recorrência · ').$r['nome'],'data'=>$proxima,'oid'=>$r['id'],'ref'=>$proxima]);
                }
                $proxima=cpProximaDataRecorrencia($proxima,$r['periodicidade'],$r['intervalo_dias']!==null?(int)$r['intervalo_dias']:null);
                $seguranca++;
            }
            $u=$pdo->prepare('UPDATE recorrencias_pf SET proxima_data=:p WHERE id=:id AND usuario_id=:uid');
            $u->execute(['p'=>$proxima,'id'=>$r['id'],'uid'=>$uid]);
        }
    }catch(Throwable $e){ error_log('CashPilot/RecorrenciasPF: '.$e->getMessage()); }
}

function cpResumoRecorrenciasPF(PDO $pdo,int $uid):array{
    $out=['mensal'=>0.0,'assinaturas_mensal'=>0.0,'assinaturas_anual'=>0.0,'quantidade'=>0];
    try{
        $s=$pdo->prepare('SELECT valor,tipo,periodicidade,intervalo_dias FROM recorrencias_pf WHERE usuario_id=:uid AND ativo=1');
        $s->execute(['uid'=>$uid]);
        foreach($s->fetchAll() as $r){
            $mult=match($r['periodicidade']){
                'semanal'=>4.33,'quinzenal'=>2.0,'anual'=>1/12,
                'outro'=>30/max(2,(int)($r['intervalo_dias']?:30)),
                default=>1.0
            };
            $m=(float)$r['valor']*$mult;
            $out['mensal']+=$m;$out['quantidade']++;
            if($r['tipo']==='assinatura')$out['assinaturas_mensal']+=$m;
        }
        $out['assinaturas_anual']=$out['assinaturas_mensal']*12;
    }catch(Throwable $e){}
    return $out;
}
?>
