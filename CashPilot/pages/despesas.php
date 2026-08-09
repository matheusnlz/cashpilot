<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$tituloPagina = 'Despesas';
$pdo = conectar();
$usuarioId = usuarioLogadoId();

$categorias = $pdo->prepare('SELECT id, nome FROM categorias WHERE usuario_id = :uid AND tipo = "despesa" ORDER BY nome');
$categorias->execute(['uid' => $usuarioId]);
$categorias = $categorias->fetchAll();

$stmt = $pdo->prepare(
    'SELECT d.id, d.valor, d.descricao, d.data_despesa, c.nome AS categoria_nome
     FROM despesas d
     LEFT JOIN categorias c ON c.id = d.categoria_id
     WHERE d.usuario_id = :uid
     ORDER BY d.data_despesa DESC, d.id DESC'
);
$stmt->execute(['uid' => $usuarioId]);
$despesas = $stmt->fetchAll();

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

<div class="grade-dupla">
    <div class="cartao">
        <?php if (empty($despesas)): ?>
            <p class="texto-vazio">Nenhuma despesa cadastrada ainda.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr><th>Descrição</th><th>Categoria</th><th>Data</th><th>Valor</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($despesas as $d): ?>
                    <tr>
                        <td><?= limpar($d['descricao']) ?></td>
                        <td><span class="badge"><?= limpar($d['categoria_nome'] ?? 'Sem categoria') ?></span></td>
                        <td><?= date('d/m/Y', strtotime($d['data_despesa'])) ?></td>
                        <td><?= formatarMoeda((float) $d['valor']) ?></td>
                        <td>
                            <div class="acoes-linha">
                                <a href="despesas.php?editar=<?= $d['id'] ?>">Editar</a>
                                <form action="../actions/despesas.php" method="POST" onsubmit="return confirm('Excluir esta despesa?');" style="display:inline">
                                    <input type="hidden" name="acao" value="excluir">
                                    <input type="hidden" name="id" value="<?= $d['id'] ?>">
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
        <h3 style="margin-bottom:16px;"><?= $edicao ? 'Editar despesa' : 'Nova despesa' ?></h3>
        <form action="../actions/despesas.php" method="POST">
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
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
