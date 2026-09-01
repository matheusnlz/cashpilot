<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/pf_financeiro.php';
require_once __DIR__ . '/../includes/cashpilot14_financeiro.php';

exigirLogin();

if (usuarioLogadoTipo() !== 'pessoa_fisica') {
    header('Location: dashboard.php');
    exit;
}

$tituloPagina = 'Planejamento mensal';

$pdo = conectar();
$usuarioId = (int) usuarioLogadoId();

$competencia = preg_match(
    '/^\d{4}-\d{2}$/',
    (string) ($_GET['competencia'] ?? '')
)
    ? $_GET['competencia']
    : date('Y-m');

$recorrencias = cpResumoRecorrenciasPF(
    $pdo,
    $usuarioId
);

$resumo = cp14ResumoPlanejamento(
    $pdo,
    $usuarioId,
    $competencia
);

$planejamento = $resumo['planejamento'];

if (
    !$planejamento
    && $resumo['fixos'] <= 0
) {
    $resumo['fixos'] =
        (float) $recorrencias['mensal'];
}

$mensagem = $_SESSION['mensagem_pf'] ?? null;
unset($_SESSION['mensagem_pf']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">PLANEJADO X REALIZADO</span>
        <h1>Planejamento mensal</h1>
        <p>
            Defina para onde o dinheiro deve ir e acompanhe
            o que realmente aconteceu no mês.
        </p>
    </div>

    <div class="cp14-head-actions">
        <form action="../actions/planejamento.php" method="POST">
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="copiar_anterior">
            <input type="hidden" name="competencia" value="<?= limpar($competencia) ?>">
            <button type="submit" class="btn btn-secundario">Copiar mês anterior</button>
        </form>

        <form method="GET">
            <input
                type="month"
                name="competencia"
                value="<?= limpar($competencia) ?>"
                onchange="this.form.submit()"
            >
        </form>

        <button
            type="button"
            class="btn btn-secundario"
            data-copiloto-pergunta="Analise meu planejamento do mês <?= limpar($competencia) ?>. Compare receita esperada, compromissos, categorias, metas, reserva e investimentos. Mostre riscos sem inventar dados."
        >
            ✦ Analisar planejamento
        </button>
    </div>
</div>

<?php if ($mensagem): ?>
    <div class="alerta-mensagem sucesso">
        <?= limpar($mensagem) ?>
    </div>
<?php endif; ?>

<section class="cp14-kpi-grid">
    <article>
        <span>Receita esperada</span>
        <strong>
            <?= formatarMoeda($resumo['receita_esperada']) ?>
        </strong>
        <small>Valor que você planejou receber</small>
    </article>

    <article>
        <span>Comprometido</span>
        <strong>
            <?= formatarMoeda($resumo['comprometido_planejado']) ?>
        </strong>
        <small>Fixos, metas, reserva, investimentos e categorias</small>
    </article>

    <article>
        <span>Folga planejada</span>
        <strong class="<?= $resumo['disponivel_planejado'] >= 0 ? 'positivo' : 'negativo' ?>">
            <?= formatarMoeda($resumo['disponivel_planejado']) ?>
        </strong>
        <small>
            Receita esperada menos valores planejados
        </small>
    </article>

    <article>
        <span>Gastos por categoria</span>
        <strong>
            <?= formatarMoeda($resumo['realizado_categorias']) ?>
        </strong>
        <small>Realizado no mês selecionado</small>
    </article>
</section>

<div class="cp14-planning-layout">
    <section class="surface-card">
        <div class="section-title">
            <div>
                <span class="eyebrow">ESTRUTURA DO MÊS</span>
                <h2>Definir planejamento</h2>
            </div>
        </div>

        <form
            action="../actions/planejamento.php"
            method="POST"
            autocomplete="off"
            class="cp14-planning-form"
        >
            <?= csrfCampo() ?>

            <input
                type="hidden"
                name="competencia"
                value="<?= limpar($competencia) ?>"
            >

            <div class="grade-dois-campos">
                <div class="form-grupo">
                    <label>Receita esperada</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="receita_esperada"
                        value="<?= limpar((string) ($planejamento['receita_esperada'] ?? '')) ?>"
                        required
                    >
                </div>

                <div class="form-grupo">
                    <label>Gastos fixos / recorrências</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="gastos_fixos_estimados"
                        value="<?= limpar((string) ($planejamento['gastos_fixos_estimados'] ?? round($recorrencias['mensal'], 2))) ?>"
                    >
                </div>
            </div>

            <div class="grade-dois-campos">
                <div class="form-grupo">
                    <label>Destinar para metas</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="valor_metas"
                        value="<?= limpar((string) ($planejamento['valor_metas'] ?? 0)) ?>"
                    >
                </div>

                <div class="form-grupo">
                    <label>Destinar para investimentos</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="valor_investimentos"
                        value="<?= limpar((string) $resumo['investimentos']) ?>"
                    >
                </div>
            </div>

            <div class="form-grupo">
                <label>Destinar para reserva de emergência</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="valor_reserva"
                    value="<?= limpar((string) $resumo['reserva']) ?>"
                >
            </div>

            <div class="cp14-category-plan">
                <div>
                    <span class="eyebrow">LIMITES POR CATEGORIA</span>
                    <h3>Quanto pode ser gasto em cada área?</h3>
                    <p class="secao-ajuda">
                        Deixe em zero as categorias que não deseja
                        controlar neste planejamento.
                    </p>
                </div>

                <div class="cp14-category-plan-grid">
                    <?php foreach ($resumo['categorias'] as $categoria): ?>
                        <label>
                            <span>
                                <?= limpar($categoria['nome']) ?>
                            </span>

                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="categoria_limite[<?= (int) $categoria['id'] ?>]"
                                value="<?= limpar((string) $categoria['planejado']) ?>"
                            >
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-grupo">
                <label>Observação</label>
                <textarea
                    name="observacao"
                    placeholder="Ex.: mês com viagem, material escolar ou outra situação fora do padrão"
                ><?= limpar($planejamento['observacao'] ?? '') ?></textarea>
            </div>

            <button class="btn btn-primario">
                Salvar planejamento
            </button>
        </form>
    </section>

    <section class="surface-card">
        <div class="section-title">
            <div>
                <span class="eyebrow">CATEGORIAS</span>
                <h2>Planejado x realizado</h2>
            </div>
        </div>

        <?php
        $categoriasComPlano = array_filter(
            $resumo['categorias'],
            static fn (array $categoria): bool =>
                $categoria['planejado'] > 0
                || $categoria['realizado'] > 0
        );
        ?>

        <?php if (!$categoriasComPlano): ?>
            <div class="estado-vazio clean-empty">
                <span><?= cpIcon('budget') ?></span>
                <h3>Planeje seus limites</h3>
                <p>
                    Defina valores por categoria para acompanhar
                    quanto ainda pode gastar durante o mês.
                </p>
            </div>
        <?php else: ?>
            <div class="cp14-plan-progress-list">
                <?php foreach ($categoriasComPlano as $categoria): ?>
                    <?php
                    $percentual =
                        $categoria['percentual'];

                    $estourou =
                        $categoria['planejado'] > 0
                        && $categoria['realizado']
                            > $categoria['planejado'];
                    $status143 = cp143StatusPlanejamento(
                        (float) $categoria['planejado'],
                        (float) $categoria['realizado']
                    );
                    ?>

                    <div>
                        <div class="cp14-plan-progress-head">
                            <strong>
                                <?= limpar($categoria['nome']) ?>
                            </strong>

                            <span class="<?= $estourou ? 'negativo' : '' ?>">
                                <?= formatarMoeda($categoria['realizado']) ?>
                                <?php if ($categoria['planejado'] > 0): ?>
                                    de
                                    <?= formatarMoeda($categoria['planejado']) ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <?php if ($categoria['planejado'] > 0): ?>
                            <span class="cp143-plan-status <?= limpar($status143[0]) ?>"><?= limpar($status143[1]) ?> · <?= number_format((float)$status143[2], 0, ',', '.') ?>%</span>
                            <div class="learning-progress">
                                <span
                                    class="<?= $estourou ? 'cp14-over' : '' ?>"
                                    style="width: <?= min(100, (float) $percentual) ?>%"
                                ></span>
                            </div>

                            <small>
                                <?php if ($estourou): ?>
                                    Limite ultrapassado em
                                    <?= formatarMoeda(
                                        $categoria['realizado']
                                        - $categoria['planejado']
                                    ) ?>
                                <?php else: ?>
                                    <?= formatarMoeda(
                                        max(
                                            0,
                                            $categoria['planejado']
                                            - $categoria['realizado']
                                        )
                                    ) ?>
                                    ainda disponíveis
                                <?php endif; ?>
                            </small>
                        <?php else: ?>
                            <small>
                                Gasto registrado sem limite definido.
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="surface-card cp14-month-allocation">
    <div class="section-title">
        <div>
            <span class="eyebrow">DESTINO DO DINHEIRO</span>
            <h2>Visão do planejamento</h2>
        </div>
    </div>

    <div class="cp14-allocation-cards">
        <div>
            <span>Fixos e recorrências</span>
            <strong><?= formatarMoeda($resumo['fixos']) ?></strong>
        </div>

        <div>
            <span>Metas</span>
            <strong><?= formatarMoeda($resumo['metas']) ?></strong>
        </div>

        <div>
            <span>Reserva</span>
            <strong><?= formatarMoeda($resumo['reserva']) ?></strong>
        </div>

        <div>
            <span>Investimentos</span>
            <strong><?= formatarMoeda($resumo['investimentos']) ?></strong>
        </div>

        <div>
            <span>Limites de categorias</span>
            <strong><?= formatarMoeda($resumo['limites_categorias']) ?></strong>
        </div>
    </div>

    <p class="secao-ajuda">
        O planejamento é uma referência criada por você.
        Ele não garante o resultado do mês.
    </p>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
