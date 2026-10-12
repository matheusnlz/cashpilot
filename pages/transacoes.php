<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/pf_financeiro.php';

require_once __DIR__ . '/../includes/negocio_financeiro.php';

exigirLogin();

$tituloPagina = 'Transações';
$pdo = conectar();
$uid = (int) usuarioLogadoId();
$mei = usuarioLogadoTipo() === 'mei';

if ($mei) {
    cpSincronizarCustosRecorrentesMes($pdo, $uid);
} else {
    cpSincronizarRecorrenciasPF($pdo, $uid);
}

$visualSolicitado = $_GET['visual'] ?? 'lista';

$visual = in_array($visualSolicitado, ['lista', 'calendario'], true) ? $visualSolicitado : 'lista';

$mes = preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '') ? $_GET['mes'] : date('Y-m');

$inicioMes = $mes . '-01';
$fimMes = date('Y-m-t', strtotime($inicioMes));

$q = trim($_GET['q'] ?? '');
$tipo = $_GET['tipo'] ?? 'todos';
$inicio = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['inicio'] ?? '') ? $_GET['inicio'] : '';
$fim = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fim'] ?? '') ? $_GET['fim'] : '';
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = 20;

$sql =
    "(SELECT r.id,'receita' tipo,r.descricao,r.valor,r.data_receita data,COALESCE(c.nome,'Outros') categoria,COALESCE(ct.nome,'Sem conta') conta,'manual' origem FROM receitas r LEFT JOIN categorias c ON c.id=r.categoria_id LEFT JOIN contas ct ON ct.id=r.conta_id WHERE r.usuario_id=:u1";

$p = ['u1' => $uid, 'u2' => $uid];
if ($inicio !== '') {
    $sql .= ' AND r.data_receita >= :i1';
    $p['i1'] = $inicio;
}
if ($fim !== '') {
    $sql .= ' AND r.data_receita <= :f1';
    $p['f1'] = $fim;
}

if ($q !== '') {
    $sql .= ' AND r.descricao LIKE :q1';
    $p['q1'] = '%' . $q . '%';
}

$sql .=
    ') UNION ALL (SELECT d.id,\'despesa\' tipo,d.descricao,d.valor,d.data_despesa data,COALESCE(c.nome,\'Outros\') categoria,COALESCE(ct.nome,\'Sem conta\') conta,COALESCE(d.origem_tipo,\'manual\') origem FROM despesas d LEFT JOIN categorias c ON c.id=d.categoria_id LEFT JOIN contas ct ON ct.id=d.conta_id WHERE d.usuario_id=:u2';
if ($inicio !== '') {
    $sql .= ' AND d.data_despesa >= :i2';
    $p['i2'] = $inicio;
}
if ($fim !== '') {
    $sql .= ' AND d.data_despesa <= :f2';
    $p['f2'] = $fim;
}

if ($q !== '') {
    $sql .= ' AND d.descricao LIKE :q2';
    $p['q2'] = '%' . $q . '%';
}

$sql .= ') ORDER BY data DESC,id DESC';

$s = $pdo->prepare($sql);
$s->execute($p);
$todos = $s->fetchAll(PDO::FETCH_ASSOC);

if (in_array($tipo, ['receita', 'despesa'], true)) {
    $todos = array_values(array_filter($todos, fn($x) => $x['tipo'] === $tipo));
}

$total = count($todos);
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $paginas);
$movs = array_slice($todos, ($pagina - 1) * $porPagina, $porPagina);

/* Calendário sempre usa o mês inteiro, independente dos filtros de período da lista */
$cs = $pdo->prepare("(SELECT r.id,'receita' tipo,r.descricao,r.valor,r.data_receita data,'registrado' status FROM receitas r WHERE r.usuario_id=:a AND r.data_receita BETWEEN :i1 AND :f1)
UNION ALL
(SELECT d.id,'despesa' tipo,d.descricao,d.valor,d.data_despesa data,CASE WHEN d.data_despesa>CURDATE() THEN 'previsto' ELSE 'registrado' END status FROM despesas d WHERE d.usuario_id=:b AND d.data_despesa BETWEEN :i2 AND :f2)
ORDER BY data");

$cs->execute([
    'a' => $uid,
    'i1' => $inicioMes,
    'f1' => $fimMes,
    'b' => $uid,
    'i2' => $inicioMes,
    'f2' => $fimMes
]);
$calMov = $cs->fetchAll(PDO::FETCH_ASSOC);

$porDia = [];
foreach ($calMov as $m) {
    $porDia[$m['data']][] = $m;
}

$prev = date('Y-m', strtotime($inicioMes . ' -1 month'));
$next = date('Y-m', strtotime($inicioMes . ' +1 month'));

$primeiro = (int) date('N', strtotime($inicioMes));
$dias = (int) date('t', strtotime($inicioMes));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>


<div class="page-head">
    <div>
        <span class="eyebrow">LINHA DO TEMPO FINANCEIRA</span>
        <h1>Transações</h1>
        <p><?= $mei
        ? 'Entradas, saídas e compromissos do negócio em uma única visão.'
        : 'Entradas, saídas e próximos compromissos em lista ou calendário.' ?></p>
    </div>
    <div class="cp169-transaction-actions">
        <div class="cp14-head-actions">
            <a class="btn btn-secundario" href="receitas.php">
                <?= cpIcon('income', 'cp-icon-inline') ?> Nova receita
            </a>
            <a class="btn btn-secundario" href="despesas.php">
                <?= cpIcon('expense', 'cp-icon-inline') ?> Nova despesa
            </a>
            <a class="btn btn-secundario" href="importar.php">
                <?= cpIcon('upload', 'cp-icon-inline') ?> Importar CSV
            </a>
        </div>
        <div class="view-toggle">
            <a class="<?= $visual === 'lista' ? 'ativo' : '' ?>" href="?<?= http_build_query(
                array_merge($_GET, ['visual' => 'lista'])
            ) ?>">Lista</a>
            <a class="<?= $visual === 'calendario' ? 'ativo' : '' ?>" href="?<?= http_build_query(
                array_merge($_GET, ['visual' => 'calendario', 'mes' => $mes])
            ) ?>">Calendário</a>
        </div>
    </div>
</div>

<?php if ($visual === 'lista'): ?>


<form class="filter-bar" method="GET">
    <input type="hidden" name="visual" value="lista">
    <div class="filter-search">
        <span>⌕</span>
        <input name="q" value="<?= limpar($q) ?>" placeholder="Pesquisar movimentação">
    </div>
    <select name="tipo">
        <option value="todos">Todos os tipos</option>
        <option value="receita" <?= $tipo === 'receita' ? 'selected' : '' ?>>Receitas</option>
        <option value="despesa" <?= $tipo === 'despesa' ? 'selected' : '' ?>>Despesas</option>
    </select>
    <input type="date" name="inicio" value="<?= limpar($inicio) ?>">
    <input type="date" name="fim" value="<?= limpar($fim) ?>">
    <button class="btn btn-secundario">Filtrar</button>
</form>
<section class="data-panel">

    <?php if (!$movs): ?>

    <div class="estado-vazio clean-empty">
        <span class="cp-empty-icon"><?= cpIcon('transactions') ?></span>
        <h3>Nenhuma transação encontrada</h3>
        <p>Registre uma receita ou despesa, importe um CSV ou ajuste os filtros atuais.</p>
        <a class="btn btn-secundario" href="importar.php"><?= cpIcon('upload', 'cp-icon-inline') ?> Importar CSV</a>
    </div>

    <?php else: ?>

    <div class="timeline-clean">

        <?php
        $dia = '';
        foreach ($movs as $m):
        
            $d = date('Y-m-d', strtotime($m['data']));
            if ($d !== $dia):
                $dia = $d; ?>

        <div class="timeline-date"><?= date('d/m/Y', strtotime($dia)) ?></div>

        <?php
        endif;
        ?>

        <div class="timeline-row">
            <span class="transaction-dot <?= $m['tipo'] ?>"><?= $m['tipo'] === 'receita'
            ? '↑'
            : '↓' ?></span>
            <div>
                <strong><?= limpar($m['descricao']) ?></strong>
                <small><?= limpar($m['categoria']) ?> · <?= limpar(
                    $m['conta']
                ) ?><?= ($m['origem'] ?? 'manual') !== 'manual'
                ? ' · ' . limpar(ucfirst(str_replace('_', ' ', $m['origem'])))
                : '' ?></small>
            </div>
            <strong class="<?= $m['tipo'] === 'receita' ? 'positivo' : 'negativo' ?>"><?= $m[
                'tipo'
            ] === 'receita'
                ? '+'
                : '-' ?> <?= formatarMoeda((float) $m['valor']) ?></strong>
        </div>

        <?php
        endforeach;
        ?>

    </div>

    <?php endif; ?>


</section>

<?php if ($paginas > 1): ?>

<nav class="pagination-clean">

    <?php for ($pg = max(1, $pagina - 2); $pg <= min($paginas, $pagina + 2); $pg++): ?>

    <a class="<?= $pg === $pagina ? 'ativo' : '' ?>" href="?<?= http_build_query(
        array_merge($_GET, ['pagina' => $pg, 'visual' => 'lista'])
    ) ?>"><?= $pg ?></a>

    <?php endfor; ?>

    <span><?= $total ?> transações</span>
</nav>

<?php endif; ?>


<?php else: ?>


<section class="calendar-panel">
    <div class="calendar-head">
        <a href="?visual=calendario&mes=<?= $prev ?>" aria-label="Mês anterior">
            <?= cpIcon('chevron-left') ?>
        </a>
        <div>
            <span class="eyebrow">CALENDÁRIO FINANCEIRO</span>
            <h2><?= limpar(ucfirst(mesAnoPt($inicioMes))) ?></h2>
        </div>
        <a href="?visual=calendario&mes=<?= $next ?>" aria-label="Próximo mês">
            <?= cpIcon('chevron-right') ?>
        </a>
    </div>
    <div class="calendar-week">
        <span>Seg</span>
        <span>Ter</span>
        <span>Qua</span>
        <span>Qui</span>
        <span>Sex</span>
        <span>Sáb</span>
        <span>Dom</span>
    </div>
    <div class="calendar-grid">

        <?php for ($x = 1; $x < $primeiro; $x++): ?>

        <div class="calendar-day empty">
        </div>

        <?php endfor; ?>


        <?php for ($d = 1; $d <= $dias; $d++):
        
        $data = $mes . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
        $items = $porDia[$data] ?? [];
        $saldoDia = 0;
        foreach ($items as $it) {
            $saldoDia += ($it['tipo'] === 'receita' ? 1 : -1) * (float) $it['valor'];
        }
        ?>


        <button class="calendar-day <?= $data === date('Y-m-d') ? 'today' : '' ?> <?= $items
        ? 'has-items'
        : '' ?>" type="button"
            data-day="<?= $data ?>">
            <span class="day-number"><?= $d ?></span>

            <?php if ($items): ?>

            <div class="day-dots">

                <?php foreach (array_slice($items, 0, 3) as $it): ?>

                <i class="<?= $it['tipo'] ?>">
                </i>

                <?php endforeach; ?>

            </div>
            <small class="<?= $saldoDia >= 0 ? 'positivo' : 'negativo' ?>"><?= formatarMoeda(
                abs($saldoDia)
            ) ?></small>

            <?php endif; ?>


        </button>

        <?php
        endfor; ?>

    </div>
</section>
<div class="calendar-detail data-panel" id="calendarDetail">
    <div class="estado-vazio clean-empty">
        <span>◫</span>
        <h3>Selecione um dia</h3>
        <p>Veja movimentações e compromissos previstos para a data.</p>
    </div>
</div>
<script>
    (() => {
        const movimentos = <?= json_encode(
            $porDia,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
        ) ?>;
        const detail = document.getElementById('calendarDetail');
        const moeda = (v) => Number(v).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
        const escapeHtml = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        } [c]));
        document.querySelectorAll('.calendar-day[data-day]').forEach((button) => {
            button.addEventListener('click', () => {
                document.querySelectorAll('.calendar-day').forEach((day) => day
                    .classList.remove('selected'));
                button.classList.add('selected');
                const data = button.dataset.day;
                const itens = movimentos[data] || [];
                const titulo = new Date(data + 'T12:00:00').toLocaleDateString(
                    'pt-BR', {
                        weekday: 'long',
                        day: '2-digit',
                        month: 'long'
                    });
                const linhas = itens.map((item) => `
                <div class="timeline-row">
                    <span class="transaction-dot ${item.tipo}">${item.tipo === 'receita' ? '↑' : '↓'}</span>
                    <div><strong>${escapeHtml(item.descricao)}</strong><small>${item.status === 'previsto' ? 'Compromisso previsto' : 'Registrado'}</small></div>
                    <strong class="${item.tipo === 'receita' ? 'positivo' : 'negativo'}">${item.tipo === 'receita' ? '+ ' : '- '}${moeda(item.valor)}</strong>
                </div>`).join('');
                detail.innerHTML =
                    `
                <div class="data-panel-head"><div><h2>${escapeHtml(titulo)}</h2><p>${itens.length} movimentação(ões)</p></div></div>
                ${itens.length ? `<div class="timeline-clean">${linhas}</div>` : '<p class="texto-vazio">Nada registrado neste dia.</p>'}`;
                detail.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest'
                });
            });
        });
    })();
</script>

<?php endif; ?>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
