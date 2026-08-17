<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/negocio_financeiro.php';
exigirLogin();

$tituloPagina = 'Despesas';
$pdo = conectar();
$usuarioId = usuarioLogadoId();
if (usuarioLogadoTipo()==='mei') cpSincronizarCustosRecorrentesMes($pdo,$usuarioId);

$categorias = $pdo->prepare('SELECT id, nome FROM categorias WHERE usuario_id = :uid AND tipo = "despesa" ORDER BY nome');
$categorias->execute(['uid' => $usuarioId]);
$categorias = $categorias->fetchAll();

$stmtContas = $pdo->prepare('SELECT id, nome FROM contas WHERE usuario_id = :uid ORDER BY padrao DESC, nome');
$stmtContas->execute(['uid' => $usuarioId]);
$contas = $stmtContas->fetchAll();
$filtroDescricao = trim($_GET['descricao'] ?? '');
$filtroCategoria = (int) ($_GET['categoria_filtro'] ?? 0);
$filtroConta = (int) ($_GET['conta_filtro'] ?? 0);
$filtroInicio = $_GET['data_inicio'] ?? '';
$filtroFim = $_GET['data_fim'] ?? '';

$porPagina = 20;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

$sql = 'SELECT d.id, d.valor, d.descricao, d.data_despesa, c.nome AS categoria_nome, co.nome AS conta_nome, d.origem_tipo
     FROM despesas d
     LEFT JOIN categorias c ON c.id = d.categoria_id
     LEFT JOIN contas co ON co.id = d.conta_id
     WHERE d.usuario_id = :uid';
$params = ['uid' => $usuarioId];
if ($filtroDescricao !== '') { $sql .= ' AND d.descricao LIKE :descricao'; $params['descricao'] = '%' . $filtroDescricao . '%'; }
if ($filtroCategoria > 0) { $sql .= ' AND d.categoria_id = :categoria'; $params['categoria'] = $filtroCategoria; }
if ($filtroConta > 0) { $sql .= ' AND d.conta_id = :conta'; $params['conta'] = $filtroConta; }
if ($filtroInicio !== '') { $sql .= ' AND d.data_despesa >= :inicio'; $params['inicio'] = $filtroInicio; }
if ($filtroFim !== '') { $sql .= ' AND d.data_despesa <= :fim'; $params['fim'] = $filtroFim; }
$sql .= ' ORDER BY d.data_despesa DESC, d.id DESC';
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
$despesas = $stmt->fetchAll();

$totalSql = preg_replace('/^SELECT .*? FROM /s', 'SELECT COALESCE(SUM(d.valor),0) FROM ', $sql);
$totalSql = preg_replace('/ LIMIT .*$/s', '', $totalSql);
$stmtTotal = $pdo->prepare($totalSql);
$stmtTotal->execute($params);
$totalDespesas = (float) $stmtTotal->fetchColumn();

$edicao = null;
if (isset($_GET['editar'])) {
    $stmtEdit = $pdo->prepare('SELECT * FROM despesas WHERE id = :id AND usuario_id = :uid');
    $stmtEdit->execute(['id' => (int) $_GET['editar'], 'uid' => $usuarioId]);
    $edicao = $stmtEdit->fetch();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="topo-pagina">
    <div>
        <h1>Despesas</h1>
        <p>Registre e acompanhe todas as suas saídas de dinheiro.</p>
    </div>
</div>

<form autocomplete="off" method="GET" class="cartao filtros-movimentacoes">
    <div class="form-filtros">
        <div class="form-grupo"><label for="descricao">Descrição</label><input id="descricao" name="descricao" value="<?= limpar($filtroDescricao) ?>" placeholder="Ex.: mercado"></div>
        <div class="form-grupo"><label for="categoria_filtro">Categoria</label><select id="categoria_filtro" name="categoria_filtro"><option value="0">Todas</option><?php foreach ($categorias as $c): ?><option value="<?= $c['id'] ?>" <?= $filtroCategoria === (int) $c['id'] ? 'selected' : '' ?>><?= limpar($c['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="form-grupo"><label for="conta_filtro">Conta</label><select id="conta_filtro" name="conta_filtro"><option value="0">Todas</option><?php foreach ($contas as $conta): ?><option value="<?= $conta['id'] ?>" <?= $filtroConta === (int) $conta['id'] ? 'selected' : '' ?>><?= limpar($conta['nome']) ?></option><?php endforeach; ?></select></div>
        <div class="form-grupo"><label for="data_inicio">De</label><input type="date" id="data_inicio" name="data_inicio" value="<?= limpar($filtroInicio) ?>"></div>
        <div class="form-grupo"><label for="data_fim">Até</label><input type="date" id="data_fim" name="data_fim" value="<?= limpar($filtroFim) ?>"></div>
        <button type="submit" class="btn btn-primario">Filtrar</button>
    </div>
    <?php if ($filtroDescricao || $filtroCategoria || $filtroConta || $filtroInicio || $filtroFim): ?><a href="despesas.php" class="link-limpar">Limpar filtros</a><?php endif; ?>
</form>


<div class="cartao inline-criacao" id="novaCategoriaDespesa" hidden><form action="../actions/categorias.php" method="POST" autocomplete="off"><?=csrfCampo()?><input type="hidden" name="acao" value="criar"><input type="hidden" name="tipo" value="despesa"><input type="hidden" name="retorno" value="despesas.php"><div class="inline-criacao-grid"><input name="nome" placeholder="Nome da nova categoria" required><input type="color" name="cor" value="#B5654A"><button class="btn btn-primario">Adicionar categoria</button></div></form></div>
<div class="grade-dupla">
    <div class="cartao">
        <p class="resumo-lista">Total gasto no período: <strong class="negativo"><?= formatarMoeda((float) $totalDespesas) ?></strong></p>
        <?php if (empty($despesas)): ?>
            <p class="texto-vazio">Nenhuma despesa cadastrada ainda.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Descrição</th><th>Categoria</th><th>Conta</th><th>Data</th><th>Valor</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($despesas as $d): ?>
                    <tr>
                        <td><?= limpar($d['descricao']) ?><?php if(($d['origem_tipo']??'manual')!=='manual'):?><small class="origem-mov"><?=limpar(ucfirst(str_replace('_',' ',$d['origem_tipo'])))?></small><?php endif;?></td>
                        <td><span class="badge"><?= limpar($d['categoria_nome'] ?? 'Sem categoria') ?></span></td>
                        <td><?= limpar($d['conta_nome'] ?? 'Sem conta') ?></td>
                        <td><?= date('d/m/Y', strtotime($d['data_despesa'])) ?></td>
                        <td><?= formatarMoeda((float) $d['valor']) ?></td>
                        <td>
                            <div class="acoes-linha">
                                <?php if(($d['origem_tipo']??'manual')==='manual'): ?>
                                <a href="despesas.php?editar=<?= $d['id'] ?>">Editar</a>
                                <form autocomplete="off" action="../actions/despesas.php" method="POST" onsubmit="return confirm('Excluir esta despesa?');" style="display:inline">
                                    <?= csrfCampo() ?><input type="hidden" name="acao" value="excluir"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button type="submit" class="excluir">Excluir</button>
                                </form>
                                <?php else: ?><span class="badge">Automática</span><?php endif; ?>
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
        <h3 style="margin-bottom:16px;"><?= $edicao ? 'Editar despesa' : 'Nova despesa' ?></h3>
        <form autocomplete="off" action="../actions/despesas.php" method="POST">
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="<?= $edicao ? 'editar' : 'criar' ?>">
            <?php if ($edicao): ?>
                <input type="hidden" name="id" value="<?= $edicao['id'] ?>">
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
                <div class="label-com-acao"><label for="categoria_id">Categoria</label><button type="button" class="link-botao" data-toggle-inline="novaCategoriaDespesa">+ Nova categoria</button></div>
                <select id="categoria_id" name="categoria_id">
                    <?php foreach ($categorias as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= (isset($edicao['categoria_id']) && $edicao['categoria_id'] == $c['id']) ? 'selected' : '' ?>>
                            <?= limpar($c['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-grupo">
                <label for="conta_id">Conta</label>
                <select id="conta_id" name="conta_id"><option value="">Sem conta</option><?php foreach ($contas as $conta): ?><option value="<?= $conta['id'] ?>" <?= (isset($edicao['conta_id']) && (int) $edicao['conta_id'] === (int) $conta['id']) ? 'selected' : '' ?>><?= limpar($conta['nome']) ?></option><?php endforeach; ?></select>
            </div>
            <div class="form-grupo">
                <label for="data_despesa">Data</label>
                <input type="date" id="data_despesa" name="data_despesa" required value="<?= $edicao['data_despesa'] ?? date('Y-m-d') ?>">
            </div>
            <button type="submit" class="btn btn-primario btn-bloco"><?= $edicao ? 'Salvar alterações' : 'Adicionar despesa' ?></button>
            <?php if ($edicao): ?>
                <a href="despesas.php" class="btn btn-secundario btn-bloco" style="margin-top:10px;">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>
</div>
<script>document.querySelectorAll('[data-toggle-inline]').forEach(b=>b.addEventListener('click',()=>{const e=document.getElementById(b.dataset.toggleInline);if(e)e.hidden=!e.hidden;}));</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
