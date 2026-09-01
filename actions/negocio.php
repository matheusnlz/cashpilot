<?php
require_once __DIR__.'/../includes/auth.php';

require_once __DIR__.'/../database/conexao.php';

require_once __DIR__.'/../includes/negocio_financeiro.php';

exigirLogin();
exigirPost();

validarCsrf();

if(usuarioLogadoTipo()!=='mei') {

    header('Location: ../pages/dashboard.php');

    exit;

}

$pdo=conectar();

$uid=usuarioLogadoId();

$acao=$_POST['acao']??'';

function cpNumero($v):float {

    return max(0,(float)str_replace(',','.',(string)$v));

}

try {

    if ($acao === 'perfil') {

            $ofertasPermitidas = [
                'produtos',
                'servicos',
                'ambos',
            ];

            $operacoesPermitidas = [
                'presencial',
                'online',
                'hibrido',
                'domicilio',
            ];

            $publicosPermitidos = [
                'Público geral',
                'Empresas / B2B',
                'Famílias',
                'Crianças e responsáveis',
                'Jovens e adolescentes',
                'Adultos',
                'Principalmente homens',
                'Principalmente mulheres',
                'Público de maior poder aquisitivo',
                'Público local / regional',
            ];

            $objetivosPermitidos = [
                'Aumentar vendas e faturamento',
                'Melhorar lucro e margem',
                'Organizar despesas e custos',
                'Melhorar o fluxo de caixa',
                'Controlar estoque',
                'Organizar equipe',
                'Fidelizar e aumentar clientes',
                'Ganhar previsibilidade',
                'Entender melhor meu negócio',
            ];

            $oferta = in_array(
                $_POST['oferta'] ?? '',
                $ofertasPermitidas,
                true
            ) ? $_POST['oferta'] : 'servicos';

            $operacao = in_array(
                $_POST['operacao'] ?? '',
                $operacoesPermitidas,
                true
            ) ? $_POST['operacao'] : 'presencial';

            $publico = in_array(
                $_POST['publico_alvo'] ?? '',
                $publicosPermitidos,
                true
            ) ? $_POST['publico_alvo'] : 'Público geral';

            $objetivo = in_array(
                $_POST['objetivo_principal'] ?? '',
                $objetivosPermitidos,
                true
            ) ? $_POST['objetivo_principal'] : 'Entender melhor meu negócio';

            $stmt = $pdo->prepare(
                'INSERT INTO perfil_negocio (
            usuario_id,
            nome_negocio,
            oferta,
            operacao,
            publico_alvo,
            canal_principal,
            objetivo_principal
         ) VALUES (
            :uid,
            :nome,
            :oferta,
            :operacao,
            :publico,
            :canal,
            :objetivo
         )
         ON DUPLICATE KEY UPDATE
            nome_negocio = VALUES(nome_negocio),
            oferta = VALUES(oferta),
            operacao = VALUES(operacao),
            publico_alvo = VALUES(publico_alvo),
            canal_principal = VALUES(canal_principal),
            objetivo_principal = VALUES(objetivo_principal)'
            );

            $stmt->execute([
                'uid' => $uid,
                'nome' => trim($_POST['nome_negocio'] ?? '') ?: null,
                'oferta' => $oferta,
                'operacao' => $operacao,
                'publico' => $publico,
                'canal' => trim($_POST['canal_principal'] ?? '') ?: null,
                'objetivo' => $objetivo,
            ]);

    }

    if($acao==='adicionar_item') {

            $nome=trim($_POST['nome']??'');

            $tipo=($_POST['tipo']??'')==='servico'?'servico':'produto';

            $preco=cpNumero($_POST['preco_venda']??0);

            $custo=cpNumero($_POST['custo_unitario']??0);

            $ctrl=$tipo==='produto'&&!empty($_POST['controlar_estoque'])?1:0;

            $estoque=$ctrl?max(0,(int)($_POST['estoque_atual']??0)):0;

            $min=$ctrl?max(0,(int)($_POST['estoque_minimo']??0)):0;

            $fornecedor=$tipo==='produto'&&!empty($_POST['fornecedor_id'])?(int)$_POST['fornecedor_id']:null;

            if($fornecedor) {

                    $vf=$pdo->prepare('SELECT id FROM fornecedores WHERE id=:id AND usuario_id=:uid AND ativo=1');

                    $vf->execute(['id'=>$fornecedor,'uid'=>$uid]);

                    if(!$vf->fetchColumn())$fornecedor=null;

        }

            if($nome!==''&&$preco>=0) {

                    $s=$pdo->prepare('INSERT INTO produtos_servicos (usuario_id,nome,tipo,preco_venda,custo_unitario,estoque_atual,estoque_minimo,controlar_estoque,fornecedor_id,ativo) VALUES (:uid,:nome,:tipo,:preco,:custo,:estoque,:min,:ctrl,:fornecedor,1)');

                    $s->execute(['uid'=>$uid,'nome'=>$nome,'tipo'=>$tipo,'preco'=>$preco,'custo'=>$custo,'estoque'=>$estoque,'min'=>$min,'ctrl'=>$ctrl,'fornecedor'=>$fornecedor]);

                    $produtoId=(int)$pdo->lastInsertId();

                    if($ctrl && $estoque>0) {

                            $m=$pdo->prepare('INSERT INTO movimentacoes_estoque (usuario_id,produto_id,fornecedor_id,tipo,quantidade,custo_unitario,referencia) VALUES (:uid,:pid,:fid,"ajuste",:qtd,:custo,"Estoque inicial")');

                            $m->execute(['uid'=>$uid,'pid'=>$produtoId,'fid'=>$fornecedor,'qtd'=>$estoque,'custo'=>$custo]);

            }

        }

    }

    if($acao==='editar_item') {

            $id=(int)($_POST['id']??0);

            $nome=trim($_POST['nome']??'');

            $tipo=($_POST['tipo']??'')==='servico'?'servico':'produto';

            $preco=cpNumero($_POST['preco_venda']??0);

            $custo=cpNumero($_POST['custo_unitario']??0);

            $ctrl=$tipo==='produto'&&!empty($_POST['controlar_estoque'])?1:0;

            $estoque=$ctrl?max(0,(int)($_POST['estoque_atual']??0)):0;

            $min=$ctrl?max(0,(int)($_POST['estoque_minimo']??0)):0;

            $fornecedor=$tipo==='produto'&&!empty($_POST['fornecedor_id'])?(int)$_POST['fornecedor_id']:null;

            if($id>0&&$nome!=='') {

                    $old=$pdo->prepare('SELECT estoque_atual FROM produtos_servicos WHERE id=:id AND usuario_id=:uid');

                    $old->execute(['id'=>$id,'uid'=>$uid]);

                    $estoqueAnterior=(int)($old->fetchColumn()?:0);

                    $s=$pdo->prepare('UPDATE produtos_servicos SET nome=:nome,tipo=:tipo,preco_venda=:preco,custo_unitario=:custo,estoque_atual=:estoque,estoque_minimo=:min,controlar_estoque=:ctrl,fornecedor_id=:fornecedor WHERE id=:id AND usuario_id=:uid');

                    $s->execute(['nome'=>$nome,'tipo'=>$tipo,'preco'=>$preco,'custo'=>$custo,'estoque'=>$estoque,'min'=>$min,'ctrl'=>$ctrl,'fornecedor'=>$fornecedor,'id'=>$id,'uid'=>$uid]);

                    if($ctrl && $estoque!==$estoqueAnterior) {

                            $dif=$estoque-$estoqueAnterior;

                            $m=$pdo->prepare('INSERT INTO movimentacoes_estoque (usuario_id,produto_id,fornecedor_id,tipo,quantidade,custo_unitario,referencia) VALUES (:uid,:pid,:fid,"ajuste",:qtd,:custo,"Ajuste manual")');

                            $m->execute(['uid'=>$uid,'pid'=>$id,'fid'=>$fornecedor,'qtd'=>$dif,'custo'=>$custo]);

            }

        }

    }

    if($acao==='remover_item') {

        $s=$pdo->prepare('UPDATE produtos_servicos SET ativo=0 WHERE id=:id AND usuario_id=:uid');

        $s->execute(['id'=>(int)($_POST['id']??0),'uid'=>$uid]);

    }

    if($acao==='editar_funcionario') {

        $id=(int)($_POST['id']??0);

        $nome=trim($_POST['nome']??'');

        if($id>0&&$nome!=='') {

            $s=$pdo->prepare('UPDATE funcionarios SET nome=:nome,cargo=:cargo,salario_base=:salario,outros_custos=:custos,dia_pagamento=:dia WHERE id=:id AND usuario_id=:uid');

            $s->execute(['nome'=>$nome,'cargo'=>trim($_POST['cargo']??'')?:null,'salario'=>cpNumero($_POST['salario_base']??0),'custos'=>cpNumero($_POST['outros_custos']??0),'dia'=>max(1,min(28,(int)($_POST['dia_pagamento']??5))),'id'=>$id,'uid'=>$uid]);

            cpSincronizarCustosRecorrentesMes($pdo,$uid);

        }

    }

    if($acao==='adicionar_funcionario') {

        $nome=trim($_POST['nome']??'');

        if($nome!=='') {

            $s=$pdo->prepare('INSERT INTO funcionarios (usuario_id,nome,cargo,salario_base,outros_custos,dia_pagamento) VALUES (:uid,:nome,:cargo,:salario,:custos,:dia)');

            $s->execute(['uid'=>$uid,'nome'=>$nome,'cargo'=>trim($_POST['cargo']??'')?:null,'salario'=>cpNumero($_POST['salario_base']??0),'custos'=>cpNumero($_POST['outros_custos']??0),'dia'=>max(1,min(28,(int)($_POST['dia_pagamento']??5)))]);

            cpSincronizarCustosRecorrentesMes($pdo,$uid);

        }

    }

    if($acao==='remover_funcionario') {

        $id=(int)($_POST['id']??0);

        $s=$pdo->prepare('UPDATE funcionarios SET ativo=0 WHERE id=:id AND usuario_id=:uid');

        $s->execute(['id'=>$id,'uid'=>$uid]);

    }

    if($acao==='adicionar_fornecedor') {

        $nome=trim($_POST['nome']??'');

        $valor=cpNumero($_POST['valor_padrao']??0);

        $per=in_array($_POST['periodicidade']??'',['pontual','semanal','quinzenal','mensal','outro'],true)?$_POST['periodicidade']:'pontual';

        $rec=$per==='pontual'?0:1;

        $int=$per==='outro'?max(2,min(180,(int)($_POST['intervalo_dias']??30))):null;

        if($nome!=='') {

            $s=$pdo->prepare('INSERT INTO fornecedores (usuario_id,nome,descricao,valor_padrao,recorrente,periodicidade,intervalo_dias,dia_vencimento,data_inicio) VALUES (:uid,:nome,:descricao,:valor,:rec,:per,:inter,:dia,CURDATE())');

            $s->execute(['uid'=>$uid,'nome'=>$nome,'descricao'=>trim($_POST['descricao']??'')?:null,'valor'=>$valor,'rec'=>$rec,'per'=>$per,'inter'=>$int,'dia'=>max(1,min(28,(int)($_POST['dia_vencimento']??10)))]);

            $id=(int)$pdo->lastInsertId();

            if($rec) {

                cpSincronizarCustosRecorrentesMes($pdo,$uid);

            }
            elseif($valor>0) {

                $cat=cpCategoriaId($pdo,$uid,'Fornecedores','despesa');

                $d=$pdo->prepare('INSERT INTO despesas (usuario_id,categoria_id,valor,descricao,data_despesa,origem_tipo,origem_id) VALUES (:uid,:cat,:valor,:descricao,:data,"fornecedor",:oid)');

                $d->execute(['uid'=>$uid,'cat'=>$cat,'valor'=>$valor,'descricao'=>'Fornecedor · '.$nome,'data'=>date('Y-m-d'),'oid'=>$id]);

            }

        }

    }

    if($acao==='editar_fornecedor') {

        $id=(int)($_POST['id']??0);

        $nome=trim($_POST['nome']??'');

        $valor=cpNumero($_POST['valor_padrao']??0);

        $per=in_array($_POST['periodicidade']??'',['pontual','semanal','quinzenal','mensal','outro'],true)?$_POST['periodicidade']:'pontual';

        $rec=$per==='pontual'?0:1;

        $int=$per==='outro'?max(2,min(180,(int)($_POST['intervalo_dias']??30))):null;

        if($id>0&&$nome!=='') {

            $s=$pdo->prepare('UPDATE fornecedores SET nome=:nome,descricao=:descricao,valor_padrao=:valor,recorrente=:rec,periodicidade=:per,intervalo_dias=:inter,dia_vencimento=:dia WHERE id=:id AND usuario_id=:uid');

            $s->execute(['nome'=>$nome,'descricao'=>trim($_POST['descricao']??'')?:null,'valor'=>$valor,'rec'=>$rec,'per'=>$per,'inter'=>$int,'dia'=>max(1,min(28,(int)($_POST['dia_vencimento']??10))),'id'=>$id,'uid'=>$uid]);

            cpSincronizarCustosRecorrentesMes($pdo,$uid);

        }

    }

    if($acao==='remover_fornecedor') {

        $s=$pdo->prepare('UPDATE fornecedores SET ativo=0 WHERE id=:id AND usuario_id=:uid');

        $s->execute(['id'=>(int)($_POST['id']??0),'uid'=>$uid]);

    }

    if($acao==='adicionar_custo') {

            $descricao=trim($_POST['descricao']??'');

            $valor=cpNumero($_POST['valor']??0);

            $rec=!empty($_POST['recorrente'])?1:0;

            $natureza=($_POST['natureza']??'fixo')==='variavel'?'variavel':'fixo';

            if($descricao!==''&&$valor>0) {

                    $s=$pdo->prepare('INSERT INTO custos_negocio (usuario_id,descricao,valor,recorrente,natureza,dia_vencimento) VALUES (:uid,:descricao,:valor,:rec,:natureza,:dia)');

                    $s->execute(['uid'=>$uid,'descricao'=>$descricao,'valor'=>$valor,'rec'=>$rec,'natureza'=>$natureza,'dia'=>max(1,min(28,(int)($_POST['dia_vencimento']??10)))]);

                    $id=(int)$pdo->lastInsertId();

                    if($rec) {

                cpSincronizarCustosRecorrentesMes($pdo,$uid);

            }

                    else {

                            $cat=cpCategoriaId($pdo,$uid,$natureza==='variavel'?'Custos variáveis':'Custos fixos','despesa');

                            $d=$pdo->prepare('INSERT INTO despesas (usuario_id,categoria_id,valor,descricao,data_despesa,origem_tipo,origem_id) VALUES (:uid,:cat,:valor,:descricao,:data,"custo_fixo",:oid)');

                            $d->execute(['uid'=>$uid,'cat'=>$cat,'valor'=>$valor,'descricao'=>'Custo · '.$descricao,'data'=>date('Y-m-d'),'oid'=>$id]);

            }

        }

    }

    if($acao==='remover_custo') {

        $s=$pdo->prepare('UPDATE custos_negocio SET ativo=0 WHERE id=:id AND usuario_id=:uid');

        $s->execute(['id'=>(int)($_POST['id']??0),'uid'=>$uid]);

    }

    if($acao==='entrada_estoque') {

            $produtoId=(int)($_POST['produto_id']??0);

            $quantidade=max(1,(int)($_POST['quantidade']??1));

            $custo=cpNumero($_POST['custo_unitario']??0);

            $fornecedor=!empty($_POST['fornecedor_id'])?(int)$_POST['fornecedor_id']:null;

            $registrarDespesa=!empty($_POST['registrar_despesa']);

            $data=$_POST['data_movimentacao']??date('Y-m-d');

            $s=$pdo->prepare('SELECT id,nome,fornecedor_id FROM produtos_servicos WHERE id=:id AND usuario_id=:uid AND ativo=1 AND tipo="produto"');

            $s->execute(['id'=>$produtoId,'uid'=>$uid]);

            $produto=$s->fetch();

            if(!$produto)throw new RuntimeException('Produto não encontrado.');

            if(!$fornecedor)$fornecedor=$produto['fornecedor_id']?:null;

            $pdo->beginTransaction();

            $u=$pdo->prepare('UPDATE produtos_servicos SET estoque_atual=estoque_atual+:qtd,custo_unitario=CASE WHEN :custo>0 THEN :custo2 ELSE custo_unitario END,fornecedor_id=COALESCE(:fid,fornecedor_id) WHERE id=:id AND usuario_id=:uid');

            $u->execute(['qtd'=>$quantidade,'custo'=>$custo,'custo2'=>$custo,'fid'=>$fornecedor,'id'=>$produtoId,'uid'=>$uid]);

            $m=$pdo->prepare('INSERT INTO movimentacoes_estoque (usuario_id,produto_id,fornecedor_id,tipo,quantidade,custo_unitario,referencia,data_movimentacao) VALUES (:uid,:pid,:fid,"entrada",:qtd,:custo,:ref,:data)');

            $m->execute(['uid'=>$uid,'pid'=>$produtoId,'fid'=>$fornecedor,'qtd'=>$quantidade,'custo'=>$custo,'ref'=>'Entrada de estoque','data'=>$data.' 12:00:00']);

            if($registrarDespesa && $custo>0) {

                    $total=$quantidade*$custo;

                    $cat=cpCategoriaId($pdo,$uid,'Estoque e insumos','despesa');

                    $d=$pdo->prepare('INSERT INTO despesas (usuario_id,categoria_id,valor,descricao,data_despesa,origem_tipo,origem_id,origem_referencia) VALUES (:uid,:cat,:valor,:descricao,:data,"estoque",:oid,:ref)');

                    $d->execute(['uid'=>$uid,'cat'=>$cat,'valor'=>$total,'descricao'=>'Estoque · '.$produto['nome'].' x '.$quantidade,'data'=>$data,'oid'=>$produtoId,'ref'=>$data]);

        }

            $pdo->commit();

    }

    if($acao==='editar_custo') {

            $id=(int)($_POST['id']??0);

            $descricao=trim($_POST['descricao']??'');

            $valor=cpNumero($_POST['valor']??0);

            $rec=!empty($_POST['recorrente'])?1:0;

            $dia=max(1,min(28,(int)($_POST['dia_vencimento']??10)));

            $natureza=($_POST['natureza']??'fixo')==='variavel'?'variavel':'fixo';

            if($id>0&&$descricao!==''&&$valor>0) {

                    $s=$pdo->prepare('UPDATE custos_negocio SET descricao=:descricao,valor=:valor,recorrente=:rec,natureza=:natureza,dia_vencimento=:dia WHERE id=:id AND usuario_id=:uid');

                    $s->execute(['descricao'=>$descricao,'valor'=>$valor,'rec'=>$rec,'natureza'=>$natureza,'dia'=>$dia,'id'=>$id,'uid'=>$uid]);

                    cpSincronizarCustosRecorrentesMes($pdo,$uid);

        }

    }

    $_SESSION['mensagem_negocio']='Informações atualizadas com sucesso.';

}
catch(Throwable $e) {

    if($pdo->inTransaction())$pdo->rollBack();

    error_log('CashPilot/Negocio: '.$e->getMessage());

    $_SESSION['mensagem_negocio']='Não foi possível salvar. Confirme se as migrations do CashPilot até a versão 11 foram executadas.';

}

$retorno='negocio.php';

if(str_contains($acao,'funcionario'))$retorno='funcionarios.php';

elseif(str_contains($acao,'fornecedor'))$retorno='fornecedores.php';

elseif(str_contains($acao,'custo'))$retorno='custos.php';

elseif(str_contains($acao,'item')||$acao==='entrada_estoque')$retorno='produtos_servicos.php';

header('Location: ../pages/'.$retorno);

exit;
