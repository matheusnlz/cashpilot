<?php
require_once __DIR__.'/../includes/auth.php';

require_once __DIR__.'/../database/conexao.php';

require_once __DIR__.'/../includes/inteligencia_financeira.php';

exigirLogin();
exigirPost();

validarCsrf();

if(usuarioLogadoTipo()!=='pessoa_fisica') {

    header('Location: ../pages/dashboard.php');

    exit;

}

$pdo=conectar();

$uid=(int)usuarioLogadoId();

$acao=$_POST['acao']??'';

try {

     if($acao==='salvar_reserva') {

           $valor=max(0,(float)str_replace(',','.',$_POST['valor_atual']??0));

        $meses=max(1,min(24,(int)($_POST['meses_objetivo']??6)));

           $s=$pdo->prepare('INSERT INTO reserva_emergencia (usuario_id,valor_atual,meses_objetivo) VALUES (:uid,:v,:m) ON DUPLICATE KEY UPDATE valor_atual=VALUES(valor_atual),meses_objetivo=VALUES(meses_objetivo)');

           $s->execute(['uid'=>$uid,'v'=>$valor,'m'=>$meses]);

        $_SESSION['mensagem_saude']='Reserva atualizada.';

    }

     if($acao==='criar_desafio') {

           $titulo=trim($_POST['titulo']??'');

        $valor=max(.01,(float)str_replace(',','.',$_POST['valor_objetivo']??0));

        $dias=max(7,min(365,(int)($_POST['dias']??30)));

           if($titulo!==''&&$valor>0) {

            $dataFim=date('Y-m-d',strtotime('+'.$dias.' days'));

            $s=$pdo->prepare('INSERT INTO desafios_economia (usuario_id,titulo,valor_objetivo,data_inicio,data_fim) VALUES (:uid,:t,:v,CURDATE(),:fim)');

            $s->execute(['uid'=>$uid,'t'=>$titulo,'v'=>$valor,'fim'=>$dataFim]);

            $_SESSION['mensagem_saude']='Desafio criado.';

        }

    }

     if($acao==='atualizar_desafio') {

           $id=(int)($_POST['id']??0);

        $valor=max(0,(float)str_replace(',','.',$_POST['valor_economizado']??0));

           $s=$pdo->prepare('UPDATE desafios_economia SET valor_economizado=:v,status=CASE WHEN :v2>=valor_objetivo THEN "concluido" ELSE status END WHERE id=:id AND usuario_id=:uid');

        $s->execute(['v'=>$valor,'v2'=>$valor,'id'=>$id,'uid'=>$uid]);

        $_SESSION['mensagem_saude']='Desafio atualizado.';

    }

     if($acao==='cancelar_desafio') {

        $s=$pdo->prepare('UPDATE desafios_economia SET status="cancelado" WHERE id=:id AND usuario_id=:uid');

        $s->execute(['id'=>(int)($_POST['id']??0),'uid'=>$uid]);

    }

}
catch(Throwable $e) {

    error_log('CashPilot/Saude: '.$e->getMessage());

    $_SESSION['mensagem_saude']='Não foi possível concluir a operação. Verifique a migration 008.';

}

header('Location: ../pages/saude_financeira.php');

exit;
