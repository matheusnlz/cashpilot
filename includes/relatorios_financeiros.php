<?php
function cpRelPeriodo(array $get): array {

        $preset = $get['preset'] ?? 'mes_atual';

        $hoje = new DateTimeImmutable('today');

        $fim = $hoje;

        switch ($preset) {

                case 'mes_anterior': $base=$hoje->modify('first day of last month');

         $inicio=$base;

         $fim=$base->modify('last day of this month');

         break;

                case '3m': $inicio=$hoje->modify('first day of -2 months');

         break;

                case '6m': $inicio=$hoje->modify('first day of -5 months');

         break;

                case '12m': $inicio=$hoje->modify('first day of -11 months');

         break;

                case 'personalizado':
                    $di=$get['data_inicio']??'';

         $df=$get['data_fim']??'';

                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/',$di) && preg_match('/^\d{4}-\d{2}-\d{2}$/',$df)) {

             $inicio=new DateTimeImmutable($di);

             $fim=new DateTimeImmutable($df);

             if($inicio>$fim) {

                [$inicio,$fim]=[$fim,$inicio];

            }

             break;

        }

                    $preset='mes_atual';

                default: $inicio=$hoje->modify('first day of this month');

         $fim=$hoje->modify('last day of this month');

    }

        return ['preset'=>$preset,'inicio'=>$inicio->format('Y-m-d'),'fim'=>$fim->format('Y-m-d')];

}

function cpRelSoma(PDO $pdo,string $tabela,string $campo,int $uid,string $inicio,string $fim,string $status=''): float {

        $sql="SELECT COALESCE(SUM(valor),0) FROM {$tabela
}
 WHERE usuario_id=:uid AND {$campo
}
 BETWEEN :i AND :f";

        if($status!=='') $sql.=' AND status=:status';

        $s=$pdo->prepare($sql);

     $p=['uid'=>$uid,'i'=>$inicio,'f'=>$fim];

     if($status!=='')$p['status']=$status;

     $s->execute($p);

     return (float)$s->fetchColumn();

}

function cpRelCategorias(PDO $pdo,string $tabela,string $campo,int $uid,string $inicio,string $fim): array {

        $s=$pdo->prepare("SELECT COALESCE(c.nome,'Outros') categoria, COALESCE(c.cor,'#6B7280') cor, COUNT(*) quantidade, COALESCE(SUM(x.valor),0) total FROM {$tabela} x LEFT JOIN categorias c ON c.id=x.categoria_id WHERE x.usuario_id=:uid AND x.{$campo} BETWEEN :i AND :f GROUP BY c.id,c.nome,c.cor ORDER BY total DESC");

        $s->execute(['uid'=>$uid,'i'=>$inicio,'f'=>$fim]);

     return $s->fetchAll(PDO::FETCH_ASSOC);

}

function cpRelEvolucao(PDO $pdo,int $uid,string $inicio,string $fim): array {

        $ini=new DateTimeImmutable(substr($inicio,0,7).'-01');

     $end=new DateTimeImmutable(substr($fim,0,7).'-01');

     $out=[];

        for($m=$ini; $m<=$end; $m=$m->modify('+1 month')) {

                $i=max($inicio,$m->format('Y-m-01'));

         $f=min($fim,$m->modify('last day of this month')->format('Y-m-d'));

                $r=cpRelSoma($pdo,'receitas','data_receita',$uid,$i,$f);

         $d=cpRelSoma($pdo,'despesas','data_despesa',$uid,$i,$f);

                $out[]=['mes'=>$m->format('Y-m'),'receitas'=>$r,'despesas'=>$d,'resultado'=>$r-$d];

    }

     return $out;

}

function cpRelComparacao(PDO $pdo,int $uid,string $inicio,string $fim): array {

        $a=new DateTimeImmutable($inicio);

    $b=new DateTimeImmutable($fim);

    $dias=(int)$a->diff($b)->days+1;

        $fimAnt=$a->modify('-1 day');

    $iniAnt=$fimAnt->modify('-'.($dias-1).' days');

        $atR=cpRelSoma($pdo,'receitas','data_receita',$uid,$inicio,$fim);

    $atD=cpRelSoma($pdo,'despesas','data_despesa',$uid,$inicio,$fim);

        $anR=cpRelSoma($pdo,'receitas','data_receita',$uid,$iniAnt->format('Y-m-d'),$fimAnt->format('Y-m-d'));

    $anD=cpRelSoma($pdo,'despesas','data_despesa',$uid,$iniAnt->format('Y-m-d'),$fimAnt->format('Y-m-d'));

        return ['atual'=>['receitas'=>$atR,'despesas'=>$atD,'resultado'=>$atR-$atD],'anterior'=>['receitas'=>$anR,'despesas'=>$anD,'resultado'=>$anR-$anD],'inicio_anterior'=>$iniAnt->format('Y-m-d'),'fim_anterior'=>$fimAnt->format('Y-m-d')];

}

function cpRelResumo(PDO $pdo,int $uid,string $inicio,string $fim,bool $mei): array {

        $receitas=cpRelSoma($pdo,'receitas','data_receita',$uid,$inicio,$fim);

    $despesas=cpRelSoma($pdo,'despesas','data_despesa',$uid,$inicio,$fim);

    $resultado=$receitas-$despesas;

        $gastos=cpRelCategorias($pdo,'despesas','data_despesa',$uid,$inicio,$fim);

    $entradas=cpRelCategorias($pdo,'receitas','data_receita',$uid,$inicio,$fim);

        $tx=$receitas>0?($resultado/$receitas*100):0;

        $extras=[];

        if($mei) {

                try {

            $s=$pdo->prepare('SELECT COALESCE(SUM(salario_base+outros_custos),0) FROM funcionarios WHERE usuario_id=:uid AND ativo=1');

            $s->execute(['uid'=>$uid]);

            $extras['custo_equipe']=(float)$s->fetchColumn();

        }
        catch(Throwable $e) {

            $extras['custo_equipe']=0;

        }

                try {

            $s=$pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM custos_negocio WHERE usuario_id=:uid AND ativo=1");

            $s->execute(['uid'=>$uid]);

            $extras['custos_cadastrados']=(float)$s->fetchColumn();

        }
        catch(Throwable $e) {

            $extras['custos_cadastrados']=0;

        }

    }
    else {

                try {

            $s=$pdo->prepare('SELECT COALESCE(SUM(valor_atual),0),COALESCE(SUM(valor_aplicado),0) FROM investimentos WHERE usuario_id=:uid AND ativo=1');

            $s->execute(['uid'=>$uid]);

            $x=$s->fetch(PDO::FETCH_NUM);

            $extras['investimentos_atual']=(float)($x[0]??0);

            $extras['investimentos_aplicado']=(float)($x[1]??0);

        }
        catch(Throwable $e) {

            $extras['investimentos_atual']=0;

            $extras['investimentos_aplicado']=0;

        }

                try {

            $s=$pdo->prepare('SELECT COUNT(*),COALESCE(SUM(valor_atual),0),COALESCE(SUM(valor_meta),0) FROM metas WHERE usuario_id=:uid AND concluida=0');

            $s->execute(['uid'=>$uid]);

            $x=$s->fetch(PDO::FETCH_NUM);

            $extras['metas_ativas']=(int)($x[0]??0);

            $extras['metas_atual']=(float)($x[1]??0);

            $extras['metas_total']=(float)($x[2]??0);

        }
        catch(Throwable $e) {

            $extras['metas_ativas']=0;

            $extras['metas_atual']=0;

            $extras['metas_total']=0;

        }

    }

        return compact('receitas','despesas','resultado','gastos','entradas','tx','extras');

}

function cpRelVariacao(float $atual,float $anterior): ?float {

     return abs($anterior)>0.00001 ? (($atual-$anterior)/abs($anterior))*100 : null;

}

function cpRelNomePeriodo(string $inicio,string $fim): string {

     return date('d/m/Y',strtotime($inicio)).' a '.date('d/m/Y',strtotime($fim));

}
