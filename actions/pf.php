<?php
require_once __DIR__.'/../includes/auth.php';

require_once __DIR__.'/../database/conexao.php';

require_once __DIR__.'/../includes/pf_financeiro.php';

exigirLogin();
exigirPost();

validarCsrf();

if(usuarioLogadoTipo()!=='pessoa_fisica') {

    header('Location: ../pages/dashboard.php');

    exit;

}

$pdo=conectar();

$uid=usuarioLogadoId();

$acao=$_POST['acao']??'';

$retorno='dashboard.php';

try {

    if($acao==='salvar_orcamento') {

          foreach($_POST['limites']??[] as $id=>$valor) {

            $v=trim((string)$valor);

            $lim=$v===''?null:max(0,(float)str_replace(',','.',$v));

            $s=$pdo->prepare('UPDATE categorias SET limite_mensal=:l WHERE id=:id AND usuario_id=:uid AND tipo="despesa"');

            $s->execute(['l'=>$lim,'id'=>(int)$id,'uid'=>$uid]);

        }

          $_SESSION['mensagem_pf']='Orçamentos atualizados.';

        $retorno='orcamentos.php';

    }

    if($acao==='adicionar_recorrencia') {

          $nome=trim($_POST['nome']??'');

        $valor=max(0,(float)str_replace(',','.',$_POST['valor']??0));

        $tipo=($_POST['tipo']??'despesa')==='assinatura'?'assinatura':'despesa';

        $per=in_array($_POST['periodicidade']??'', ['semanal','quinzenal','mensal','anual','outro'],true)?$_POST['periodicidade']:'mensal';

        $inter=$per==='outro'?max(2,min(365,(int)($_POST['intervalo_dias']??30))):null;

        $dia=max(1,min(28,(int)($_POST['dia_vencimento']??10)));

        $cat=(int)($_POST['categoria_id']??0)?:null;
        if ($cat !== null && !cashpilotCategoriaPertenceAoUsuario($pdo, $uid, $cat, 'despesa')) {
            $cat = null;
        }

          if($nome!==''&&$valor>0) {

            $prox=$_POST['proxima_data']??date('Y-m-').str_pad((string)$dia,2,'0',STR_PAD_LEFT);

            $s=$pdo->prepare('INSERT INTO recorrencias_pf (usuario_id,nome,categoria_id,valor,tipo,periodicidade,intervalo_dias,dia_vencimento,proxima_data) VALUES (:uid,:nome,:cat,:valor,:tipo,:per,:inter,:dia,:prox)');

            $s->execute(['uid'=>$uid,'nome'=>$nome,'cat'=>$cat,'valor'=>$valor,'tipo'=>$tipo,'per'=>$per,'inter'=>$inter,'dia'=>$dia,'prox'=>$prox]);

            cpSincronizarRecorrenciasPF($pdo,$uid);

        }

          $_SESSION['mensagem_pf']='Recorrência salva.';

        $retorno='recorrencias.php';

    }

    if($acao==='editar_recorrencia') {

          $id=(int)($_POST['id']??0);

        $nome=trim($_POST['nome']??'');

        $valor=max(0,(float)str_replace(',','.',$_POST['valor']??0));

        $tipo=($_POST['tipo']??'despesa')==='assinatura'?'assinatura':'despesa';

        $per=in_array($_POST['periodicidade']??'', ['semanal','quinzenal','mensal','anual','outro'],true)?$_POST['periodicidade']:'mensal';

        $inter=$per==='outro'?max(2,min(365,(int)($_POST['intervalo_dias']??30))):null;

        $dia=max(1,min(28,(int)($_POST['dia_vencimento']??10)));

        $cat=(int)($_POST['categoria_id']??0)?:null;
        if ($cat !== null && !cashpilotCategoriaPertenceAoUsuario($pdo, $uid, $cat, 'despesa')) {
            $cat = null;
        }

          $s=$pdo->prepare('UPDATE recorrencias_pf SET nome=:nome,categoria_id=:cat,valor=:valor,tipo=:tipo,periodicidade=:per,intervalo_dias=:inter,dia_vencimento=:dia WHERE id=:id AND usuario_id=:uid');

        $s->execute(['nome'=>$nome,'cat'=>$cat,'valor'=>$valor,'tipo'=>$tipo,'per'=>$per,'inter'=>$inter,'dia'=>$dia,'id'=>$id,'uid'=>$uid]);

        $_SESSION['mensagem_pf']='Recorrência atualizada.';

        $retorno='recorrencias.php';

    }

    if($acao==='remover_recorrencia') {

        $s=$pdo->prepare('UPDATE recorrencias_pf SET ativo=0 WHERE id=:id AND usuario_id=:uid');

        $s->execute(['id'=>(int)($_POST['id']??0),'uid'=>$uid]);

        $_SESSION['mensagem_pf']='Recorrência desativada.';

        $retorno='recorrencias.php';

    }

    if($acao==='salvar_planejamento') {

          $comp=preg_match('/^\d{4}-\d{2}$/',$_POST['competencia']??'')?$_POST['competencia']:date('Y-m',strtotime('first day of next month'));

        $rec=max(0,(float)str_replace(',','.',$_POST['receita_esperada']??0));

        $fix=max(0,(float)str_replace(',','.',$_POST['gastos_fixos_estimados']??0));

        $met=max(0,(float)str_replace(',','.',$_POST['valor_metas']??0));

        $obs=trim($_POST['observacao']??'')?:null;

          $s=$pdo->prepare('INSERT INTO planejamento_mensal (usuario_id,competencia,receita_esperada,gastos_fixos_estimados,valor_metas,observacao) VALUES (:uid,:c,:r,:g,:m,:o) ON DUPLICATE KEY UPDATE receita_esperada=VALUES(receita_esperada),gastos_fixos_estimados=VALUES(gastos_fixos_estimados),valor_metas=VALUES(valor_metas),observacao=VALUES(observacao)');

        $s->execute(['uid'=>$uid,'c'=>$comp,'r'=>$rec,'g'=>$fix,'m'=>$met,'o'=>$obs]);

        $_SESSION['mensagem_pf']='Planejamento salvo.';

        $retorno='planejamento.php?competencia='.urlencode($comp);

    }

    if($acao==='salvar_financiamento') {

          $nome=trim($_POST['nome']??'Simulação');

        $bem=max(0,(float)$_POST['valor_bem']);

        $ent=max(0,(float)$_POST['entrada']);

        $tax=max(0,(float)$_POST['taxa_mensal']);

        $n=max(1,(int)$_POST['parcelas']);

        $fin=max(0,$bem-$ent);

        $i=$tax/100;

        $parc=$i>0?($fin*$i*pow(1+$i,$n))/(pow(1+$i,$n)-1):$fin/$n;

        $total=$ent+$parc*$n;

        $juros=max(0,$total-$bem);

          $s=$pdo->prepare('INSERT INTO financiamentos_simulados (usuario_id,nome,valor_bem,entrada,taxa_mensal,parcelas,valor_financiado,valor_parcela,total_pago,total_juros) VALUES (:uid,:nome,:bem,:ent,:tax,:n,:fin,:parc,:total,:juros)');

        $s->execute(['uid'=>$uid,'nome'=>$nome,'bem'=>$bem,'ent'=>$ent,'tax'=>$tax,'n'=>$n,'fin'=>$fin,'parc'=>$parc,'total'=>$total,'juros'=>$juros]);

        $_SESSION['mensagem_pf']='Simulação salva.';

        $retorno='financiamentos.php';

    }

    if($acao==='excluir_financiamento') {

        $s=$pdo->prepare('DELETE FROM financiamentos_simulados WHERE id=:id AND usuario_id=:uid');

        $s->execute(['id'=>(int)($_POST['id']??0),'uid'=>$uid]);

        $_SESSION['mensagem_pf']='Simulação removida.';

        $retorno='financiamentos.php';

    }

}
catch(Throwable $e) {

    error_log('CashPilot/PF: '.$e->getMessage());

    $_SESSION['mensagem_pf']='Não foi possível concluir a operação. Verifique a migration do CashPilot 9.';

}

header('Location: ../pages/'.$retorno);

exit;
