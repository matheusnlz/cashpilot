<?php

function cp168Checklist(PDO $pdo, int $usuarioId, string $tipoUsuario): array
{
    $stmt = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM contas WHERE usuario_id = :contas) AS contas,
            (
                (SELECT COUNT(*) FROM receitas WHERE usuario_id = :receitas) +
                (SELECT COUNT(*) FROM despesas WHERE usuario_id = :despesas)
            ) AS movimentacoes,
            (SELECT COUNT(*) FROM metas WHERE usuario_id = :metas) AS metas,
            (
                SELECT COUNT(*)
                FROM planejamento_categoria_mensal
                WHERE usuario_id = :planejamento
            ) AS planejamento,
            (SELECT COUNT(*) FROM vendas WHERE usuario_id = :vendas) AS vendas'
    );

    $stmt->execute([
        'contas' => $usuarioId,
        'receitas' => $usuarioId,
        'despesas' => $usuarioId,
        'metas' => $usuarioId,
        'planejamento' => $usuarioId,
        'vendas' => $usuarioId
    ]);

    $dados = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $planejamentoConcluido = $tipoUsuario === 'mei'
        ? (int) ($dados['vendas'] ?? 0) > 0
        : (int) ($dados['metas'] ?? 0) > 0 || (int) ($dados['planejamento'] ?? 0) > 0;

    return [
        [
            'titulo' => 'Organize uma conta',
            'texto' => 'Cadastre uma conta ou carteira para acompanhar o saldo.',
            'url' => 'contas.php',
            'acao' => 'Cadastrar conta',
            'concluido' => (int) ($dados['contas'] ?? 0) > 0
        ],
        [
            'titulo' => 'Adicione uma movimentação',
            'texto' => 'Registre uma receita ou despesa, ou importe um arquivo CSV.',
            'url' => 'transacoes.php',
            'acao' => 'Adicionar dados',
            'concluido' => (int) ($dados['movimentacoes'] ?? 0) > 0
        ],
        [
            'titulo' => $tipoUsuario === 'mei' ? 'Registre uma venda' : 'Crie seu primeiro plano',
            'texto' => $tipoUsuario === 'mei'
                ? 'Use uma venda para começar a acompanhar o desempenho do negócio.'
                : 'Defina uma meta ou um limite mensal para dar direção ao seu dinheiro.',
            'url' => $tipoUsuario === 'mei' ? 'vendas.php' : 'planejamento.php',
            'acao' => $tipoUsuario === 'mei' ? 'Registrar venda' : 'Começar plano',
            'concluido' => $planejamentoConcluido
        ]
    ];
}

function cp168ChecklistDispensado(PDO $pdo, int $usuarioId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM usuario_apresentacoes
         WHERE usuario_id = :uid
           AND chave = "checklist_inicio_16_8"
         LIMIT 1'
    );
    $stmt->execute(['uid' => $usuarioId]);

    return (bool) $stmt->fetchColumn();
}

function cp168RenderChecklist(array $itens): void
{
    $concluidos = count(array_filter($itens, fn(array $item): bool => $item['concluido']));
    $total = count($itens);
    ?>

    <section class="cp168-start" id="cp168Start" aria-labelledby="cp168StartTitle">
        <div class="cp168-start-head">
            <div>
                <span class="eyebrow">PRIMEIROS PASSOS</span>
                <h2 id="cp168StartTitle">Prepare seu CashPilot</h2>
                <p>Conclua o essencial para que os painéis e análises façam mais sentido.</p>
            </div>
            <span class="cp168-start-progress"><?= $concluidos ?>/<?= $total ?> concluídos</span>
        </div>

        <div class="cp168-start-list">
            <?php foreach ($itens as $item): ?>
                <article class="cp168-start-item <?= $item['concluido'] ? 'concluido' : '' ?>">
                    <span class="cp168-start-check">
                        <?= cpIcon($item['concluido'] ? 'check' : 'arrow-up-right') ?>
                    </span>
                    <div>
                        <h3><?= limpar($item['titulo']) ?></h3>
                        <p><?= limpar($item['texto']) ?></p>
                    </div>
                    <?php if (!$item['concluido']): ?>
                        <a href="<?= limpar($item['url']) ?>"><?= limpar($item['acao']) ?></a>
                    <?php else: ?>
                        <small>Concluído</small>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <button type="button" class="cp168-start-dismiss" id="cp168StartDismiss">
            Não mostrar novamente
        </button>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const card = document.getElementById('cp168Start');
            const dismiss = document.getElementById('cp168StartDismiss');

            dismiss?.addEventListener('click', async () => {
                const form = new FormData();
                form.append('csrf_token', <?= json_encode(csrfToken()) ?>);
                form.append('acao', 'marcar_apresentacao');
                form.append('chave', 'checklist_inicio_16_8');

                try {
                    const resposta = await fetch('../actions/preferencias.php', {
                        method: 'POST',
                        body: form
                    });

                    if (!resposta.ok) {
                        throw new Error('Não foi possível salvar esta preferência.');
                    }

                    card?.classList.add('saindo');
                    setTimeout(() => card?.remove(), 180);
                } catch (erro) {
                    window.alert(erro.message);
                }
            });
        });
    </script>
    <?php
}
