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

$filtroDescricao = trim($_GET['descricao'] ?? '');
$filtroCategoria = (int) ($_GET['categoria_filtro'] ?? 0);
$filtroConta = (int) ($_GET['conta_filtro'] ?? 0);
$filtroInicio = $_GET['data_inicio'] ?? '';
$filtroFim = $_GET['data_fim'] ?? '';

$sql = 'SELECT r.id, r.valor, r.descricao, r.data_receita, c.nome AS categoria_nome, co.nome AS conta_nome
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
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$receitas = $stmt->fetchAll();
$totalReceitas = array_sum(array_column($receitas, 'valor'));

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
        <p>Registre e acompanhe todas as suas entradas de dinheiro.</p>
    </div>
</div>

<form method="GET" class="cartao filtros-movimentacoes">
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
                        <td><?= limpar($r['descricao']) ?></td>
                        <td><span class="badge"><?= limpar($r['categoria_nome'] ?? 'Sem categoria') ?></span></td>
                        <td><?= limpar($r['conta_nome'] ?? 'Sem conta') ?></td>
                        <td><?= date('d/m/Y', strtotime($r['data_receita'])) ?></td>
                        <td><?= formatarMoeda((float) $r['valor']) ?></td>
                        <td>
                            <div class="acoes-linha">
                                <a href="receitas.php?editar=<?= $r['id'] ?>">Editar</a>
                                <form action="../actions/receitas.php" method="POST" onsubmit="return confirm('Excluir esta receita?');" style="display:inline">
                                    <?= csrfCampo() ?>
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button type="submit" class="excluir">Excluir</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="cartao">
        <h3 style="margin-bottom:16px;"><?= $edicao ? 'Editar receita' : 'Nova receita' ?></h3>
        <form action="../actions/receitas.php" method="POST">
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="<?= $edicao ? 'editar' : 'criar' ?>">
            <?php if ($edicao): ?>
                <input type="hidden" name="id" value="<?= $edicao['id'] ?>">
            <?php endif; ?>

            <div class="form-grupo">
                <label for="descricao">Descrição</label>
                <input type="text" id="descricao" name="descricao" required value="<?= limpar($edicao['descricao'] ?? '') ?>">
            </div>
            <div class="form-grupo">
                <label for="valor">Valor (R$)</label>
                <input type="number" step="0.01" min="0.01" id="valor" name="valor" required value="<?= $edicao['valor'] ?? '' ?>">
            </div>
            <div class="form-grupo">
                <label for="categoria_id">Categoria</label>
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
