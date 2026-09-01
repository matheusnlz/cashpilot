<?php
function cpIFTotal(PDO $pdo,string $tabela,string $campo,int $uid,string $inicio,string $fim):float{

    $s=$pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM {$tabela} WHERE usuario_id=:uid AND {$campo} BETWEEN :i AND :f");

    $s->execute(['uid'=>$uid,'i'=>$inicio,'f'=>$fim]);
return(float)$s->fetchColumn();
}


function cpIFMediaDespesasEssenciais(PDO $pdo,int $uid,int $meses=3):float{

    $inicio=date('Y-m-01',strtotime('-'.max(0,$meses-1).' months'));
$fim=date('Y-m-t');

    $s=$pdo->prepare("SELECT COALESCE(SUM(d.valor),0) total
      FROM despesas d LEFT JOIN categorias c ON c.id=d.categoria_id
      WHERE d.usuario_id=:uid AND d.data_despesa BETWEEN :i AND :f
      AND (LOWER(COALESCE(c.nome,'')) REGEXP 'moradia|aliment|saúde|saude|transporte|educa|conta|essencial'
           OR d.origem_tipo IN ('recorrencia_pf'))");

    $s->execute(['uid'=>$uid,'i'=>$inicio,'f'=>$fim]);
$ess=(float)$s->fetchColumn();

    if($ess<=0){
$ess=cpIFTotal($pdo,'despesas','data_despesa',$uid,$inicio,$fim);
}

    return $ess/max(1,$meses);
}


function cpReservaResumo(PDO $pdo,int $uid):array{

    $s=$pdo->prepare('SELECT valor_atual,meses_objetivo FROM reserva_emergencia WHERE usuario_id=:uid');
$s->execute(['uid'=>$uid]);
$r=$s->fetch(PDO::FETCH_ASSOC)?:['valor_atual'=>0,'meses_objetivo'=>6];

    $media=cpIFMediaDespesasEssenciais($pdo,$uid,3);
$objetivo=$media*(int)$r['meses_objetivo'];
$atual=(float)$r['valor_atual'];

    return ['valor_atual'=>$atual,'meses_objetivo'=>(int)$r['meses_objetivo'],'gasto_essencial_medio'=>$media,'valor_objetivo'=>$objetivo,'cobertura_meses'=>$media>0?$atual/$media:0,'percentual'=>$objetivo>0?min(100,$atual/$objetivo*100):0];
}


function cpCashScore(PDO $pdo,int $uid):array{

    $score=50;
$fatores=[];
$i=date('Y-m-01');
$f=date('Y-m-t');

    $r=cpIFTotal($pdo,'receitas','data_receita',$uid,$i,$f);
$d=cpIFTotal($pdo,'despesas','data_despesa',$uid,$i,$f);
$resultado=$r-$d;

    if($r>0){
$taxa=$resultado/$r;
if($taxa>=.20){
$score+=15;
$fatores[]=['positivo','Resultado saudável','Seu resultado representa pelo menos 20% das receitas.',15];
}
elseif($taxa>=0){
$score+=8;
$fatores[]=['positivo','Mês no positivo','Você está gastando menos do que recebeu.',8];
}
else{
$score-=14;
$fatores[]=['negativo','Resultado negativo','As despesas estão acima das receitas neste mês.',-14];
}
}
else{
$fatores[]=['neutro','Receitas insuficientes','Cadastre receitas para o score ficar mais preciso.',0];
}


    $s=$pdo->prepare('SELECT c.limite_mensal,COALESCE(SUM(d.valor),0) gasto FROM categorias c LEFT JOIN despesas d ON d.categoria_id=c.id AND d.usuario_id=:u1 AND d.data_despesa BETWEEN :i AND :f WHERE c.usuario_id=:u2 AND c.tipo="despesa" AND c.limite_mensal IS NOT NULL AND c.limite_mensal>0 GROUP BY c.id,c.limite_mensal');

    $s->execute(['u1'=>$uid,'i'=>$i,'f'=>$f,'u2'=>$uid]);
$orc=$s->fetchAll(PDO::FETCH_ASSOC);

    if($orc){
$ok=0;
foreach($orc as $o)if((float)$o['gasto']<=(float)$o['limite_mensal'])$ok++;
$tax=$ok/count($orc);
$pts=(int)round(($tax-.5)*20);
$pts=max(-10,min(10,$pts));
$score+=$pts;
$fatores[]=[($pts>=0?'positivo':'negativo'),'Orçamentos',"$ok de ".count($orc).' categorias estão dentro do limite.',$pts];
}


    try{

      $s=$pdo->prepare('SELECT valor,periodicidade,intervalo_dias FROM recorrencias_pf WHERE usuario_id=:uid AND ativo=1');
$s->execute(['uid'=>$uid]);
$mensal=0;
foreach($s->fetchAll() as $x){
$m=match($x['periodicidade']){
'semanal'=>4.33,'quinzenal'=>2,'anual'=>1/12,'outro'=>30/max(2,(int)($x['intervalo_dias']?:30)),default=>1
}
;
$mensal+=(float)$x['valor']*$m;
}
if($r>0){
$tax=$mensal/$r;
if($tax<=.20){
$score+=7;
$fatores[]=['positivo','Recorrências controladas','Compromissos recorrentes estão abaixo de 20% das receitas.',7];
}
elseif($tax>.40){
$score-=8;
$fatores[]=['negativo','Recorrências pesadas','Compromissos recorrentes passam de 40% das receitas.',-8];
}
}
}
catch(Throwable $e){
}


    $res=cpReservaResumo($pdo,$uid);

    if($res['cobertura_meses']>=6){
$score+=13;
$fatores[]=['positivo','Reserva forte','Sua reserva cobre pelo menos 6 meses de gastos essenciais.',13];
}

    elseif($res['cobertura_meses']>=3){
$score+=9;
$fatores[]=['positivo','Reserva em construção','Sua reserva já cobre pelo menos 3 meses.',9];
}

    elseif($res['valor_atual']>0){
$score+=3;
$fatores[]=['neutro','Reserva inicial','Você já começou sua reserva de emergência.',3];
}

    else{
$score-=5;
$fatores[]=['negativo','Sem reserva registrada','A reserva de emergência ainda não foi iniciada.',-5];
}


    try{

      $s=$pdo->prepare('SELECT COALESCE(SUM(valor_atual),0) FROM investimentos WHERE usuario_id=:uid AND ativo=1');

      $s->execute(['uid'=>$uid]);

      $investido=(float)$s->fetchColumn();

      if($investido>0){

        if($res['cobertura_meses']>=1){

          $score+=3;

          $fatores[]=['positivo','Investimentos com contexto','Você possui investimentos e já registra alguma cobertura de emergência.',3];
}
else{

          $fatores[]=['neutro','Investimentos antes da proteção','Há investimentos registrados, mas a reserva ainda cobre menos de 1 mês. O CashPilot não aumenta a nota apenas por investir.',0];
}
}
}
catch(Throwable $e){
}


    try{
$s=$pdo->prepare('SELECT COUNT(*) FROM metas WHERE usuario_id=:uid AND concluida=0 AND valor_atual>0');
$s->execute(['uid'=>$uid]);
if((int)$s->fetchColumn()>0){
$score+=5;
$fatores[]=['positivo','Metas em andamento','Você já direciona dinheiro para objetivos.',5];
}
}
catch(Throwable $e){
}


    $score=max(0,min(100,$score));

    $nivel=$score>=85?'Excelente':($score>=70?'Bom':($score>=50?'Em atenção':'Crítico'));

    return ['score'=>$score,'nivel'=>$nivel,'fatores'=>$fatores,'receitas_mes'=>$r,'despesas_mes'=>$d,'resultado_mes'=>$resultado,'reserva'=>$res];
}


function cpDesafiosEconomia(PDO $pdo,int $uid):array{

    $s=$pdo->prepare('SELECT * FROM desafios_economia WHERE usuario_id=:uid AND status<>"cancelado" ORDER BY status="ativo" DESC,data_fim,id DESC');
$s->execute(['uid'=>$uid]);
return$s->fetchAll(PDO::FETCH_ASSOC);
}


function cpDesempenhoNegocio(PDO $pdo,int $uid,string $inicio,string $fim):array{

    $out=['resumo'=>['vendas'=>0,'faturamento'=>0.0,'custo'=>0.0,'lucro'=>0.0,'margem'=>0.0,'ticket'=>0.0],'itens'=>[],'fornecedores'=>[],'custos'=>['fixos'=>0.0,'variaveis'=>0.0],'previsao'=>[]];

    try{

      $s=$pdo->prepare('SELECT COUNT(*) vendas,COALESCE(SUM(valor_bruto),0) fat,COALESCE(SUM(custo_total),0) custo FROM vendas WHERE usuario_id=:uid AND data_venda BETWEEN :i AND :f');
$s->execute(['uid'=>$uid,'i'=>$inicio,'f'=>$fim]);
$r=$s->fetch(PDO::FETCH_ASSOC);
$out['resumo']['vendas']=(int)$r['vendas'];
$out['resumo']['faturamento']=(float)$r['fat'];
$out['resumo']['custo']=(float)$r['custo'];
$out['resumo']['lucro']=$out['resumo']['faturamento']-$out['resumo']['custo'];
$out['resumo']['margem']=$out['resumo']['faturamento']>0?$out['resumo']['lucro']/$out['resumo']['faturamento']*100:0;
$out['resumo']['ticket']=$out['resumo']['vendas']>0?$out['resumo']['faturamento']/$out['resumo']['vendas']:0;


      $s=$pdo->prepare('SELECT vi.nome_item,MAX(vi.tipo) tipo,SUM(vi.quantidade) quantidade,SUM(vi.preco_unitario*vi.quantidade) faturamento,SUM(vi.custo_unitario*vi.quantidade) custo,SUM((vi.preco_unitario-vi.custo_unitario)*vi.quantidade) lucro FROM venda_itens vi JOIN vendas v ON v.id=vi.venda_id WHERE v.usuario_id=:uid AND v.data_venda BETWEEN :i AND :f GROUP BY vi.nome_item ORDER BY faturamento DESC');
$s->execute(['uid'=>$uid,'i'=>$inicio,'f'=>$fim]);
$out['itens']=$s->fetchAll(PDO::FETCH_ASSOC);
foreach($out['itens'] as &$x){
$x['margem']=(float)$x['faturamento']>0?(float)$x['lucro']/(float)$x['faturamento']*100:0;
}
unset($x);


      $s=$pdo->prepare('SELECT f.id,f.nome,COALESCE(SUM(d.valor),0) total,COUNT(d.id) pagamentos FROM fornecedores f LEFT JOIN despesas d ON d.usuario_id=f.usuario_id AND d.origem_tipo="fornecedor" AND d.origem_id=f.id AND d.data_despesa BETWEEN :i AND :f WHERE f.usuario_id=:uid AND f.ativo=1 GROUP BY f.id,f.nome ORDER BY total DESC');
$s->execute(['uid'=>$uid,'i'=>$inicio,'f'=>$fim]);
$out['fornecedores']=$s->fetchAll(PDO::FETCH_ASSOC);


      $s=$pdo->prepare('SELECT natureza,COALESCE(SUM(valor),0) total FROM custos_negocio WHERE usuario_id=:uid AND ativo=1 GROUP BY natureza');
$s->execute(['uid'=>$uid]);
foreach($s->fetchAll() as $c){
if($c['natureza']==='variavel')$out['custos']['variaveis']+=(float)$c['total'];
else$out['custos']['fixos']+=(float)$c['total'];
}

      $s=$pdo->prepare('SELECT COALESCE(SUM(salario_base+outros_custos),0) FROM funcionarios WHERE usuario_id=:uid AND ativo=1');
$s->execute(['uid'=>$uid]);
$out['custos']['fixos']+=(float)$s->fetchColumn();

      $out['custos']['variaveis']+=(float)$out['resumo']['custo'];

      $s=$pdo->prepare('SELECT valor_padrao,periodicidade,intervalo_dias FROM fornecedores WHERE usuario_id=:uid AND ativo=1 AND recorrente=1');
$s->execute(['uid'=>$uid]);
foreach($s->fetchAll() as $fr){
$mult=match($fr['periodicidade']){
'semanal'=>4.33,'quinzenal'=>2,'mensal'=>1,'outro'=>30/max(2,(int)($fr['intervalo_dias']?:30)),default=>0
}
;
$out['custos']['fixos']+=(float)$fr['valor_padrao']*$mult;
}
}
catch(Throwable $e){
error_log('CashPilot/Desempenho: '.$e->getMessage());
}

    return$out;
}


function cpPrevisaoCaixaNegocio(PDO $pdo,int $uid,int $dias=30):array{

    $hoje=date('Y-m-d');
$fim=date('Y-m-d',strtotime("+{$dias} days"));


    $s=$pdo->prepare('SELECT (SELECT COALESCE(SUM(valor),0) FROM receitas WHERE usuario_id=:u1 AND data_receita<=:hoje1)-(SELECT COALESCE(SUM(valor),0) FROM despesas WHERE usuario_id=:u2 AND data_despesa<=:hoje2)');

    $s->execute(['u1'=>$uid,'hoje1'=>$hoje,'u2'=>$uid,'hoje2'=>$hoje]);
$saldo=(float)$s->fetchColumn();


    $comp=0.0;

    try{

        /* despesas manuais futuras já registradas */
        $s=$pdo->prepare('SELECT COALESCE(SUM(valor),0) FROM despesas WHERE usuario_id=:uid AND data_despesa>:hoje AND data_despesa<=:fim AND origem_tipo="manual"');

        $s->execute(['uid'=>$uid,'hoje'=>$hoje,'fim'=>$fim]);
$comp+=(float)$s->fetchColumn();


        $competencias=array_unique([date('Y-m'),date('Y-m',strtotime($fim))]);


        /* equipe */
        $s=$pdo->prepare('SELECT salario_base,outros_custos,dia_pagamento FROM funcionarios WHERE usuario_id=:uid AND ativo=1');

        $s->execute(['uid'=>$uid]);
$func=$s->fetchAll(PDO::FETCH_ASSOC);

        foreach($competencias as $c)foreach($func as $x){

            $data=$c.'-'.str_pad((string)max(1,min(28,(int)$x['dia_pagamento'])),2,'0',STR_PAD_LEFT);

            if($data>$hoje&&$data<=$fim)$comp+=(float)$x['salario_base']+(float)$x['outros_custos'];
}


        /* custos recorrentes */
        $s=$pdo->prepare('SELECT valor,dia_vencimento FROM custos_negocio WHERE usuario_id=:uid AND ativo=1 AND recorrente=1');

        $s->execute(['uid'=>$uid]);
$custos=$s->fetchAll(PDO::FETCH_ASSOC);

        foreach($competencias as $c)foreach($custos as $x){

            $data=$c.'-'.str_pad((string)max(1,min(28,(int)$x['dia_vencimento'])),2,'0',STR_PAD_LEFT);

            if($data>$hoje&&$data<=$fim)$comp+=(float)$x['valor'];
}


        /* fornecedores recorrentes */
        $s=$pdo->prepare('SELECT valor_padrao,dia_vencimento,periodicidade,intervalo_dias FROM fornecedores WHERE usuario_id=:uid AND ativo=1 AND recorrente=1');

        $s->execute(['uid'=>$uid]);
$forn=$s->fetchAll(PDO::FETCH_ASSOC);

        foreach($competencias as $c)foreach($forn as $x){

            $datas=function_exists('cpDatasFornecedor')?cpDatasFornecedor($c,(string)$x['periodicidade'],(int)$x['dia_vencimento'],$x['intervalo_dias']!==null?(int)$x['intervalo_dias']:null):[];

            foreach($datas as $data)if($data>$hoje&&$data<=$fim)$comp+=(float)$x['valor_padrao'];
}
}
catch(Throwable $e){
error_log('CashPilot/PrevisaoCaixa: '.$e->getMessage());
}


    $inicioMedia=date('Y-m-01',strtotime('first day of -3 months'));
$fimMedia=date('Y-m-t',strtotime('last day of last month'));

    $s=$pdo->prepare('SELECT COALESCE(SUM(valor),0) FROM receitas WHERE usuario_id=:uid AND data_receita BETWEEN :i AND :f');

    $s->execute(['uid'=>$uid,'i'=>$inicioMedia,'f'=>$fimMedia]);
$mediaMensal=(float)$s->fetchColumn()/3;


    $receitaPrevista=$mediaMensal*($dias/30);
$projetado=$saldo+$receitaPrevista-$comp;

    return ['saldo_atual'=>$saldo,'receita_prevista'=>$receitaPrevista,'compromissos_previstos'=>$comp,'caixa_projetado'=>$projetado,'dias'=>$dias,'metodo'=>'média das receitas dos 3 meses completos anteriores + compromissos recorrentes programados'];
}


function cpPlanosAtivos(PDO $pdo,int $uid,int $limite=6):array{

    $s=$pdo->prepare('SELECT p.*,COUNT(i.id) total_itens,SUM(CASE WHEN i.concluido=1 THEN 1 ELSE 0 END) concluidos FROM planos_acao p LEFT JOIN plano_acao_itens i ON i.plano_id=p.id WHERE p.usuario_id=:uid AND p.status="ativo" GROUP BY p.id ORDER BY p.atualizado_em DESC LIMIT '.$limite);
$s->execute(['uid'=>$uid]);
return$s->fetchAll(PDO::FETCH_ASSOC);
}?>
