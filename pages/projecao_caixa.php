<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/inteligencia_financeira.php';

require_once __DIR__ . '/../includes/negocio_financeiro.php';

require_once __DIR__ . '/../includes/cashpilot14_financeiro.php';


exigirLogin();


if (usuarioLogadoTipo() !== 'mei') {

    header('Location: dashboard.php');

    exit;
}


$tituloPagina = 'Projeção de Caixa';


$pdo = conectar();

$usuarioId = (int) usuarioLogadoId();


cpSincronizarCustosRecorrentesMes(
    $pdo,
    $usuarioId
);


$dias = (int) ($_GET['dias'] ?? 30);


if (!in_array($dias, [15, 30, 60], true)) {

    $dias = 30;
}


$projecao = cp14ProjecaoCaixaDetalhada(
    $pdo,
    $usuarioId,
    $dias
);


$pontos = [];


foreach ([7, 15, 30, 60] as $ponto) {

    if ($ponto > $dias) {

        continue;
}


    $indice = $ponto - 1;


    if (isset($projecao['serie'][$indice])) {

        $pontos[$ponto] =
            $projecao['serie'][$indice]['saldo_projetado'];
}
}


require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';?>

<div class="page-head">
<div>
<span class="eyebrow">GESTÃO PREVENTIVA</span>
<h1>Projeção de Caixa</h1>
<p>
            Antecipe períodos de pressão no caixa usando
            histórico e compromissos já registrados.
        </p>
</div>
<div class="cp14-head-actions">
<div class="periodos-grafico">
            <?php foreach ([15, 30, 60] as $periodo):?>
                <a
                    class="periodo <?= $dias === $periodo ? 'ativo' : ''?>"
                    href="?dias=<?= $periodo?>"
                >
                    <?= $periodo?>D
                </a>
            <?php endforeach;?>
        </div>
<button
            type="button"
            class="btn btn-secundario"
            data-copiloto-pergunta="Analise minha projeção de caixa dos próximos <?= $dias?> dias. Diferencie saldo realizado, entradas estimadas e compromissos registrados. Mostre o período de maior risco sem tratar a projeção como certeza."
        >
            ✦ Analisar projeção
        </button>
</div>
</div>
<section class="cp14-kpi-grid">
<article>
<span>Saldo atual</span>
<strong>
            <?= formatarMoeda(
                (float) $projecao['saldo_atual']
            )?>
        </strong>
<small>Movimentações realizadas até hoje</small>
</article>
<article>
<span>Entradas estimadas</span>
<strong>
            <?= formatarMoeda(
                (float) $projecao['receita_prevista']
            )?>
        </strong>
<small>Baseadas na média histórica</small>
</article>
<article>
<span>Compromissos previstos</span>
<strong>
            <?= formatarMoeda(
                (float) $projecao['compromissos_previstos']
            )?>
        </strong>
<small>Custos e saídas já conhecidas</small>
</article>
<article>
<span>Caixa projetado</span>
<strong class="<?= $projecao['caixa_projetado'] >= 0 ? 'positivo' : 'negativo'?>">
            <?= formatarMoeda(
                (float) $projecao['caixa_projetado']
            )?>
        </strong>
<small>Estimativa para <?= $dias?> dias</small>
</article>
</section>
<div class="cp14-cash-layout">
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">CURVA PROJETADA</span>
<h2>Como o caixa pode evoluir</h2>
</div>
</div>
<div class="container-grafico cp14-cash-chart">
<canvas id="graficoProjecaoCaixa14">
</canvas>
</div>
<p class="secao-ajuda">
            <?= limpar($projecao['observacao'])?>
        </p>
</section>
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">PONTO DE ATENÇÃO</span>
<h2>Menor saldo projetado</h2>
</div>
</div>
<div class="cp14-lowest-cash">
<strong class="<?= $projecao['menor_saldo'] >= 0 ? 'positivo' : 'negativo'?>">
                <?= formatarMoeda(
                    (float) $projecao['menor_saldo']
                )?>
            </strong>
<span>
                aproximadamente em
                <?= date(
                    'd/m/Y',
                    strtotime($projecao['menor_data'])
                )?>
            </span>
</div>
<div class="cp14-cash-checkpoints">
            <?php foreach ($pontos as $periodo => $saldo):?>
                <div>
<span><?= $periodo?> dias</span>
<strong class="<?= $saldo >= 0 ? 'positivo' : 'negativo'?>">
                        <?= formatarMoeda((float) $saldo)?>
                    </strong>
</div>
            <?php endforeach;?>
        </div>
</section>
</div>
<section class="surface-card cp143-projection-reading">
<div class="section-title">
<div>
<span class="eyebrow">LEITURA DA PROJEÇÃO</span>
<h2><?= $projecao['primeiro_negativo'] ? 'Atenção ao caixa' : 'Projeção permanece positiva'?></h2>
</div>
</div>
<p><?php if($projecao['primeiro_negativo']):?>Com os lançamentos atuais, o primeiro saldo negativo projetado aparece aproximadamente em <strong><?= date('d/m/Y', strtotime($projecao['primeiro_negativo']))?></strong>. Revise entradas previstas e compromissos antes dessa data.<?php else:?>Com os dados cadastrados atualmente, o caixa permanece positivo durante os <?= $dias?> dias analisados.<?php endif;?></p>
<small>A projeção não é uma garantia: ela muda quando novas receitas e despesas são registradas.</small>
</section>
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">SAÍDAS CONHECIDAS</span>
<h2>Compromissos no horizonte</h2>
</div>
</div>

    <?php if (!$projecao['compromissos']):?>
        <div class="estado-vazio clean-empty">
<span><?= cpIcon('calendar')?></span>
<h3>Nenhuma saída futura registrada</h3>
<p>
                A projeção fica mais útil conforme custos,
                fornecedores e compromissos futuros são cadastrados.
            </p>
</div>
    <?php else:?>
        <div class="cp14-commitment-list">
            <?php foreach (
                array_slice(
                    $projecao['compromissos'],
                    0,
                    20
                )
                as $compromisso
            ):?>
                <div>
<div class="date-chip">
<b>
                            <?= date(
                                'd',
                                strtotime($compromisso['data'])
                            )?>
                        </b>
<small>
                            <?= mesAbreviadoPt(
                                $compromisso['data']
                            )?>
                        </small>
</div>
<div>
<strong>
                            <?= limpar($compromisso['descricao'])?>
                        </strong>
<small>
                            <?= limpar(ucfirst($compromisso['tipo']))?>
                        </small>
</div>
<strong>
                        <?= formatarMoeda(
                            (float) $compromisso['valor']
                        )?>
                    </strong>
</div>
            <?php endforeach;?>
        </div>
    <?php endif;?>
</section>
<script>
const serieCashPilot14 =
    <?= json_encode(
        $projecao['serie'],
        JSON_UNESCAPED_UNICODE
    )?>;


const ctxCashPilot14 =
    document.getElementById('graficoProjecaoCaixa14');


if (ctxCashPilot14 && window.Chart) {

    const dark =
        document.documentElement
            .getAttribute('data-theme') === 'dark';


    new Chart(ctxCashPilot14, {
        type: 'line',
        data: {
            labels: serieCashPilot14.map(
                item =>
                    new Date(
                        item.data + 'T12:00:00'
                    ).toLocaleDateString(
                        'pt-BR',
                        {
                            day: '2-digit',
                            month: '2-digit'
                        }
                    )
            ),
            datasets: [
                {
                    label: 'Saldo projetado',
                    data: serieCashPilot14.map(
                        item =>
                            Number(
                                item.saldo_projetado
                            )
                    ),
                    borderColor:
                        dark
                            ? '#78a79d'
                            : '#315e59',
                    backgroundColor:
                        'transparent',
                    borderWidth: 2,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                    tension: .34
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: context =>
                            Number(
                                context.raw || 0
                            ).toLocaleString(
                                'pt-BR',
                                {
                                    style: 'currency',
                                    currency: 'BRL'
                                }
                            )
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        color:
                            dark
                                ? '#a8b1ac'
                                : '#6d7771'
                    }
                },
                y: {
                    grid: {
                        color:
                            dark
                                ? '#2c3530'
                                : '#e5e9e5'
                    },
                    ticks: {
                        color:
                            dark
                                ? '#a8b1ac'
                                : '#6d7771'
                    }
                }
            }
        }
    });

}
</script>

<?php require_once __DIR__ . '/../includes/footer.php';?>
