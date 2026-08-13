<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$tituloPagina = 'Categorias';
$pdo = conectar();
$usuarioId = usuarioLogadoId();

$stmt = $pdo->prepare('SELECT * FROM categorias WHERE usuario_id = :uid ORDER BY tipo, nome');
$stmt->execute(['uid' => $usuarioId]);
$categorias = $stmt->fetchAll();
$receitasCat = array_filter($categorias, fn($c) => $c['tipo'] === 'receita');
$despesasCat = array_filter($categorias, fn($c) => $c['tipo'] === 'despesa');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="topo-pagina">
    <div>
        <h1>Categorias</h1>
        <p>Organize suas receitas e despesas por categoria.</p>
    </div>
</div>

<div class="grade-dupla">
    <div>
        <div class="cartao" style="margin-bottom:20px;">
            <h3 style="margin-bottom:14px;">Categorias de receita</h3>
            <?php foreach ($receitasCat as $c): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--cor-borda);">
                    <span><span class="badge" style="background:<?= $c['cor'] ?>22; color:<?= $c['cor'] ?>;"><?= limpar($c['nome']) ?></span></span>
                    <?php if (!$c['padrao']): ?>
                        <form action="../actions/categorias.php" method="POST" onsubmit="return confirm('Excluir categoria?');">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="excluir" style="border:none;background:none;font-size:12px;">Excluir</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="cartao">
            <h3 style="margin-bottom:14px;">Categorias de despesa</h3>
            <?php foreach ($despesasCat as $c): ?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--cor-borda);">
                    <span><span class="badge" style="background:<?= $c['cor'] ?>22; color:<?= $c['cor'] ?>;"><?= limpar($c['nome']) ?></span></span>
                    <?php if (!$c['padrao']): ?>
                        <form action="../actions/categorias.php" method="POST" onsubmit="return confirm('Excluir categoria?');">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <button type="submit" class="excluir" style="border:none;background:none;font-size:12px;">Excluir</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="cartao">
        <h3 style="margin-bottom:16px;">Nova categoria</h3>
        <form action="../actions/categorias.php" method="POST">
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="criar">
            <div class="form-grupo">
                <label for="nome">Nome</label>
                <input type="text" id="nome" name="nome" required>
            </div>
            <div class="form-grupo">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo">
                    <option value="receita">Receita</option>
                    <option value="despesa">Despesa</option>
                </select>
            </div>
            <div class="form-grupo">
                <label for="cor">Cor</label>
                <input type="color" id="cor" name="cor" value="#2F5D62">
            </div>
            <button type="submit" class="btn btn-primario btn-bloco">Adicionar categoria</button>
        </form>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
