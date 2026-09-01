<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';

exigirLogin();

$tituloPagina = 'Revisar importação';
$pdo = conectar();
$usuarioId = (int) usuarioLogadoId();
$preview = $_SESSION['importacao_preview'] ?? null;

if (!$preview || empty($preview['linhas'])) {
    header('Location: ../pages/importar.php');
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, nome, tipo FROM categorias WHERE usuario_id = :uid ORDER BY tipo, nome'
);
$stmt->execute(['uid' => $usuarioId]);
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    'SELECT id, nome FROM contas WHERE id = :id AND usuario_id = :uid'
);
$stmt->execute(['id' => $preview['conta_id'], 'uid' => $usuarioId]);
$conta = $stmt->fetch(PDO::FETCH_ASSOC);

$stats = $preview['estatisticas'] ?? [];
$alta = (int) ($stats['alta'] ?? 0);
$media = (int) ($stats['media'] ?? 0);
$baixa = (int) ($stats['baixa'] ?? 0);
$duplicadas = (int) ($stats['duplicadas'] ?? 0);
$recorrencias = 0;
$precisamRevisao = 0;

foreach ($preview['linhas'] as $linha) {
    if (
        (int) ($linha['confianca_leitura'] ?? 0) < 70
        || ($linha['confianca'] ?? 'baixa') === 'baixa'
        || ($linha['categoria_nome'] ?? 'Outros') === 'Outros'
    ) {
        $precisamRevisao++;
    }
    if (!empty($linha['possivel_recorrencia'])) {
        $recorrencias++;
    }
}

$meta = is_array($preview['meta'] ?? null) ? $preview['meta'] : [];
$avisos = is_array($preview['avisos'] ?? null) ? $preview['avisos'] : [];
$formato = strtoupper((string) ($preview['formato'] ?? ''));
$motor = (string) ($meta['motor'] ?? ($formato === 'PDF' ? 'Leitor PDF local' : $formato));
$confiancaMedia = isset($meta['confianca_media']) ? (int) $meta['confianca_media'] : null;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="topo-pagina import-review-head">
    <div>
        <span class="eyebrow">CONFERÊNCIA ANTES DE SALVAR</span>
        <h1>Revisar importação</h1>
        <p>
            Encontramos <?= count($preview['linhas']) ?> movimentações em
            <strong><?= limpar($preview['nome_arquivo']) ?></strong>
            para <strong><?= limpar($conta['nome'] ?? '') ?></strong>.
            Corrija o que for necessário e confirme somente depois da revisão.
        </p>
    </div>
</div>

<div class="cp144-reading-meta surface-card">
    <div>
        <span>Formato</span>
        <strong><?= limpar($formato) ?></strong>
    </div>
    <div>
        <span>Motor de leitura</span>
        <strong><?= limpar($motor) ?></strong>
    </div>
    <?php if ($confiancaMedia !== null): ?>
        <div>
            <span>Confiança média da leitura</span>
            <strong><?= $confiancaMedia ?>%</strong>
        </div>
    <?php endif; ?>
    <?php if (!empty($preview['banco_pdf'])): ?>
        <div>
            <span>Banco detectado</span>
            <strong><?= limpar(ucfirst((string) $preview['banco_pdf'])) ?></strong>
        </div>
    <?php endif; ?>
</div>

<div class="importacao-resumo-grid import-review-summary cp144-review-summary">
    <div class="cartao">
        <span>Leitura alta</span>
        <strong><?= $alta ?></strong>
    </div>
    <div class="cartao">
        <span>Leitura média</span>
        <strong><?= $media ?></strong>
    </div>
    <div class="cartao destaque-revisao">
        <span>Precisam de revisão</span>
        <strong><?= $precisamRevisao ?></strong>
    </div>
    <div class="cartao">
        <span>Possíveis duplicatas</span>
        <strong><?= $duplicadas ?></strong>
    </div>
</div>

<?php foreach ($avisos as $aviso): ?>
    <div class="alerta-mensagem aviso import-review-alert">
        <?= limpar((string) $aviso) ?>
    </div>
<?php endforeach; ?>

<?php if (!empty($preview['quantidade_ignoradas'])): ?>
    <div class="alerta-mensagem aviso import-review-alert">
        <?= (int) $preview['quantidade_ignoradas'] ?> linha(s) foram ignoradas por não parecerem movimentações válidas.
        Saldos, totais, cabeçalhos e linhas incompletas são descartados de propósito.
    </div>
<?php endif; ?>

<form autocomplete="off" action="../actions/importar_confirmar.php" method="POST" class="import-review-form">
    <?= csrfCampo() ?>

    <div class="cartao import-review-card">
        <div class="import-review-toolbar">
            <div>
                <strong>Movimentações encontradas</strong>
                <small>
                    Campos essenciais agora podem ser corrigidos aqui. Linhas marcadas como duplicadas começam desmarcadas.
                </small>
            </div>
            <div class="paginacao-importacao" aria-label="Paginação da revisão">
                <button type="button" class="btn btn-secundario" id="pagina-anterior">Anterior</button>
                <span id="pagina-atual"></span>
                <button type="button" class="btn btn-secundario" id="pagina-proxima">Próxima</button>
            </div>
        </div>

        <div class="import-review-table-wrap">
            <table class="import-review-table cp144-review-table">
                <thead>
                    <tr>
                        <th class="import-check-col"></th>
                        <th>Descrição</th>
                        <th>Data</th>
                        <th>Valor</th>
                        <th>Tipo</th>
                        <th>Categoria</th>
                        <th>Recorrência</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($preview['linhas'] as $i => $linha): ?>
                    <?php
                    $score = (int) ($linha['confianca_leitura'] ?? 0);
                    $leituraBaixa = $score < 70;
                    $categoriaBaixa = ($linha['confianca'] ?? 'baixa') === 'baixa'
                        || ($linha['categoria_nome'] ?? 'Outros') === 'Outros';
                    $precisaRevisar = $leituraBaixa || $categoriaBaixa;
                    $duplicada = !empty($linha['possivel_duplicata']);
                    $classeConfianca = $score >= 85 ? 'success' : ($score >= 70 ? 'neutral' : 'warning');
                    ?>
                    <tr class="linha-importacao <?= $precisaRevisar ? 'precisa-revisao' : '' ?>" data-linha="<?= $i ?>">
                        <td>
                            <input
                                type="checkbox"
                                name="linhas[<?= $i ?>][incluir]"
                                value="1"
                                <?= $duplicada ? '' : 'checked' ?>
                                aria-label="Incluir movimentação"
                            >
                        </td>

                        <td class="import-desc-cell cp144-edit-cell">
                            <input
                                class="cp144-inline-input cp144-desc-input"
                                type="text"
                                name="linhas[<?= $i ?>][descricao]"
                                value="<?= limpar($linha['descricao']) ?>"
                                maxlength="180"
                                required
                            >
                            <div class="import-row-badges">
                                <span class="import-badge <?= $classeConfianca ?>">Leitura <?= $score ?>%</span>
                                <?php if ($categoriaBaixa): ?>
                                    <span class="import-badge warning">Revisar categoria</span>
                                <?php endif; ?>
                                <?php if ($duplicada): ?>
                                    <span class="import-badge danger">Possível duplicata</span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <td class="cp144-edit-cell">
                            <input
                                class="cp144-inline-input"
                                type="date"
                                name="linhas[<?= $i ?>][data]"
                                value="<?= limpar($linha['data']) ?>"
                                required
                            >
                        </td>

                        <td class="import-value-cell <?= $linha['tipo'] === 'receita' ? 'receita' : 'despesa' ?> cp144-edit-cell">
                            <input
                                class="cp144-inline-input cp144-value-input"
                                type="number"
                                step="0.01"
                                min="0.01"
                                name="linhas[<?= $i ?>][valor]"
                                value="<?= number_format(abs((float) $linha['valor']), 2, '.', '') ?>"
                                required
                            >
                        </td>

                        <td>
                            <select name="linhas[<?= $i ?>][tipo]" class="seletor-tipo" data-linha="<?= $i ?>">
                                <option value="receita" <?= $linha['tipo'] === 'receita' ? 'selected' : '' ?>>Receita</option>
                                <option value="despesa" <?= $linha['tipo'] === 'despesa' ? 'selected' : '' ?>>Despesa</option>
                            </select>
                        </td>

                        <td>
                            <input
                                type="hidden"
                                name="linhas[<?= $i ?>][categoria_sugerida_id]"
                                value="<?= (int) ($linha['categoria_id'] ?? 0) ?>"
                            >
                            <select name="linhas[<?= $i ?>][categoria_id]" class="seletor-categoria" data-linha="<?= $i ?>">
                                <?php foreach ($categorias as $categoria): ?>
                                    <option
                                        value="<?= (int) $categoria['id'] ?>"
                                        data-tipo="<?= limpar($categoria['tipo']) ?>"
                                        <?= (int) ($linha['categoria_id'] ?? 0) === (int) $categoria['id'] ? 'selected' : '' ?>
                                    >
                                        <?= limpar($categoria['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label class="lembrar-regra import-learn-rule">
                                <input type="checkbox" name="linhas[<?= $i ?>][lembrar_regra]" value="1" checked>
                                Lembrar correção
                            </label>
                        </td>

                        <td class="import-rec-cell">
                            <?php if (!empty($linha['possivel_recorrencia'])): ?>
                                <span class="import-badge recurring">
                                    <?= limpar(ucfirst((string) ($linha['recorrencia_periodicidade'] ?? 'possível'))) ?>
                                </span>
                                <small><?= limpar($linha['recorrencia_motivo'] ?? 'Padrão semelhante encontrado.') ?></small>

                                <?php if (usuarioLogadoTipo() === 'pessoa_fisica' && $linha['tipo'] === 'despesa'): ?>
                                    <label class="import-rec-check">
                                        <input type="checkbox" name="linhas[<?= $i ?>][criar_recorrencia]" value="1">
                                        Adicionar às recorrências
                                    </label>
                                    <input type="hidden" name="linhas[<?= $i ?>][recorrencia_periodicidade]" value="<?= limpar((string) ($linha['recorrencia_periodicidade'] ?? 'mensal')) ?>">
                                    <input type="hidden" name="linhas[<?= $i ?>][recorrencia_tipo]" value="<?= limpar((string) ($linha['recorrencia_tipo'] ?? 'despesa')) ?>">
                                    <input type="hidden" name="linhas[<?= $i ?>][recorrencia_proxima_data]" value="<?= limpar((string) ($linha['recorrencia_proxima_data'] ?? '')) ?>">
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="import-muted">Não identificada</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="import-review-actions">
        <button type="submit" class="btn btn-primario">Confirmar importação</button>
        <a href="../pages/importar.php" class="btn btn-secundario">Cancelar</a>
    </div>
</form>

<script>
function filtrarCategorias(seletorTipo) {

    const linha = seletorTipo.dataset.linha;

    const tipo = seletorTipo.value;

    const seletorCategoria = document.querySelector(`.seletor-categoria[data-linha="${linha}"]`);

    if (!seletorCategoria) return;


    let selecionadaVisivel = false;

    Array.from(seletorCategoria.options).forEach((opcao) => {
        const visivel = opcao.dataset.tipo === tipo;
        opcao.hidden = !visivel;
        if (visivel && opcao.selected) selecionadaVisivel = true;
    });


    if (!selecionadaVisivel) {

        const primeira = Array.from(seletorCategoria.options).find((opcao) => !opcao.hidden);

        if (primeira) seletorCategoria.value = primeira.value;

    }

}


document.querySelectorAll('.seletor-tipo').forEach((seletor) => {
    filtrarCategorias(seletor);
    seletor.addEventListener('change', () => filtrarCategorias(seletor));
});


const tamanhoPagina = 20;

const linhas = Array.from(document.querySelectorAll('.linha-importacao'));

const totalPaginas = Math.max(1, Math.ceil(linhas.length / tamanhoPagina));

let pagina = 1;

const anterior = document.getElementById('pagina-anterior');

const proxima = document.getElementById('pagina-proxima');

const indicador = document.getElementById('pagina-atual');


function mostrarPagina() {

    linhas.forEach((linha, indice) => {
        linha.style.display = Math.floor(indice / tamanhoPagina) + 1 === pagina ? '' : 'none';
    });

    indicador.textContent = `Página ${pagina} de ${totalPaginas} — ${linhas.length} movimentações`;

    anterior.disabled = pagina === 1;

    proxima.disabled = pagina === totalPaginas;

}


anterior.addEventListener('click', () => {
    if (pagina > 1) {
        pagina--;
        mostrarPagina();
    }
});

proxima.addEventListener('click', () => {
    if (pagina < totalPaginas) {
        pagina++;
        mostrarPagina();
    }
});

mostrarPagina();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
