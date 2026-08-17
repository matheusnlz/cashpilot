<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/negocio_financeiro.php';
exigirLogin();
validarCsrf();

$pdo = conectar();
$usuarioId = usuarioLogadoId();
$acao = $_POST['acao'] ?? '';

if ($acao === 'excluir') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM receitas WHERE id = :id AND usuario_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $usuarioId]);
    header('Location: ../pages/receitas.php');
    exit;
}

if ($acao === 'criar' || $acao === 'editar') {
    $descricao   = trim($_POST['descricao'] ?? '');
    $valor       = (float) str_replace(',', '.', $_POST['valor'] ?? '0');
    $categoriaId = !empty($_POST['categoria_id']) ? (int) $_POST['categoria_id'] : null;
    $contaId     = !empty($_POST['conta_id']) ? (int) $_POST['conta_id'] : null;
    $data        = $_POST['data_receita'] ?? date('Y-m-d');

    if ($descricao === '' || $valor <= 0 || !validarVinculos($pdo, $usuarioId, $categoriaId, $contaId, 'receita')) {
        header('Location: ../pages/receitas.php');
        exit;
    }

    if ($acao === 'criar') {
        $itemVendaId = usuarioLogadoTipo()==='mei' ? (int)($_POST['item_venda_id'] ?? 0) : 0;
        if ($itemVendaId > 0) {
            $iv=$pdo->prepare('SELECT id,nome,tipo,preco_venda,custo_unitario FROM produtos_servicos WHERE id=:id AND usuario_id=:uid AND ativo=1');$iv->execute(['id'=>$itemVendaId,'uid'=>$usuarioId]);$item=$iv->fetch();
            if($item){$qtd=$item['tipo']==='servico'?1:max(1,(int)($_POST['quantidade_venda']??1));$valor=(float)$item['preco_venda']*$qtd;$descricao=$item['nome'].($item['tipo']==='produto'?' x '.$qtd:'');$categoriaId=cpCategoriaId($pdo,$usuarioId,$item['tipo']==='servico'?'Serviços':'Vendas','receita');
                $pdo->beginTransaction();
                $stmt=$pdo->prepare('INSERT INTO receitas (usuario_id,categoria_id,conta_id,valor,descricao,data_receita) VALUES (:uid,:cat,:conta,:valor,:descricao,:data)');$stmt->execute(['uid'=>$usuarioId,'cat'=>$categoriaId,'conta'=>!empty($_POST['conta_id'])?(int)$_POST['conta_id']:null,'valor'=>$valor,'descricao'=>$descricao,'data'=>$data]);$receitaId=(int)$pdo->lastInsertId();
                $v=$pdo->prepare('INSERT INTO vendas (usuario_id,receita_id,data_venda,valor_bruto,custo_total) VALUES (:uid,:rid,:data,:valor,:custo)');$v->execute(['uid'=>$usuarioId,'rid'=>$receitaId,'data'=>$data,'valor'=>$valor,'custo'=>(float)$item['custo_unitario']*$qtd]);$vendaId=(int)$pdo->lastInsertId();
                $vi=$pdo->prepare('INSERT INTO venda_itens (venda_id,produto_servico_id,nome_item,tipo,quantidade,preco_unitario,custo_unitario) VALUES (:vid,:pid,:nome,:tipo,:qtd,:preco,:custo)');$vi->execute(['vid'=>$vendaId,'pid'=>$itemVendaId,'nome'=>$item['nome'],'tipo'=>$item['tipo'],'qtd'=>$qtd,'preco'=>$item['preco_venda'],'custo'=>$item['custo_unitario']]);
                $pdo->prepare('UPDATE receitas SET venda_id=:vid WHERE id=:rid')->execute(['vid'=>$vendaId,'rid'=>$receitaId]);$pdo->commit();
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO receitas (usuario_id, categoria_id, conta_id, valor, descricao, data_receita) VALUES (:uid, :cat, :conta, :valor, :descricao, :data)');
            $stmt->execute(['uid'=>$usuarioId,'cat'=>$categoriaId,'conta'=>!empty($_POST['conta_id'])?(int)$_POST['conta_id']:null,'valor'=>$valor,'descricao'=>$descricao,'data'=>$data]);
        }
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare(
            'UPDATE receitas SET categoria_id = :cat, conta_id = :conta, valor = :valor, descricao = :descricao, data_receita = :data
             WHERE id = :id AND usuario_id = :uid'
        );
        $stmt->execute([
            'cat' => $categoriaId, 'conta' => $contaId, 'valor' => $valor, 'descricao' => $descricao,
            'data' => $data, 'id' => $id, 'uid' => $usuarioId,
        ]);
    }
}

function validarVinculos(PDO $pdo, int $usuarioId, ?int $categoriaId, ?int $contaId, string $tipo): bool
{
    if ($categoriaId !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM categorias WHERE id = :id AND usuario_id = :uid AND tipo = :tipo');
        $stmt->execute(['id' => $categoriaId, 'uid' => $usuarioId, 'tipo' => $tipo]);
        if (!$stmt->fetchColumn()) return false;
    }
    if ($contaId !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM contas WHERE id = :id AND usuario_id = :uid');
        $stmt->execute(['id' => $contaId, 'uid' => $usuarioId]);
        if (!$stmt->fetchColumn()) return false;
    }
    return true;
}

header('Location: ../pages/receitas.php');
exit;
