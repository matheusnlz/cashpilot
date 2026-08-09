<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$tituloPagina = 'Revisar importação';
$pdo = conectar();
$usuarioId = usuarioLogadoId();

$preview = $_SESSION['importacao_preview'] ?? null;
if (!$preview || empty($preview['linhas'])) {
    header('Location: ../pages/importar.php');
    exit;
}

$stmt = $pdo->prepare('SELECT id, nome FROM categorias WHERE usuario_id = :uid ORDER BY tipo, nome');
$stmt->execute(['uid' => $usuarioId]);
$todasCategorias = $stmt->fetchAll();
$categoriasReceita = array_filter($todasCategorias, fn($c) => true); // filtragem por tipo é feita no JS via data-tipo

$stmtCatTipos = $pdo->prepare('SELECT id, nome, tipo FROM categorias WHERE usuario_id = :uid ORDER BY nome');
$stmtCatTipos->execute(['uid' => $usuarioId]);
$categorias = $stmtCatTipos->fetchAll();

$stmt = $pdo->prepare('SELECT id, nome FROM contas WHERE id = :id AND usuario_id = :uid');
$stmt->execute(['id' => $preview['conta_id'], 'uid' => $usuarioId]);
$conta = $stmt->fetch();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="topo-pagina">
    <div>
        <h1>Revisar importação</h1>
        <p>
            <?= count($preview['linhas']) ?> movimentações encontradas em
            <strong><?= limpar($preview['nome_arquivo']) ?></strong>
            para a conta <strong><?= limpar($conta['nome'] ?? '') ?></strong>.
            Revise a classificação sugerida e desmarque o que não quiser importar.
        </p>
    </div>
</div>

<?php if (!empty($preview['quantidade_ignoradas'])): ?>
    <div class="alerta-mensagem erro" style="max-width:720px;">
        <?= $preview['quantidade_ignoradas'] ?> linha(s) do arquivo não puderam ser interpretadas e foram ignoradas automaticamente.
    </div>
<?php endif; ?>

<form action="../actions/importar_confirmar.php" method="POST">
    <div class="cartao">
        <table>
            <thead>
                <tr>
                    <th style="width:32px;"></th>
                    <th>Descrição</th>
                    <th>Data</th>
                    <th>Valor</th>
                    <th>Tipo</th>
                    <th>Categoria</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($preview['linhas'] as $i => $linha): ?>
                <tr style="<?= $linha['possivel_duplicata'] ? 'background:#FBF3E9;' : '' ?>">
                    <td>
                        <input type="checkbox" name="linhas[<?= $i ?>][incluir]" value="1" <?= $linha['possivel_duplicata'] ? '' : 'checked' ?>>
                    </td>
                    <td>
                        <?= limpar($linha['descricao']) ?>
                        <input type="hidden" name="linhas[<?= $i ?>][descricao]" value="<?= limpar($linha['descricao']) ?>">
                        <?php if ($linha['possivel_duplicata']): ?>
                            <div style="font-size:11px; color:var(--cor-alerta); margin-top:2px;">Possível duplicata — já existe um lançamento parecido</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= date('d/m/Y', strtotime($linha['data'])) ?>
                        <input type="hidden" name="linhas[<?= $i ?>][data]" value="<?= $linha['data'] ?>">
                    </td>
                    <td>
                        <?= formatarMoeda(abs((float) $linha['valor'])) ?>
                        <input type="hidden" name="linhas[<?= $i ?>][valor]" value="<?= abs((float) $linha['valor']) ?>">
                    </td>
                    <td>
                        <select name="linhas[<?= $i ?>][tipo]" class="seletor-tipo" data-linha="<?= $i ?>">
                            <option value="receita" <?= $linha['tipo'] === 'receita' ? 'selected' : '' ?>>Receita</option>
                            <option value="despesa" <?= $linha['tipo'] === 'despesa' ? 'selected' : '' ?>>Despesa</option>
                        </select>
                    </td>
                    <td>
                        <select name="linhas[<?= $i ?>][categoria_id]" class="seletor-categoria" data-linha="<?= $i ?>">
                            <?php foreach ($categorias as $c): ?>
                                <option value="<?= $c['id'] ?>" data-tipo="<?= $c['tipo'] ?>" <?= ((int) $linha['categoria_id'] === (int) $c['id']) ? 'selected' : '' ?>>
                                    <?= limpar($c['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div style="display:flex; gap:12px; margin-top:20px;">
        <button type="submit" class="btn btn-primario">Confirmar importação</button>
        <a href="../pages/importar.php" class="btn btn-secundario">Cancelar</a>
    </div>
</form>

<script>
    // Filtra as opções de categoria conforme o tipo selecionado em cada linha
    function filtrarCategorias(seletorTipo) {
        const linha = seletorTipo.dataset.linha;
        const tipo = seletorTipo.value;
        const seletorCategoria = document.querySelector(`.seletor-categoria[data-linha="${linha}"]`);
        let temSelecionada = false;

        Array.from(seletorCategoria.options).forEach(opcao => {
            const visivel = opcao.dataset.tipo === tipo;
            opcao.hidden = !visivel;
            if (visivel && !temSelecionada && opcao.selected) temSelecionada = true;
        });

        if (!temSelecionada) {
            const primeiraVisivel = Array.from(seletorCategoria.options).find(o => !o.hidden);
            if (primeiraVisivel) seletorCategoria.value = primeiraVisivel.value;
        }
    }

    document.querySelectorAll('.seletor-tipo').forEach(seletor => {
        filtrarCategorias(seletor);
        seletor.addEventListener('change', () => filtrarCategorias(seletor));
    });
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
