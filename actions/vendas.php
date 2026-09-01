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

$itens=$_POST['itens']??[];

$data=$_POST['data_venda']??date('Y-m-d');

$obs=trim($_POST['observacao']??'')?:null;

$normal=[];

foreach($itens as $id=>$q) {

    $id=(int)$id;

    $q=max(0,(int)$q);

    if($id>0&&$q>0)$normal[$id]=$q;

}

if(!$normal) {

    $_SESSION['mensagem_venda']='Adicione pelo menos um item à venda.';

    header('Location: ../pages/vendas.php');

    exit;

}

try {

    $ids=array_keys($normal);

    $ph=implode(',',array_fill(0,count($ids),'?'));

    $s=$pdo->prepare("SELECT id,nome,tipo,preco_venda,custo_unitario,estoque_atual,controlar_estoque FROM produtos_servicos WHERE usuario_id=? AND ativo=1 AND id IN ($ph)");

    $s->execute(array_merge([$uid],$ids));

    $catalogo=[];

    foreach($s->fetchAll() as $x)$catalogo[(int)$x['id']]=$x;

    $total=0;

    $custo=0;

    $descricao=[];

    foreach($normal as $id=>$q) {

        if(!isset($catalogo[$id]))continue;

        $x=$catalogo[$id];

        if($x['tipo']==='produto'&&!empty($x['controlar_estoque'])&&(int)$x['estoque_atual']<$q)throw new RuntimeException('Estoque insuficiente para '.$x['nome'].'.');

        $total+=(float)$x['preco_venda']*$q;

        $custo+=(float)$x['custo_unitario']*$q;

        $descricao[]=$x['nome'].' x'.$q;

    }

    if($total<=0)throw new RuntimeException('Venda sem valor.');

    $pdo->beginTransaction();

    $cat=cpCategoriaId($pdo,$uid,'Vendas','receita');

    $r=$pdo->prepare('INSERT INTO receitas (usuario_id,categoria_id,valor,descricao,data_receita) VALUES (:uid,:cat,:valor,:descricao,:data)');

    $r->execute(['uid'=>$uid,'cat'=>$cat,'valor'=>$total,'descricao'=>'Venda · '.implode(', ',$descricao),'data'=>$data]);

    $rid=(int)$pdo->lastInsertId();

    $v=$pdo->prepare('INSERT INTO vendas (usuario_id,receita_id,data_venda,valor_bruto,custo_total,observacao) VALUES (:uid,:rid,:data,:valor,:custo,:obs)');

    $v->execute(['uid'=>$uid,'rid'=>$rid,'data'=>$data,'valor'=>$total,'custo'=>$custo,'obs'=>$obs]);

    $vid=(int)$pdo->lastInsertId();

    $vi=$pdo->prepare('INSERT INTO venda_itens (venda_id,produto_servico_id,nome_item,tipo,quantidade,preco_unitario,custo_unitario) VALUES (:vid,:pid,:nome,:tipo,:qtd,:preco,:custo)');

    $baixa=$pdo->prepare('UPDATE produtos_servicos SET estoque_atual=GREATEST(0,estoque_atual-:qtd) WHERE id=:id AND usuario_id=:uid AND controlar_estoque=1 AND tipo="produto"');

    $movEstoque=$pdo->prepare('INSERT INTO movimentacoes_estoque (usuario_id,produto_id,tipo,quantidade,custo_unitario,referencia,venda_id,data_movimentacao) VALUES (:uid,:pid,"venda",:qtd,:custo,:ref,:vid,:data)');

    foreach($normal as $id=>$q) {

            if(!isset($catalogo[$id]))continue;

            $x=$catalogo[$id];

            $vi->execute([
                'vid'=>$vid,
                'pid'=>$id,
                'nome'=>$x['nome'],
                'tipo'=>$x['tipo'],
                'qtd'=>$q,
                'preco'=>$x['preco_venda'],
                'custo'=>$x['custo_unitario']
            ]);

            if($x['tipo']==='produto'&&!empty($x['controlar_estoque'])) {

                    $baixa->execute(['qtd'=>$q,'id'=>$id,'uid'=>$uid]);

                    $movEstoque->execute([
                        'uid'=>$uid,
                        'pid'=>$id,
                        'qtd'=>-$q,
                        'custo'=>$x['custo_unitario'],
                        'ref'=>'Venda #'.$vid,
                        'vid'=>$vid,
                        'data'=>$data.' 12:00:00'
                    ]);

        }

    }

    $pdo->commit();

    $_SESSION['mensagem_venda']='Venda registrada com '.count($normal).' tipo(s) de item.';

}
catch(Throwable $e) {

    if($pdo->inTransaction())$pdo->rollBack();

    error_log('CashPilot/Vendas: '.$e->getMessage());

    $_SESSION['mensagem_venda']='Não foi possível registrar a venda. Tente novamente.';

}

header('Location: ../pages/vendas.php');

exit;
