<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/pf_financeiro.php';
require_once __DIR__ . '/../includes/dashboard_helpers.php';
require_once __DIR__ . '/../includes/inteligencia_financeira.php';
require_once __DIR__ . '/../includes/cashpilot14_financeiro.php';

exigirLogin();

if (usuarioLogadoTipo() !== 'pessoa_fisica') {
    header('Location: dashboard.php');
    exit;
}

$tituloPagina = 'Posso comprar?';
$pdo = conectar();
$uid = (int) usuarioLogadoId();

$resRec = cpResumoRecorrenciasPF($pdo, $uid);
$meses = 3;
$inicio = date('Y-m-01', strtotime('-2 months'));
$fim = date('Y-m-t');
$receitaMedia = cpTotalPeriodo($pdo, 'receitas', 'data_receita', $uid, $inicio, $fim) / $meses;
$despesaMedia = cpTotalPeriodo($pdo, 'despesas', 'data_despesa', $uid, $inicio, $fim) / $meses;
$folgaMedia = $receitaMedia - $despesaMedia;

$visao = cp143VisaoFinanceiraPF($pdo, $uid);
$score = cpCashScore($pdo, $uid);
$reserva = $visao['reserva'] ?? [];
$planejamento = $visao['planejamento'] ?? [];
$patrimonio = $visao['patrimonio'] ?? [];

$metasMensais = 0.0;
foreach (($visao['metas'] ?? []) as $meta) {
    if ($meta['mensal_necessario'] !== null) {
        $metasMensais += (float) $meta['mensal_necessario'];
    }
}

$temPlanejamento = (float) ($planejamento['receita_esperada'] ?? 0) > 0
    || (float) ($planejamento['comprometido_planejado'] ?? 0) > 0;

$dadosCompra = [
    'receitaMedia' => $receitaMedia,
    'despesaMedia' => $despesaMedia,
    'folgaMedia' => $folgaMedia,
    'recorrenciasMensais' => (float) ($resRec['mensal'] ?? 0),
    'saldoRegistrado' => (float) ($patrimonio['saldo_financeiro'] ?? 0),
    'reservaAtual' => (float) ($reserva['valor_atual'] ?? 0),
    'reservaMeses' => (float) ($reserva['cobertura_meses'] ?? 0),
    'cashScore' => (int) ($score['score'] ?? 0),
    'cashScoreNivel' => (string) ($score['nivel'] ?? ''),
    'temPlanejamento' => $temPlanejamento,
    'disponivelPlanejado' => $temPlanejamento
        ? (float) ($planejamento['disponivel_planejado'] ?? 0)
        : null,
    'metasMensais' => $metasMensais,
    'metasAtivas' => count($visao['metas'] ?? []),
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-head cp1431-buy-head">
    <div>
        <span class="eyebrow">DECISÃO DE COMPRA</span>
        <h1>Posso comprar?</h1>
        <p>
            Simule como uma compra à vista ou parcelada pressiona seu fluxo,
            planejamento, reserva e metas antes de assumir o compromisso.
        </p>
    </div>
    <button
        type="button"
        class="btn btn-secundario"
        data-copiloto-pergunta="Explique como devo avaliar uma compra antes de assumir o compromisso, considerando fluxo mensal, reserva, planejamento e metas."
    >
        ✦ Como avaliar uma compra
    </button>
</div>

<section class="cp1431-buy-baseline" aria-label="Base usada na análise">
    <article>
        <span>Folga média mensal</span>
        <strong class="<?= $folgaMedia >= 0 ? 'positivo' : 'negativo' ?>">
            <?= formatarMoeda($folgaMedia) ?>
        </strong>
        <small>Receita média menos despesa média dos últimos 3 meses</small>
    </article>

    <article>
        <span>Saldo registrado</span>
        <strong><?= formatarMoeda((float) ($patrimonio['saldo_financeiro'] ?? 0)) ?></strong>
        <small>Saldo que o CashPilot consegue acompanhar</small>
    </article>

    <article>
        <span>Reserva</span>
        <strong><?= number_format((float) ($reserva['cobertura_meses'] ?? 0), 1, ',', '.') ?> mês(es)</strong>
        <small><?= formatarMoeda((float) ($reserva['valor_atual'] ?? 0)) ?> registrados</small>
    </article>

    <article>
        <span>Planejamento</span>
        <strong>
            <?= $temPlanejamento
                ? formatarMoeda((float) ($planejamento['disponivel_planejado'] ?? 0))
                : '—' ?>
        </strong>
        <small>
            <?= $temPlanejamento
                ? 'valor ainda disponível no planejamento do mês'
                : 'nenhum planejamento mensal definido' ?>
        </small>
    </article>
</section>

<div class="cp1431-buy-layout">
    <section class="surface-card cp1431-buy-form-card">
        <div class="section-title">
            <div>
                <span class="eyebrow">SIMULAÇÃO</span>
                <h2>Detalhes da compra</h2>
            </div>
        </div>

        <form id="pcForm" autocomplete="off">
            <div class="form-grupo">
                <label for="pcNome">O que você quer comprar?</label>
                <input id="pcNome" placeholder="Ex.: Notebook" maxlength="80">
            </div>

            <div class="form-linha">
                <div class="form-grupo">
                    <label for="pcValor">Valor da compra</label>
                    <input type="number" step="0.01" min="0.01" id="pcValor" required placeholder="0,00">
                </div>

                <div class="form-grupo">
                    <label for="pcForma">Forma de pagamento</label>
                    <select id="pcForma">
                        <option value="vista">À vista</option>
                        <option value="parcelado">Parcelado</option>
                    </select>
                </div>
            </div>

            <div id="pcParcelamento" class="cp1431-installment-fields" hidden>
                <div class="form-linha">
                    <div class="form-grupo">
                        <label for="pcEntrada">Entrada</label>
                        <input type="number" step="0.01" min="0" id="pcEntrada" value="0">
                    </div>

                    <div class="form-grupo">
                        <label for="pcParcelas">Número de parcelas</label>
                        <input type="number" min="2" max="120" id="pcParcelas" value="12">
                    </div>
                </div>

                <div class="form-grupo">
                    <label for="pcJuros">Juros ao mês <span class="cp1431-optional">opcional</span></label>
                    <div class="cp1431-percent-input">
                        <input type="number" step="0.01" min="0" max="100" id="pcJuros" value="0">
                        <span>% a.m.</span>
                    </div>
                    <small>Se for parcelamento sem juros, mantenha 0%.</small>
                </div>
            </div>

            <button class="btn btn-primario btn-bloco" type="submit">
                Analisar impacto da compra
            </button>
        </form>

        <p class="secao-ajuda cp1431-buy-disclaimer">
            A análise é educativa e usa somente os dados cadastrados no CashPilot.
            Ela não representa aprovação de crédito nem recomendação para contratar dívida.
        </p>
    </section>

    <aside class="surface-card cp1431-buy-context">
        <div class="section-title">
            <div>
                <span class="eyebrow">CONTEXTO FINANCEIRO</span>
                <h2>O que será considerado</h2>
            </div>
        </div>

        <div class="cp1431-context-list">
            <div>
                <span>Receita média</span>
                <strong><?= formatarMoeda($receitaMedia) ?>/mês</strong>
            </div>
            <div>
                <span>Despesa média</span>
                <strong><?= formatarMoeda($despesaMedia) ?>/mês</strong>
            </div>
            <div>
                <span>Recorrências</span>
                <strong><?= formatarMoeda((float) ($resRec['mensal'] ?? 0)) ?>/mês</strong>
            </div>
            <div>
                <span>Metas com necessidade mensal</span>
                <strong><?= $metasMensais > 0 ? formatarMoeda($metasMensais) . '/mês' : '—' ?></strong>
            </div>
            <div>
                <span>CashScore</span>
                <strong><?= (int) ($score['score'] ?? 0) ?>/100 · <?= limpar((string) ($score['nivel'] ?? '')) ?></strong>
            </div>
        </div>

        <div class="cp1431-context-note">
            <strong>Por que isso importa?</strong>
            <p>
                O preço sozinho não diz se uma compra cabe no orçamento. O CashPilot
                observa quanto sobra por mês e quais outros objetivos já disputam esse dinheiro.
            </p>
        </div>
    </aside>
</div>

<section id="pcResultado" class="cp1431-buy-result" hidden aria-live="polite"></section>

<script>
window.CASH_PILOT_COMPRA = <?= json_encode(
    $dadosCompra,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?>;
</script>
<script src="../assets/js/posso-comprar.js?v=14.3.1">

</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
