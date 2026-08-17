<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$tituloPagina = 'Receitas';
$pdo = conectar();
$usuarioId = usuarioLogadoId();

$categorias = $pdo->prepare('SELECT id, nome FROM categorias WHERE usuario_id = :uid AND tipo = "receita" ORDER BY nome');
$categorias->execute(['uid' => $usuarioId]);
$categorias = $categorias->fetchAll();

$stmtContas = $pdo->prepare('SELECT id, nome FROM contas WHERE usuario_id = :uid ORDER BY padrao DESC, nome');
$stmtContas->execute(['uid' => $usuarioId]);
$contas = $stmtContas->fetchAll();
$empreendedor = usuarioLogadoTipo() === 'mei';
$itensVenda = [];
if ($empreendedor) {
    try { $st=$pdo->prepare('SELECT id,nome,tipo,preco_venda,custo_unitario FROM produtos_servicos WHERE usuario_id=:uid AND ativo=1 ORDER BY tipo,nome'); $st->execute(['uid'=>$usuarioId]); $itensVenda=$st->fetchAll(); } catch(Throwable $e) {}
}

$filtroDescricao = trim($_GET['descricao'] ?? '');
$filtroCategoria = (int) ($_GET['categoria_filtro'] ?? 0);
$filtroConta = (int) ($_GET['conta_filtro'] ?? 0);
$filtroInicio = $_GET['data_inicio'] ?? '';
$filtroFim = $_GET['data_fim'] ?? '';

$porPagina = 20;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

$sql = 'SELECT r.id, r.valor, r.descricao, r.data_receita, r.venda_id, c.nome AS categoria_nome, co.nome AS conta_nome
     FROM receitas r
     LEFT JOIN categorias c ON c.id = r.categoria_id
     LEFT JOIN contas co ON co.id = r.conta_id
     WHERE r.usuario_id = :uid';
$params = ['uid' => $usuarioId];
if ($filtroDescricao !== '') { $sql .= ' AND r.descricao LIKE :descricao'; $params['descricao'] = '%' . $filtroDescricao . '%'; }
if ($filtroCategoria > 0) { $sql .= ' AND r.categoria_id = :categoria'; $params['categoria'] = $filtroCategoria; }
if ($filtroConta > 0) { $sql .= ' AND r.conta_id = :conta'; $params['conta'] = $filtroConta; }
if ($filtroInicio !== '') { $sql .= ' AND r.data_receita >= :inicio'; $params['inicio'] = $filtroInicio; }
if ($filtroFim !== '') { $sql .= ' AND r.data_receita <= :fim'; $params['fim'] = $filtroFim; }
$sql .= ' ORDER BY r.data_receita DESC, r.id DESC';
$countSql = preg_replace('/^SELECT .*? FROM /s', 'SELECT COUNT(*) FROM ', $sql);
$countSql = preg_replace('/ ORDER BY .*$/s', '', $countSql);
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalRegistros = (int) $stmtCount->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalRegistros / $porPagina));
$pagina = min($pagina, $totalPaginas);
$offset = ($pagina - 1) * $porPagina;
$sql .= ' LIMIT ' . $porPagina . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$receitas = $stmt->fetchAll();

$totalSql = preg_replace('/^SELECT .*? FROM /s', 'SELECT COALESCE(SUM(r.valor),0) FROM ', $sql);
$totalSql = preg_replace('/ LIMIT .*$/s', '', $totalSql);
$stmtTotal = $pdo->prepare($totalSql);
$stmtTotal->execute($params);
$totalReceitas = (float) $stmtTotal->fetchColumn();

$edicao = null;
if (isset($_GET['editar'])) {
    $stmtEdit = $pdo->prepare('SELECT * FROM receitas WHERE id = :id AND usuario_id = :uid');
    $stmtEdit->execute(['id' => (int) $_GET['editar'], 'uid' => $usuarioId]);
    $edicao = $stmtEdit->fetch();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="topo-pagina">
    <div>
        <h1>Receitas</h1>
        <p><?= $empreendedor ? 'Registre vendas, serviços e outras entradas do negócio.' : 'Registre e acompanhe todas as suas entradas de dinheiro.' ?></p>
    </div>
</div>

<form autocomplete="off" method="GET" class="cartao filtros-movimentacoes">
    <div class="form-filtros">
        <div class="form-grupo"><label for="descricao">Descrição</label><input id="descricao" name="descricao" value="<?= limpar($filtroDescricao) ?>" placeholder="Ex.: salário"></div>
        <div class="form-grupo"><label for="categoria_filtro">Categoria</label><select id="categoria_filtro" name="categoria_filtro"><option value="0">Todas</option><?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>" <?= $filtroCategoria === (int) $c['id'] ? 'selected' : '' ?>><?= limpar($c['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="form-grupo"><label for="conta_filtro">Conta</label><select id="conta_filtro" name="conta_filtro"><option value="0">Todas</option><?php foreach ($contas as $conta): ?><option value="<?= $conta['id'] ?>" <?= $filtroConta === (int) $conta['id'] ? 'selected' : '' ?>><?= limpar($conta['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="form-grupo"><label for="data_inicio">De</label><input type="date" id="data_inicio" name="data_inicio" value="<?= limpar($filtroInicio) ?>"></div>
        <div class="form-grupo"><label for="data_fim">Até</label><input type="date" id="data_fim" name="data_fim" value="<?= limpar($filtroFim) ?>"></div>
        <button type="submit" class="btn btn-primario">Filtrar</button>
    </div>
    <?php if ($filtroDescricao || $filtroCategoria || $filtroConta || $filtroInicio || $filtroFim): ?><a href="receitas.php" class="link-limpar">Limpar filtros</a><?php endif; ?>
</form>


<div class="cartao inline-criacao" id="novaCategoriaReceita" hidden><form action="../actions/categorias.php" method="POST" autocomplete="off"><?=csrfCampo()?><input type="hidden" name="acao" value="criar"><input type="hidden" name="tipo" value="receita"><input type="hidden" name="retorno" value="receitas.php"><div class="inline-criacao-grid"><input name="nome" placeholder="Nome da nova categoria" required><input type="color" name="cor" value="#2F5D62"><button class="btn btn-primario">Adicionar categoria</button></div></form></div>
<div class="grade-dupla">
    <div class="cartao">
        <p class="resumo-lista">Total recebido no período: <strong class="positivo"><?= formatarMoeda((float) $totalReceitas) ?></strong></p>
        <?php if (empty($receitas)): ?>
            <p class="texto-vazio">Nenhuma receita cadastrada ainda.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Descrição</th><th>Categoria</th><th>Conta</th><th>Data</th><th>Valor</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($receitas as $r): ?>
                    <tr>
                        <td><?= limpar($r['descricao']) ?><?php if(!empty($r['venda_id'])):?><small class="origem-mov">Venda vinculada</small><?php endif;?></td>
                        <td><span class="badge"><?= limpar($r['categoria_nome'] ?? 'Sem categoria') ?></span></td>
                        <td><?= limpar($r['conta_nome'] ?? 'Sem conta') ?></td>
                        <td><?= date('d/m/Y', strtotime($r['data_receita'])) ?></td>
                        <td><?= formatarMoeda((float) $r['valor']) ?></td>
                        <td>
                            <div class="acoes-linha">
                                <?php if(empty($r['venda_id'])): ?>
                                <a href="receitas.php?editar=<?= $r['id'] ?>">Editar</a>
                                <form autocomplete="off" action="../actions/receitas.php" method="POST" onsubmit="return confirm('Excluir esta receita?');" style="display:inline">
                                    <?= csrfCampo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button type="submit" class="excluir">Excluir</button>
                                </form>
                                <?php else: ?><span class="badge">Venda</span><?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php if ($totalPaginas > 1): ?>
            <div class="paginacao-importacao">
                <a class="btn btn-secundario" href="?<?= http_build_query(array_merge($_GET, ['pagina' => max(1, $pagina - 1)])) ?>">‹</a>
                <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
                <a class="btn btn-secundario" href="?<?= http_build_query(array_merge($_GET, ['pagina' => min($totalPaginas, $pagina + 1)])) ?>">›</a>
            </div>
        <?php endif; ?>
    </div>

    <div class="cartao">
        <h3 style="margin-bottom:16px;"><?= $edicao ? 'Editar receita' : 'Nova receita' ?></h3>
        <form autocomplete="off" action="../actions/receitas.php" method="POST">
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="<?= $edicao ? 'editar' : 'criar' ?>">
            <?php if ($edicao): ?>
                <input type="hidden" name="id" value="<?= $edicao['id'] ?>">
            <?php endif; ?>

            <?php if($empreendedor && !$edicao && $itensVenda): ?>
            <div class="venda-rapida">
                <div class="form-grupo"><label for="item_venda_id">Vincular a uma venda (opcional)</label><select id="item_venda_id" name="item_venda_id"><option value="">Entrada avulsa</option><?php foreach($itensVenda as $i):?><option value="<?=$i['id']?>" data-tipo="<?=limpar($i['tipo'])?>" data-preco="<?=$i['preco_venda']?>"><?=limpar($i['nome'])?> · <?=ucfirst($i['tipo'])?> · <?=formatarMoeda((float)$i['preco_venda'])?></option><?php endforeach;?></select></div>
                <div class="form-grupo" id="grupoQuantidadeVenda"><label for="quantidade_venda">Quantidade</label><input type="number" min="1" name="quantidade_venda" id="quantidade_venda" value="1"><small class="secao-ajuda">Serviços são registrados como uma execução; produtos permitem várias unidades.</small></div>
            </div>
            <?php endif; ?>

            <div class="form-grupo">
                <label for="descricao">Descrição</label>
                <input autocomplete="off" type="text" id="descricao" name="descricao" required value="<?= limpar($edicao['descricao'] ?? '') ?>">
            </div>
            <div class="form-grupo">
                <label for="valor">Valor (R$)</label>
                <input autocomplete="off" type="number" step="0.01" min="0.01" id="valor" name="valor" required value="<?= $edicao['valor'] ?? '' ?>">
            </div>
            <div class="form-grupo">
                <div class="label-com-acao"><label for="categoria_id">Categoria</label><button type="button" class="link-botao" data-toggle-inline="novaCategoriaReceita">+ Nova categoria</button></div>
                <select id="categoria_id" name="categoria_id"><?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>" <?= (isset($edicao['categoria_id']) && $edicao['categoria_id'] == $c['id']) ? 'selected' : '' ?>><?= limpar($c['nome']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-grupo">
                <label for="conta_id">Conta</label>
                <select id="conta_id" name="conta_id"><option value="">Sem conta</option><?php foreach ($contas as $conta): ?><option value="<?= $conta['id'] ?>" <?= (isset($edicao['conta_id']) && (int) $edicao['conta_id'] === (int) $conta['id']) ? 'selected' : '' ?>><?= limpar($conta['nome']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-grupo">
                <label for="data_receita">Data</label>
                <input type="date" id="data_receita" name="data_receita" required value="<?= $edicao['data_receita'] ?? date('Y-m-d') ?>">
            </div>
            <button type="submit" class="btn btn-primario btn-bloco"><?= $edicao ? 'Salvar alterações' : 'Adicionar receita' ?></button>
            <?php if ($edicao): ?>
                <a href="receitas.php" class="btn btn-secundario btn-bloco" style="margin-top:10px;">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>
</div>
<script>
document.querySelectorAll('[data-toggle-inline]').forEach(b=>b.addEventListener('click',()=>{const e=document.getElementById(b.dataset.toggleInline);if(e)e.hidden=!e.hidden;}));
const itemVenda=document.getElementById('item_venda_id'),qtdVenda=document.getElementById('quantidade_venda'),valorVenda=document.getElementById('valor'),descVenda=document.getElementById('descricao');
function syncVenda(){if(!itemVenda||!qtdVenda)return;const op=itemVenda.selectedOptions[0];if(!op||!op.value)return;const servico=op.dataset.tipo==='servico';if(servico){qtdVenda.value=1;qtdVenda.readOnly=true;}else qtdVenda.readOnly=false;const qtd=Math.max(1,Number(qtdVenda.value||1));if(valorVenda)valorVenda.value=(Number(op.dataset.preco||0)*qtd).toFixed(2);if(descVenda)descVenda.value=op.textContent.split(' · ')[0].trim()+(servico?'':' x '+qtd);}
itemVenda?.addEventListener('change',syncVenda);qtdVenda?.addEventListener('input',syncVenda);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
