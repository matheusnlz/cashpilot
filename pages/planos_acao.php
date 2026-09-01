<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';

exigirLogin();

$tituloPagina = 'Planos de Ação';
$pdo = conectar();
$usuarioId = (int) usuarioLogadoId();

$stmt = $pdo->prepare(
    'SELECT
        p.*,
        COUNT(i.id) AS total_itens,
        SUM(CASE WHEN i.concluido = 1 THEN 1 ELSE 0 END) AS concluidos
     FROM planos_acao p
     LEFT JOIN plano_acao_itens i
        ON i.plano_id = p.id
     WHERE p.usuario_id = :uid
       AND p.status <> "arquivado"
     GROUP BY p.id
     ORDER BY p.status = "ativo" DESC, p.atualizado_em DESC'
);
$stmt->execute(['uid' => $usuarioId]);
$planos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$itens = [];

if ($planos) {
    $ids = array_column($planos, 'id');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $stmtItens = $pdo->prepare(
        "SELECT *
         FROM plano_acao_itens
         WHERE plano_id IN ($marcadores)
         ORDER BY ordem, id"
    );
    $stmtItens->execute($ids);

    foreach ($stmtItens->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $itens[$item['plano_id']][] = $item;
    }
}

$mensagem = $_SESSION['mensagem_plano'] ?? null;
unset($_SESSION['mensagem_plano']);

$totalPlanos = count($planos);
$planosConcluidos = count(array_filter(
    $planos,
    static fn (array $plano): bool => ($plano['status'] ?? '') === 'concluido'
));

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">DADOS → DECISÃO → AÇÃO</span>
        <h1>Planos de Ação</h1>
        <p>
            Transforme análises do RadarPilot e do Copiloto em etapas práticas,
            acompanhe o progresso e registre o que já foi concluído.
        </p>
    </div>

    <button class="btn btn-primario" data-drawer-open="drawerPlano">
        ＋ Novo plano
    </button>
</div>

<?php if ($mensagem): ?>
    <div class="alerta-mensagem sucesso">
        <?= limpar($mensagem) ?>
    </div>
<?php endif; ?>

<section class="action-plan-intro">
    <div>
        <span class="eyebrow">COMO ESSA ÁREA SE CONECTA AO CASHPILOT</span>
        <h2>Transforme um alerta ou análise em próximos passos.</h2>
        <p class="secao-ajuda">
            O RadarPilot identifica pontos de atenção, o Copiloto ajuda a explicar o cenário
            e o Plano de Ação organiza as decisões que você quer acompanhar.
        </p>

        <div class="action-plan-intro-flow" aria-label="Fluxo do plano de ação">
            <span>RadarPilot</span><i>→</i>
            <span>Copiloto</span><i>→</i>
            <span>Plano de Ação</span><i>→</i>
            <span>Acompanhamento</span>
        </div>
    </div>

    <button
        type="button"
        class="btn btn-secundario"
        data-copiloto-pergunta="Analise meus dados atuais, identifique o principal ponto de atenção e proponha um plano de ação com 3 passos objetivos."
    >
        ✦ Criar com o Copiloto
    </button>
</section>

<?php if ($totalPlanos > 0): ?>
    <section class="summary-strip">
        <div>
            <small>Planos visíveis</small>
            <strong><?= $totalPlanos ?></strong>
        </div>
        <div>
            <small>Concluídos</small>
            <strong><?= $planosConcluidos ?></strong>
        </div>
        <div>
            <small>Em acompanhamento</small>
            <strong><?= max(0, $totalPlanos - $planosConcluidos) ?></strong>
        </div>
    </section>
<?php endif; ?>

<?php if (!$planos): ?>
    <section class="surface-card">
        <div class="estado-vazio clean-empty">
            <span>✓</span>
            <h3>Você ainda não criou um plano de ação</h3>
            <p>
                Crie manualmente ou peça ao Copiloto para transformar uma análise
                em passos que possam ser acompanhados.
            </p>
            <div class="inline-actions">
                <button class="btn btn-primario" data-drawer-open="drawerPlano">
                    Criar primeiro plano
                </button>
                <button
                    class="btn btn-secundario"
                    data-copiloto-pergunta="Analise meus dados atuais e proponha um plano de ação com 3 passos objetivos."
                >
                    ✦ Pedir ao Copiloto
                </button>
            </div>
        </div>
    </section>
<?php else: ?>
    <div class="action-plan-grid">
        <?php foreach ($planos as $plano): ?>
            <?php
                $total = (int) $plano['total_itens'];
                $concluidos = (int) $plano['concluidos'];
                $progresso = $total > 0 ? (int) round($concluidos / $total * 100) : 0;
                $concluido = ($plano['status'] ?? '') === 'concluido';
            ?>

            <article class="surface-card action-plan-card">
                <div class="section-title">
                    <div>
                        <span class="soft-badge">
                            <?= limpar(ucfirst($plano['origem'])) ?>
                        </span>
                        <h2><?= limpar($plano['titulo']) ?></h2>
                    </div>

                    <strong><?= $progresso ?>%</strong>
                </div>

                <?php if ($plano['descricao']): ?>
                    <p><?= limpar($plano['descricao']) ?></p>
                <?php endif; ?>

                <div class="learning-progress" aria-label="<?= $progresso ?>% concluído">
                    <span style="width: <?= $progresso ?>%"></span>
                </div>

                <div class="action-items">
                    <?php foreach ($itens[$plano['id']] ?? [] as $item): ?>
                        <form action="../actions/planos_acao.php" method="POST">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="acao" value="toggle_item">
                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">

                            <button class="<?= $item['concluido'] ? 'done' : '' ?>">
                                <i><?= $item['concluido'] ? '✓' : '' ?></i>
                                <span><?= limpar($item['descricao']) ?></span>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>

                <div class="catalog-actions">
                    <?php if (!$concluido): ?>
                        <form action="../actions/planos_acao.php" method="POST">
                            <?= csrfCampo() ?>
                            <input type="hidden" name="acao" value="concluir">
                            <input type="hidden" name="id" value="<?= (int) $plano['id'] ?>">
                            <button class="text-button">Marcar concluído</button>
                        </form>
                    <?php endif; ?>

                    <form
                        action="../actions/planos_acao.php"
                        method="POST"
                        data-confirm="Arquivar este plano?"
                        data-confirm-message="Ele deixará de aparecer entre os planos ativos."
                    >
                        <?= csrfCampo() ?>
                        <input type="hidden" name="acao" value="arquivar">
                        <input type="hidden" name="id" value="<?= (int) $plano['id'] ?>">
                        <button class="excluir">Arquivar</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<aside class="cp-drawer" id="drawerPlano">
    <div class="drawer-head">
        <div>
            <span class="eyebrow">NOVO PLANO</span>
            <h2>Criar plano de ação</h2>
        </div>
        <button class="drawer-close" data-drawer-close aria-label="Fechar">×</button>
    </div>

    <div class="drawer-body">
        <form action="../actions/planos_acao.php" method="POST">
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="criar">
            <input type="hidden" name="origem" value="manual">

            <div class="form-grupo">
                <label>Título</label>
                <input name="titulo" required placeholder="Ex.: Reduzir custos em setembro">
            </div>

            <div class="form-grupo">
                <label>Descrição</label>
                <textarea name="descricao" placeholder="Explique rapidamente o objetivo do plano."></textarea>
            </div>

            <?php for ($indice = 0; $indice < 5; $indice++): ?>
                <div class="form-grupo">
                    <label>
                        Passo <?= $indice + 1 ?>
                        <?= $indice > 2 ? ' (opcional)' : '' ?>
                    </label>
                    <input
                        name="itens[]"
                        <?= $indice < 3 ? 'required' : '' ?>
                        placeholder="Descreva uma ação objetiva"
                    >
                </div>
            <?php endfor; ?>

            <button class="btn btn-primario btn-bloco">Criar plano</button>
        </form>
    </div>
</aside>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
