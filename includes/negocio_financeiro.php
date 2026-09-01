<?php
function cpCategoriaId(PDO $pdo,int $uid,string $nome,string $tipo):?int{
$s=$pdo->prepare('SELECT id FROM categorias WHERE usuario_id=:uid AND tipo=:tipo AND nome=:nome LIMIT 1');
$s->execute(['uid'=>$uid,'tipo'=>$tipo,'nome'=>$nome]);
$id=$s->fetchColumn();
if($id!==false)return(int)$id;
$s=$pdo->prepare('SELECT id FROM categorias WHERE usuario_id=:uid AND tipo=:tipo AND nome="Outros" LIMIT 1');
$s->execute(['uid'=>$uid,'tipo'=>$tipo]);
$id=$s->fetchColumn();
return$id!==false?(int)$id:null;
}

function cpDespesaOrigem(PDO $pdo,int $uid,string $tipo,int $oid,string $descricao,float $valor,string $data,string $categoria):void{
if($valor<=0)return;
$cat=cpCategoriaId($pdo,$uid,$categoria,'despesa');
$comp=substr($data,0,7);
try{
$s=$pdo->prepare('SELECT id FROM despesas WHERE usuario_id=:uid AND origem_tipo=:tipo AND origem_id=:oid AND (origem_referencia=:ref_origem OR (origem_referencia IS NULL AND data_despesa=:ref_data)) LIMIT 1');
$s->execute(['uid'=>$uid,'tipo'=>$tipo,'oid'=>$oid,'ref_origem'=>$data,'ref_data'=>$data]);
$id=$s->fetchColumn();
}
catch(Throwable $e){
$s=$pdo->prepare('SELECT id FROM despesas WHERE usuario_id=:uid AND origem_tipo=:tipo AND origem_id=:oid AND competencia=:comp LIMIT 1');
$s->execute(['uid'=>$uid,'tipo'=>$tipo,'oid'=>$oid,'comp'=>$comp]);
$id=$s->fetchColumn();
}
if($id){
$u=$pdo->prepare('UPDATE despesas SET categoria_id=:cat,valor=:valor,descricao=:descricao,data_despesa=:data WHERE id=:id AND usuario_id=:uid');
$u->execute(['cat'=>$cat,'valor'=>$valor,'descricao'=>$descricao,'data'=>$data,'id'=>$id,'uid'=>$uid]);
return;
}
try{
$i=$pdo->prepare('INSERT INTO despesas (usuario_id,categoria_id,valor,descricao,data_despesa,origem_tipo,origem_id,competencia,origem_referencia) VALUES (:uid,:cat,:valor,:descricao,:data,:tipo,:oid,:comp,:ref)');
$i->execute(['uid'=>$uid,'cat'=>$cat,'valor'=>$valor,'descricao'=>$descricao,'data'=>$data,'tipo'=>$tipo,'oid'=>$oid,'comp'=>$comp,'ref'=>$data]);
}
catch(Throwable $e){
$i=$pdo->prepare('INSERT INTO despesas (usuario_id,categoria_id,valor,descricao,data_despesa,origem_tipo,origem_id,competencia) VALUES (:uid,:cat,:valor,:descricao,:data,:tipo,:oid,:comp)');
$i->execute(['uid'=>$uid,'cat'=>$cat,'valor'=>$valor,'descricao'=>$descricao,'data'=>$data,'tipo'=>$tipo,'oid'=>$oid,'comp'=>$comp]);
}
}

function cpDatasFornecedor(string $competencia,string $periodicidade,int $dia,?int $intervalo):array{
$inicio=new DateTime($competencia.'-01');
$fim=(clone$inicio)->modify('last day of this month');
$dia=max(1,min((int)$fim->format('d'),$dia));
$at=new DateTime($competencia.'-'.str_pad((string)$dia,2,'0',STR_PAD_LEFT));
if($periodicidade==='mensal')return[$at->format('Y-m-d')];
$passo=$periodicidade==='semanal'?7:($periodicidade==='quinzenal'?15:max(2,(int)($intervalo?:30)));
$datas=[];
while($at<=$fim){
$datas[]=$at->format('Y-m-d');
$at->modify('+'.$passo.' days');
}
return$datas;
}

function cpSincronizarCustosRecorrentesMes(PDO $pdo,int $uid,?string $competencia=null):void{
$competencia=$competencia?:date('Y-m');
try{
$s=$pdo->prepare('SELECT id,nome,salario_base,outros_custos,dia_pagamento FROM funcionarios WHERE usuario_id=:uid AND ativo=1');
$s->execute(['uid'=>$uid]);
foreach($s->fetchAll() as$x){
$data=$competencia.'-'.str_pad((string)max(1,min(28,(int)$x['dia_pagamento'])),2,'0',STR_PAD_LEFT);
cpDespesaOrigem($pdo,$uid,'funcionario',(int)$x['id'],'Funcionário · '.$x['nome'],(float)$x['salario_base']+(float)$x['outros_custos'],$data,'Funcionários');
}
$s=$pdo->prepare('SELECT id,nome,descricao,valor_padrao,dia_vencimento,recorrente,COALESCE(periodicidade,IF(recorrente=1,"mensal","pontual")) periodicidade,intervalo_dias FROM fornecedores WHERE usuario_id=:uid AND ativo=1');
$s->execute(['uid'=>$uid]);
foreach($s->fetchAll() as$x){
if($x['periodicidade']==='pontual')continue;
try{
$del=$pdo->prepare('DELETE FROM despesas WHERE usuario_id=:uid AND origem_tipo="fornecedor" AND origem_id=:oid AND competencia=:comp');
$del->execute(['uid'=>$uid,'oid'=>$x['id'],'comp'=>$competencia]);
}
catch(Throwable $e){
}
foreach(cpDatasFornecedor($competencia,$x['periodicidade'],(int)$x['dia_vencimento'],$x['intervalo_dias']!==null?(int)$x['intervalo_dias']:null) as$data)cpDespesaOrigem($pdo,$uid,'fornecedor',(int)$x['id'],'Fornecedor · '.$x['nome'].($x['descricao']?' · '.$x['descricao']:''),(float)$x['valor_padrao'],$data,'Fornecedores');
}
$s=$pdo->prepare('SELECT id,descricao,valor,dia_vencimento,COALESCE(natureza,"fixo") natureza FROM custos_negocio WHERE usuario_id=:uid AND ativo=1 AND recorrente=1');
$s->execute(['uid'=>$uid]);
foreach($s->fetchAll() as$x){
$data=$competencia.'-'.str_pad((string)max(1,min(28,(int)$x['dia_vencimento'])),2,'0',STR_PAD_LEFT);
$nat=($x['natureza']??'fixo')==='variavel'?'variavel':'fixo';
cpDespesaOrigem($pdo,$uid,'custo_fixo',(int)$x['id'],'Custo '.($nat==='variavel'?'variável':'fixo').' · '.$x['descricao'],(float)$x['valor'],$data,$nat==='variavel'?'Custos variáveis':'Custos fixos');
}
}
catch(Throwable $e){
}
}

function cpCompromissosMensais(PDO $pdo,int $uid):array{
$out=['funcionarios'=>0.0,'fornecedores'=>0.0,'custos_fixos'=>0.0,'total'=>0.0];
try{
$s=$pdo->prepare('SELECT COALESCE(SUM(salario_base+outros_custos),0) FROM funcionarios WHERE usuario_id=:uid AND ativo=1');
$s->execute(['uid'=>$uid]);
$out['funcionarios']=(float)$s->fetchColumn();
$s=$pdo->prepare('SELECT valor_padrao,recorrente,COALESCE(periodicidade,IF(recorrente=1,"mensal","pontual")) periodicidade,intervalo_dias FROM fornecedores WHERE usuario_id=:uid AND ativo=1');
$s->execute(['uid'=>$uid]);
foreach($s->fetchAll() as$f){
$mult=match($f['periodicidade']){
'semanal'=>4.33,'quinzenal'=>2,'mensal'=>1,'outro'=>30/max(2,(int)($f['intervalo_dias']?:30)),default=>0
}
;
$out['fornecedores']+=(float)$f['valor_padrao']*$mult;
}
$s=$pdo->prepare('SELECT COALESCE(SUM(valor),0) FROM custos_negocio WHERE usuario_id=:uid AND ativo=1 AND recorrente=1');
$s->execute(['uid'=>$uid]);
$out['custos_fixos']=(float)$s->fetchColumn();
}
catch(Throwable $e){
}
$out['total']=$out['funcionarios']+$out['fornecedores']+$out['custos_fixos'];
return$out;
}

function cpResumoVendas(PDO $pdo,int $uid,string $inicio,string $fim):array{
$out=['vendas'=>0,'receita_vendas'=>0.0,'custo_vendas'=>0.0,'margem_bruta'=>0.0,'ticket_medio'=>0.0];
try{
$s=$pdo->prepare('SELECT COUNT(*) vendas,COALESCE(SUM(valor_bruto),0) receita,COALESCE(SUM(custo_total),0) custo FROM vendas WHERE usuario_id=:uid AND data_venda BETWEEN :i AND :f');
$s->execute(['uid'=>$uid,'i'=>$inicio,'f'=>$fim]);
$r=$s->fetch();
$out['vendas']=(int)$r['vendas'];
$out['receita_vendas']=(float)$r['receita'];
$out['custo_vendas']=(float)$r['custo'];
$out['margem_bruta']=$out['receita_vendas']>0?(($out['receita_vendas']-$out['custo_vendas'])/$out['receita_vendas']*100):0;
$out['ticket_medio']=$out['vendas']>0?$out['receita_vendas']/$out['vendas']:0;
}
catch(Throwable $e){
}
return$out;
}

function cpPerfilNegocio(PDO $pdo,int $uid):array{
try{
$s=$pdo->prepare('SELECT * FROM perfil_negocio WHERE usuario_id=:uid');
$s->execute(['uid'=>$uid]);
return$s->fetch()?:[];
}
catch(Throwable $e){
return[];
}
}

function cpGrupoNicho(string $nicho):string{
$n=mb_strtolower($nicho);
if(str_contains($n,'barbear')||str_contains($n,'salão')||str_contains($n,'beleza'))return'beleza';
if(str_contains($n,'loja')||str_contains($n,'comércio'))return'comercio';
if(str_contains($n,'aliment')||str_contains($n,'restaur'))return'alimentacao';
if(str_contains($n,'serviço')||str_contains($n,'autônomo'))return'servicos';
return'geral';
}?>
