<?php
require_once __DIR__.'/negocio_financeiro.php';

function cpTotalPeriodo(PDO $pdo,string $tabela,string $campo,int $uid,string $inicio,string $fim):float {

        $stmt=$pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM {$tabela} WHERE usuario_id=:uid AND {$campo} BETWEEN :i AND :f");

        $stmt->execute(['uid'=>$uid,'i'=>$inicio,'f'=>$fim]);

        return (float)$stmt->fetchColumn();

}

function cpDadosMensais(PDO $pdo,int $uid,int $meses=12):array {

        $dados=[];

    for($i=$meses-1; $i>=0; $i--) {

        $ref=new DateTime("first day of -{$i} month");

        $dados[]=['mes'=>$ref->format('Y-m'),'receitas'=>cpTotalPeriodo($pdo,'receitas','data_receita',$uid,$ref->format('Y-m-01'),$ref->format('Y-m-t')),'despesas'=>cpTotalPeriodo($pdo,'despesas','data_despesa',$uid,$ref->format('Y-m-01'),$ref->format('Y-m-t'))];

    }

    return $dados;

}

function cpDadosDiariosMes(PDO $pdo,int $uid):array {

        $inicio=date('Y-m-01');

    $fim=date('Y-m-t');

    $dias=(int)date('t');

    $r=[];

    $d=[];

        $s=$pdo->prepare('SELECT DAY(data_receita) dia,SUM(valor) total FROM receitas WHERE usuario_id=:uid AND data_receita BETWEEN :i AND :f GROUP BY DAY(data_receita)');

    $s->execute(['uid'=>$uid,'i'=>$inicio,'f'=>$fim]);

    foreach($s->fetchAll() as $x)$r[(int)$x['dia']]=(float)$x['total'];

        $s=$pdo->prepare('SELECT DAY(data_despesa) dia,SUM(valor) total FROM despesas WHERE usuario_id=:uid AND data_despesa BETWEEN :i AND :f GROUP BY DAY(data_despesa)');

    $s->execute(['uid'=>$uid,'i'=>$inicio,'f'=>$fim]);

    foreach($s->fetchAll() as $x)$d[(int)$x['dia']]=(float)$x['total'];

        $out=[];

    for($dia=1; $dia<=$dias; $dia++)$out[]=['dia'=>$dia,'receitas'=>$r[$dia]??0,'despesas'=>$d[$dia]??0];

    return $out;

}

function cpSaldoGeral(PDO $pdo,int $uid):float {

        $s=$pdo->prepare('SELECT (SELECT COALESCE(SUM(valor),0) FROM receitas WHERE usuario_id=:a)-(SELECT COALESCE(SUM(valor),0) FROM despesas WHERE usuario_id=:b)');

    $s->execute(['a'=>$uid,'b'=>$uid]);

    return(float)$s->fetchColumn();

}

function cpCategoriasDespesas(PDO $pdo,int $uid,string $i,string $f,int $limite=5):array {

        $s=$pdo->prepare("SELECT COALESCE(c.nome,'Outros') categoria,SUM(d.valor) total FROM despesas d LEFT JOIN categorias c ON c.id=d.categoria_id WHERE d.usuario_id=:uid AND d.data_despesa BETWEEN :i AND :f GROUP BY categoria ORDER BY total DESC LIMIT {$limite}");

    $s->execute(['uid'=>$uid,'i'=>$i,'f'=>$f]);

    return$s->fetchAll();

}

function cpExecutarPython(string $script,array $args):?array {

        $pasta=escapeshellarg(__DIR__.'/../python');

    $python=PHP_OS_FAMILY==='Windows'?'python':'python3';

    $cmd='cd '.$pasta.' && '.$python.' '.escapeshellarg($script);

    foreach($args as $arg)$cmd.=' '.escapeshellarg((string)$arg);

    if(PHP_OS_FAMILY!=='Windows')$cmd.=' 2>/dev/null';

    $saida=@shell_exec($cmd);

    if(!$saida)return null;

    $dados=json_decode($saida,true);

    return is_array($dados)?$dados:null;

}

function cpRadarBase(array $insights,float $receitas,float $despesas,float $despesasAnterior):array {

        $radar=[];

    foreach($insights as $item) {

        $tipo=$item['tipo']??'info';

        $nivel=$tipo==='alerta'?'vermelho':($tipo==='atencao'?'amarelo':'verde');

        $radar[]=['nivel'=>$nivel,'mensagem'=>$item['mensagem']??''];

    }

        if($despesasAnterior>0&&$despesas>$despesasAnterior*1.15)$radar[]=['nivel'=>'amarelo','mensagem'=>'Suas despesas cresceram mais de 15% em relação ao mês anterior.'];

        $resultado=$receitas-$despesas;

    if($resultado<0)$radar[]=['nivel'=>'vermelho','mensagem'=>'O resultado do mês está negativo. Reveja os maiores gastos antes de assumir novos compromissos.'];

    elseif($receitas>0&&$resultado/$receitas>=.2)$radar[]=['nivel'=>'verde','mensagem'=>'O mês mantém uma margem positiva acima de 20% das receitas.'];

        return $radar;

}
