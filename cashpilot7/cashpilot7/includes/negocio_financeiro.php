<?php
function cpCategoriaId(PDO $pdo,int $uid,string $nome,string $tipo):?int{
    $s=$pdo->prepare('SELECT id FROM categorias WHERE usuario_id=:uid AND tipo=:tipo AND nome=:nome LIMIT 1');
    $s->execute(['uid'=>$uid,'tipo'=>$tipo,'nome'=>$nome]);$id=$s->fetchColumn();
    if($id!==false)return (int)$id;
    $s=$pdo->prepare('SELECT id FROM categorias WHERE usuario_id=:uid AND tipo=:tipo AND nome="Outros" LIMIT 1');
    $s->execute(['uid'=>$uid,'tipo'=>$tipo]);$id=$s->fetchColumn();return $id!==false?(int)$id:null;
}
function cpDespesaOrigem(PDO $pdo,int $uid,string $tipo,int $origemId,string $descricao,float $valor,int $dia,string $categoria,string $competencia):void{
    if($valor<=0)return;$cat=cpCategoriaId($pdo,$uid,$categoria,'despesa');
    $dia=max(1,min(28,$dia));$data=$competencia.'-'.str_pad((string)$dia,2,'0',STR_PAD_LEFT);
    $s=$pdo->prepare('SELECT id FROM despesas WHERE usuario_id=:uid AND origem_tipo=:tipo AND origem_id=:oid AND competencia=:comp LIMIT 1');
    $s->execute(['uid'=>$uid,'tipo'=>$tipo,'oid'=>$origemId,'comp'=>$competencia]);$id=$s->fetchColumn();
    if($id){$u=$pdo->prepare('UPDATE despesas SET categoria_id=:cat,valor=:valor,descricao=:descricao,data_despesa=:data WHERE id=:id AND usuario_id=:uid');$u->execute(['cat'=>$cat,'valor'=>$valor,'descricao'=>$descricao,'data'=>$data,'id'=>$id,'uid'=>$uid]);return;}
    $i=$pdo->prepare('INSERT INTO despesas (usuario_id,categoria_id,valor,descricao,data_despesa,origem_tipo,origem_id,competencia) VALUES (:uid,:cat,:valor,:descricao,:data,:tipo,:oid,:comp)');
    $i->execute(['uid'=>$uid,'cat'=>$cat,'valor'=>$valor,'descricao'=>$descricao,'data'=>$data,'tipo'=>$tipo,'oid'=>$origemId,'comp'=>$competencia]);
}
function cpSincronizarCustosRecorrentesMes(PDO $pdo,int $uid,?string $competencia=null):void{
    $competencia=$competencia?:date('Y-m');
    try{
        $s=$pdo->prepare('SELECT id,nome,salario_base,outros_custos,dia_pagamento FROM funcionarios WHERE usuario_id=:uid AND ativo=1');$s->execute(['uid'=>$uid]);
        foreach($s->fetchAll() as $x)cpDespesaOrigem($pdo,$uid,'funcionario',(int)$x['id'],'Funcionário · '.$x['nome'],(float)$x['salario_base']+(float)$x['outros_custos'],(int)$x['dia_pagamento'],'Funcionários',$competencia);
        $s=$pdo->prepare('SELECT id,nome,descricao,valor_padrao,dia_vencimento FROM fornecedores WHERE usuario_id=:uid AND ativo=1 AND recorrente=1');$s->execute(['uid'=>$uid]);
        foreach($s->fetchAll() as $x)cpDespesaOrigem($pdo,$uid,'fornecedor',(int)$x['id'],'Fornecedor · '.$x['nome'].($x['descricao']?' · '.$x['descricao']:''),(float)$x['valor_padrao'],(int)$x['dia_vencimento'],'Fornecedores',$competencia);
        $s=$pdo->prepare('SELECT id,descricao,valor,dia_vencimento FROM custos_negocio WHERE usuario_id=:uid AND ativo=1 AND recorrente=1');$s->execute(['uid'=>$uid]);
        foreach($s->fetchAll() as $x)cpDespesaOrigem($pdo,$uid,'custo_fixo',(int)$x['id'],'Custo fixo · '.$x['descricao'],(float)$x['valor'],(int)$x['dia_vencimento'],'Custos fixos',$competencia);
    }catch(Throwable $e){}
}
function cpCompromissosMensais(PDO $pdo,int $uid):array{
    $out=['funcionarios'=>0.0,'fornecedores'=>0.0,'custos_fixos'=>0.0,'total'=>0.0];
    try{
        $s=$pdo->prepare('SELECT COALESCE(SUM(salario_base+outros_custos),0) FROM funcionarios WHERE usuario_id=:uid AND ativo=1');$s->execute(['uid'=>$uid]);$out['funcionarios']=(float)$s->fetchColumn();
        $s=$pdo->prepare('SELECT COALESCE(SUM(valor_padrao),0) FROM fornecedores WHERE usuario_id=:uid AND ativo=1 AND recorrente=1');$s->execute(['uid'=>$uid]);$out['fornecedores']=(float)$s->fetchColumn();
        $s=$pdo->prepare('SELECT COALESCE(SUM(valor),0) FROM custos_negocio WHERE usuario_id=:uid AND ativo=1 AND recorrente=1');$s->execute(['uid'=>$uid]);$out['custos_fixos']=(float)$s->fetchColumn();
    }catch(Throwable $e){}$out['total']=$out['funcionarios']+$out['fornecedores']+$out['custos_fixos'];return$out;
}
function cpResumoVendas(PDO $pdo,int $uid,string $inicio,string $fim):array{
    $out=['vendas'=>0,'receita_vendas'=>0.0,'custo_vendas'=>0.0,'margem_bruta'=>0.0,'ticket_medio'=>0.0];
    try{$s=$pdo->prepare('SELECT COUNT(*) vendas,COALESCE(SUM(valor_bruto),0) receita,COALESCE(SUM(custo_total),0) custo FROM vendas WHERE usuario_id=:uid AND data_venda BETWEEN :i AND :f');$s->execute(['uid'=>$uid,'i'=>$inicio,'f'=>$fim]);$r=$s->fetch();$out['vendas']=(int)$r['vendas'];$out['receita_vendas']=(float)$r['receita'];$out['custo_vendas']=(float)$r['custo'];$out['margem_bruta']=$out['receita_vendas']>0?(($out['receita_vendas']-$out['custo_vendas'])/$out['receita_vendas']*100):0;$out['ticket_medio']=$out['vendas']>0?$out['receita_vendas']/$out['vendas']:0;}catch(Throwable $e){}return$out;
}
?>