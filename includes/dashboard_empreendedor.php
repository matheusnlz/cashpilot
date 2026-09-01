<?php

require_once __DIR__ . '/negocio_financeiro.php';
require_once __DIR__ . '/inteligencia_financeira.php';
require_once __DIR__ . '/personalizacao.php';
require_once __DIR__ . '/cashpilot14_financeiro.php';

cpSincronizarCustosRecorrentesMes($pdo, $usuarioId);

$inicio = date('Y-m-01');
$fim = date('Y-m-t');
$inicioAnterior = date('Y-m-01', strtotime('first day of last month'));
$fimAnterior = date('Y-m-t', strtotime('last day of last month'));

$stmt = $pdo->prepare(
    'SELECT nome, nicho
     FROM usuarios
     WHERE id = :uid'
);

$stmt->execute([
    'uid' => $usuarioId,
]);

$usuarioEmpreendedor = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$nicho = $usuarioEmpreendedor['nicho'] ?? 'Negócio';

$perfil = cpPerfilNegocio($pdo, $usuarioId);
$perfilPersonalizado = cpPerfilEmpreendedor($pdo, $usuarioId);
$focoEmpreendedor = cpFocoEmpreendedor($perfilPersonalizado);

$faturamento = cpTotalPeriodo(
    $pdo,
    'receitas',
    'data_receita',
    $usuarioId,
    $inicio,
    $fim
);

$despesas = cpTotalPeriodo(
    $pdo,
    'despesas',
    'data_despesa',
    $usuarioId,
    $inicio,
    $fim
);

$faturamentoAnterior = cpTotalPeriodo(
    $pdo,
    'receitas',
    'data_receita',
    $usuarioId,
    $inicioAnterior,
    $fimAnterior
);

$despesasAnterior = cpTotalPeriodo(
    $pdo,
    'despesas',
    'data_despesa',
    $usuarioId,
    $inicioAnterior,
    $fimAnterior
);

$resultado = $faturamento - $despesas;

$vendas = cpResumoVendas(
    $pdo,
    $usuarioId,
    $inicio,
    $fim
);

$compromissos = cpCompromissosMensais(
    $pdo,
    $usuarioId
);

$previsaoCaixa = cp14ProjecaoCaixaDetalhada(
    $pdo,
    $usuarioId,
    30
);

$mensal = cpDadosMensais($pdo, $usuarioId, 12);
$diario = cpDadosDiariosMes($pdo, $usuarioId);

$stmt = $pdo->prepare(
    'SELECT
        nome,
        estoque_atual,
        estoque_minimo
     FROM produtos_servicos
     WHERE usuario_id = :uid
       AND ativo = 1
       AND tipo = "produto"
       AND controlar_estoque = 1
       AND estoque_atual <= estoque_minimo
     ORDER BY estoque_atual
     LIMIT 5'
);

$stmt->execute([
    'uid' => $usuarioId,
]);

$estoqueBaixo = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    'SELECT
        vi.nome_item,
        SUM(vi.quantidade) AS quantidade,
        SUM(vi.preco_unitario * vi.quantidade) AS faturamento,
        SUM(
            (vi.preco_unitario - vi.custo_unitario) * vi.quantidade
        ) AS lucro
     FROM venda_itens vi
     JOIN vendas v ON v.id = vi.venda_id
     WHERE v.usuario_id = :uid
       AND v.data_venda BETWEEN :inicio AND :fim
     GROUP BY vi.nome_item
     ORDER BY faturamento DESC
     LIMIT 3'
);

$stmt->execute([
    'uid' => $usuarioId,
    'inicio' => $inicio,
    'fim' => $fim,
]);

$topItens = $stmt->fetchAll(PDO::FETCH_ASSOC);

$hoje = date('Y-m-d');
$ate = date('Y-m-d', strtotime('+15 days'));

$stmt = $pdo->prepare(
    'SELECT
        descricao,
        valor,
        data_despesa,
        origem_tipo
     FROM despesas
     WHERE usuario_id = :uid
       AND data_despesa BETWEEN :inicio AND :fim
       AND origem_tipo <> "manual"
     ORDER BY data_despesa
     LIMIT 8'
);

$stmt->execute([
    'uid' => $usuarioId,
    'inicio' => $hoje,
    'fim' => $ate,
]);

$proximosCompromissos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$radar = [];

$variacaoDespesas = $despesasAnterior > 0
    ? (($despesas - $despesasAnterior) / $despesasAnterior) * 100
    : null;

if (
    $faturamento > 0 &&
    ($compromissos['total'] / $faturamento) > 0.55
) {
    $radar[] = [
        'vermelho',
        'Estrutura recorrente pesada',
        'Compromissos mensais representam ' .
        number_format(
            ($compromissos['total'] / $faturamento) * 100,
            0
        ) .
        '% do faturamento atual.',
    ];
}

if (
    $variacaoDespesas !== null &&
    $variacaoDespesas > 15
) {
    $radar[] = [
        'amarelo',
        'Custos aceleraram',
        'As despesas cresceram ' .
        number_format($variacaoDespesas, 1, ',', '.') .
        '% contra o mês anterior.',
    ];
}

if (
    $vendas['receita_vendas'] > 0 &&
    $vendas['margem_bruta'] < 25
) {
    $radar[] = [
        'amarelo',
        'Margem sob atenção',
        'A margem bruta das vendas está em ' .
        number_format(
            $vendas['margem_bruta'],
            1,
            ',',
            '.'
        ) .
        '%.',
    ];
}

if ($estoqueBaixo) {
    $radar[] = [
        'amarelo',
        'Estoque precisa de atenção',
        count($estoqueBaixo) .
        ' produto(s) chegaram ao estoque mínimo.',
    ];
}

if ($resultado < 0) {
    $radar[] = [
        'vermelho',
        'Resultado negativo',
        'As saídas superam as entradas em ' .
        formatarMoeda(abs($resultado)) .
        '.',
    ];
}

$kpis = [
    'faturamento' => [
        'rotulo' => 'Faturamento',
        'valor' => formatarMoeda($faturamento),
        'classe' => '',
        'detalhe' => $faturamentoAnterior > 0
            ? (
                ($faturamento - $faturamentoAnterior >= 0 ? '↑ ' : '↓ ') .
                number_format(
                    abs(
                        ($faturamento - $faturamentoAnterior) /
                        $faturamentoAnterior *
                        100
                    ),
                    1,
                    ',',
                    '.'
                ) .
                '% vs. mês anterior'
            )
            : 'Sem base anterior',
    ],
    'resultado' => [
        'rotulo' => 'Resultado',
        'valor' => formatarMoeda($resultado),
        'classe' => $resultado >= 0 ? 'positivo' : 'negativo',
        'detalhe' => 'Receitas menos despesas',
    ],
    'margem' => [
        'rotulo' => 'Margem bruta',
        'valor' => (int) $vendas['vendas'] > 0 && (float) $vendas['receita_vendas'] > 0
            ? number_format((float) $vendas['margem_bruta'], 1, ',', '.') . '%'
            : 'Dados insuficientes',
        'classe' => (int) $vendas['vendas'] > 0 ? '' : 'dados-insuficientes',
        'detalhe' => (int) $vendas['vendas'] > 0
            ? 'Nas vendas vinculadas'
            : 'Registre vendas e custos para calcular',
    ],
    'vendas' => [
        'rotulo' => 'Vendas',
        'valor' => (string) $vendas['vendas'],
        'classe' => '',
        'detalhe' => (int) $vendas['vendas'] > 0
            ? 'Ticket médio ' . formatarMoeda((float) $vendas['ticket_medio'])
            : 'Sem vendas suficientes para calcular ticket',
    ],
];

$ordemKpis = ['faturamento', 'resultado', 'margem', 'vendas'];
$objetivoNormalizado = cpTextoNormalizado(
    (string) ($perfilPersonalizado['objetivo_principal'] ?? '')
);

if (
    str_contains($objetivoNormalizado, 'margem') ||
    str_contains($objetivoNormalizado, 'lucro')
) {
    $ordemKpis = ['margem', 'resultado', 'faturamento', 'vendas'];
} elseif (
    str_contains($objetivoNormalizado, 'venda') ||
    str_contains($objetivoNormalizado, 'faturamento') ||
    str_contains($objetivoNormalizado, 'cliente')
) {
    $ordemKpis = ['faturamento', 'vendas', 'margem', 'resultado'];
} elseif (
    str_contains($objetivoNormalizado, 'caixa') ||
    str_contains($objetivoNormalizado, 'previs')
) {
    $ordemKpis = ['resultado', 'faturamento', 'margem', 'vendas'];
}
?>

<div class="page-head dashboard-head">
    <div>
        <span class="eyebrow">
            ÁREA DO EMPREENDEDOR ·
            <?= limpar(mb_strtoupper($nicho)) ?>
        </span>

        <h1>
            <?= limpar(
                $perfil['nome_negocio'] ?? 'Visão do negócio'
            ) ?>
        </h1>

        <p>
            Faturamento, operação e compromissos em uma visão executiva
            adaptada à prioridade atual do negócio.
        </p>
    </div>

    <div class="cp14-head-actions">
        <a href="visao_financeira.php" class="btn btn-secundario">Visão Financeira</a>
        <button
            class="btn btn-secundario"
            data-copiloto-pergunta="Analise meu negócio atual considerando meu nicho, público, objetivo principal, faturamento, resultado, margem, vendas, estoque e compromissos. Diga o principal ponto de atenção."
        >
            ✦ Explicar meu negócio
        </button>
    </div>
</div>

<section class="surface-card business-focus-card">
    <div>
        <span class="eyebrow">
            PERSONALIZAÇÃO DO SEU PAINEL
        </span>

        <h2>
            <?= limpar($focoEmpreendedor['titulo']) ?>
        </h2>

        <p>
            <?= limpar($focoEmpreendedor['descricao']) ?>
        </p>
    </div>

    <a href="negocio.php" class="link-limpar">
        Alterar prioridade →
    </a>
</section>

<section class="business-kpis">
    <?php foreach ($ordemKpis as $chave): ?>
        <?php $kpi = $kpis[$chave]; ?>

        <article>
            <span>
                <?= limpar($kpi['rotulo']) ?>
            </span>

            <strong class="<?= limpar($kpi['classe']) ?>">
                <?= limpar($kpi['valor']) ?>
            </strong>

            <small>
                <?= limpar($kpi['detalhe']) ?>
            </small>
        </article>
    <?php endforeach; ?>
</section>

<div class="dashboard-grid-main">
    <section class="surface-card">
        <div class="section-title">
            <div>
                <span class="eyebrow">DESEMPENHO</span>
                <h2>Faturamento x Despesas</h2>
            </div>

            <a href="desempenho.php" class="link-limpar">
                Abrir desempenho →
            </a>

            <div class="periodos-grafico">
                <button class="periodo ativo" data-meses="1">1M</button>
                <button class="periodo" data-meses="3">3M</button>
                <button class="periodo" data-meses="6">6M</button>
                <button class="periodo" data-meses="12">12M</button>
            </div>
        </div>

        <div class="container-grafico">
            <canvas id="graficoEvolucao"></canvas>
        </div>
    </section>

    <section class="surface-card">
        <div class="section-title">
            <div>
                <span class="eyebrow">PRÓXIMOS 15 DIAS</span>
                <h2>Compromissos</h2>
            </div>

            <a
                href="transacoes.php?visual=calendario"
                class="link-limpar"
            >
                Calendário →
            </a>
        </div>

        <?php if (!$proximosCompromissos): ?>
            <p class="texto-vazio">
                Nenhum compromisso automático nos próximos 15 dias.
            </p>
        <?php else: ?>
            <div class="upcoming-list">
                <?php foreach ($proximosCompromissos as $compromisso): ?>
                    <div>
                        <div class="date-chip">
                            <b>
                                <?= date(
                                    'd',
                                    strtotime($compromisso['data_despesa'])
                                ) ?>
                            </b>

                            <small>
                                <?= mesAbreviadoPt(
                                    $compromisso['data_despesa']
                                ) ?>
                            </small>
                        </div>

                        <div>
                            <strong>
                                <?= limpar($compromisso['descricao']) ?>
                            </strong>

                            <small>
                                <?= limpar(
                                    ucfirst(
                                        str_replace(
                                            '_',
                                            ' ',
                                            $compromisso['origem_tipo']
                                        )
                                    )
                                ) ?>
                            </small>
                        </div>

                        <strong>
                            <?= formatarMoeda(
                                (float) $compromisso['valor']
                            ) ?>
                        </strong>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="surface-card dashboard-forecast-mini">
    <div>
        <span class="eyebrow">
            PREVISÃO DE CAIXA · 30 DIAS
        </span>

        <h2 class="<?= $previsaoCaixa['caixa_projetado'] >= 0
            ? 'positivo'
            : 'negativo' ?>">
            <?= formatarMoeda(
                $previsaoCaixa['caixa_projetado']
            ) ?>
        </h2>

        <p>
            Estimativa com média recente de receitas e compromissos já registrados.
        </p>
    </div>

    <a href="desempenho.php" class="btn btn-secundario">
        Ver previsão
    </a>
</section>

<div class="dashboard-grid-secondary">
    <section class="surface-card">
        <div class="section-title">
            <div>
                <span class="eyebrow">OPERAÇÃO</span>
                <h2>Estoque</h2>
            </div>

            <a href="produtos_servicos.php" class="link-limpar">
                Gerenciar →
            </a>
        </div>

        <?php if (!$estoqueBaixo): ?>
            <div class="mini-empty success">
                <p>
                    Nenhum produto abaixo do estoque mínimo.
                </p>
            </div>
        <?php else: ?>
            <div class="stock-alert-list">
                <?php foreach ($estoqueBaixo as $item): ?>
                    <div>
                        <span class="status-dot warning"></span>

                        <div>
                            <strong>
                                <?= limpar($item['nome']) ?>
                            </strong>

                            <small>
                                <?= (int) $item['estoque_atual'] ?>
                                disponíveis · mínimo
                                <?= (int) $item['estoque_minimo'] ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="surface-card">
        <div class="section-title">
            <div>
                <span class="eyebrow">DESTAQUES</span>
                <h2>Produtos e serviços</h2>
            </div>

            <a href="vendas.php" class="link-limpar">
                Vendas →
            </a>
        </div>

        <?php if (!$topItens): ?>
            <p class="texto-vazio">
                Registre vendas para identificar os destaques.
            </p>
        <?php else: ?>
            <div class="ranking-list">
                <?php foreach ($topItens as $indice => $item): ?>
                    <div>
                        <span>
                            <?= $indice + 1 ?>
                        </span>

                        <div>
                            <strong>
                                <?= limpar($item['nome_item']) ?>
                            </strong>

                            <small>
                                <?= (int) $item['quantidade'] ?>
                                vendido(s)
                            </small>
                        </div>

                        <strong>
                            <?= formatarMoeda(
                                (float) $item['faturamento']
                            ) ?>
                        </strong>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="surface-card">
        <div class="section-title">
            <div>
                <span class="eyebrow">RADARPILOT</span>
                <h2>O que merece atenção</h2>
            </div>

            <a href="radar.php" class="link-limpar">
                Ver radar →
            </a>
        </div>

        <?php if (!$radar): ?>
            <p class="texto-vazio">
                Nenhum ponto crítico detectado.
            </p>
        <?php else: ?>
            <div class="radar-compact">
                <?php foreach (array_slice($radar, 0, 3) as $alerta): ?>
                    <div class="<?= limpar($alerta[0]) ?>">
                        <i></i>

                        <div>
                            <strong>
                                <?= limpar($alerta[1]) ?>
                            </strong>

                            <p>
                                <?= limpar($alerta[2]) ?>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<script src="../assets/js/dashboard.js">

</script>

<script>
inicializarGraficoEvolucao({
    mensal: <?= json_encode(
        $mensal,
        JSON_UNESCAPED_UNICODE
    ) ?>,
    diario: <?= json_encode(
        $diario,
        JSON_UNESCAPED_UNICODE
    ) ?>
});
</script>
