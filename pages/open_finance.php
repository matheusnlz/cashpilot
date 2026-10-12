<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/pluggy_service.php';

exigirLogin();

$tituloPagina = 'Conecte seu banco';
$pdo = conectar();
$uid = (int) usuarioLogadoId();
$perfilAtual = usuarioLogadoTipoReal();
$pluggyPronto = pluggyConfigurado();
$migrationOk = true;
$migrationWebhookOk = true;
$itens = [];
$contas = [];
$transacoes = [];
$resumo = ['instituicoes' => 0, 'contas' => 0, 'pendentes' => 0];

try {
    $stmt = $pdo->prepare(
        'SELECT * FROM pluggy_items WHERE usuario_id = :uid ORDER BY atualizado_em DESC'
    );
    $stmt->execute(['uid' => $uid]);
    $itens = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT
                pa.*,
                pi.instituicao_nome
            FROM
                pluggy_accounts pa
                INNER JOIN pluggy_items pi ON pi.pluggy_item_id = pa.pluggy_item_id
                AND pi.usuario_id = pa.usuario_id
            WHERE
                pa.usuario_id = :uid
            ORDER BY
                pi.instituicao_nome,
                pa.nome'
    );
    $stmt->execute(['uid' => $uid]);
    $contas = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT
                pt.*,
                pa.nome AS conta_nome,
                pa.tipo AS conta_tipo,
                pa.conta_id,
                pi.instituicao_nome,
                EXISTS (
                    SELECT
                        1
                    FROM
                        receitas r
                    WHERE
                        r.usuario_id = pt.usuario_id
                        AND r.data_receita = pt.data_transacao
                        AND ABS(r.valor - ABS(pt.valor)) < 0.01
                        AND (
                            r.conta_id = pa.conta_id
                            OR pa.conta_id IS NULL
                        )
                )
                OR EXISTS (
                    SELECT
                        1
                    FROM
                        despesas d
                    WHERE
                        d.usuario_id = pt.usuario_id
                        AND d.data_despesa = pt.data_transacao
                        AND ABS(d.valor - ABS(pt.valor)) < 0.01
                        AND (
                            d.conta_id = pa.conta_id
                            OR pa.conta_id IS NULL
                        )
                ) AS possivel_duplicada
            FROM
                pluggy_transactions pt
                INNER JOIN pluggy_accounts pa ON pa.pluggy_account_id = pt.pluggy_account_id
                AND pa.usuario_id = pt.usuario_id
                INNER JOIN pluggy_items pi ON pi.pluggy_item_id = pt.pluggy_item_id
                AND pi.usuario_id = pt.usuario_id
            WHERE
                pt.usuario_id = :uid
                AND pt.importada = 0
                AND pt.ignorada = 0
            ORDER BY
                pt.data_transacao DESC,
                pt.id DESC
            LIMIT
                120'
    );
    $stmt->execute(['uid' => $uid]);
    $transacoes = $stmt->fetchAll();

    $resumo['instituicoes'] = count($itens);
    $resumo['contas'] = count($contas);
    $resumo['pendentes'] = count($transacoes);
} catch (PDOException $e) {
    if (str_contains(strtolower($e->getMessage()), 'pluggy_')) {
        $migrationOk = false;
    } else {
        throw $e;
    }
}

if ($migrationOk) {
    try {
        $pdo->query('SELECT 1 FROM pluggy_webhook_eventos LIMIT 1');
    } catch (PDOException $e) {
        $migrationWebhookOk = false;
    }
}

$mensagemOk = $_SESSION['open_finance_ok'] ?? '';
$mensagemErro = $_SESSION['open_finance_erro'] ?? '';
unset($_SESSION['open_finance_ok'], $_SESSION['open_finance_erro']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>


<section class="cp153-open-finance" data-sandbox="<?= pluggySandboxAtivo() ? '1' : '0' ?>">
    <div class="topo-pagina cp153-of-head">
        <div>
            <span class="cp153-eyebrow">CONEXÃO BANCÁRIA</span>
            <h1>Conecte seu banco</h1>
            <p>Traga suas contas e movimentações para o CashPilot com uma conexão segura e
                controlada por você.</p>
        </div>
        <button type="button" class="btn btn-primario cp153-connect-btn" id="pluggyConnectButton"
            <?= !$pluggyPronto || !$migrationOk ? 'disabled' : '' ?>
            >
            + Conectar instituição
        </button>
    </div>

    <div class="surface-card cp154-bank-explainer">
        <div>
            <span class="eyebrow">COMO FUNCIONA</span>
            <h2>Seu banco ajuda a manter o CashPilot atualizado</h2>
            <p>Ao conectar uma instituição, você autoriza a consulta dos dados disponíveis pela
                integração. O CashPilot sincroniza contas e movimentações, mostra tudo para revisão
                e só importa para suas receitas e despesas o que você confirmar.</p>
        </div>
        <div class="cp154-bank-steps">
            <span><b>1</b> Conecte uma instituição</span>
            <span><b>2</b> Sincronize as movimentações</span>
            <span><b>3</b> Revise antes de importar</span>
        </div>
        <small>Você pode desconectar a instituição quando quiser. Suas credenciais bancárias não são
            armazenadas pelo CashPilot. A conexão depende do seu consentimento e pode exigir nova
            autorização quando a instituição solicitar.</small>
    </div>

    <?php if ($pluggyPronto && pluggySandboxAtivo()): ?>
    <div class="cp153-alert warning">
        <strong>Instituições de teste habilitadas.</strong>
        As conexões Sandbox são demonstrativas. Esta configuração não comprova liberação
        para bancos reais; isso depende da autorização da Pluggy e da homologação.
    </div>
    <?php endif; ?>

    <p class="secao-ajuda">Prefere enviar um extrato? <a href="importar.php">Importar CSV</a>.</p>

    <?php if ($mensagemOk): ?>


    <div class="cp153-alert success"><?= limpar($mensagemOk) ?></div>

    <?php endif; ?>


    <?php if ($mensagemErro): ?>


    <div class="cp153-alert error"><?= limpar($mensagemErro) ?></div>

    <?php endif; ?>


    <?php if (!$pluggyPronto): ?>


    <div class="cp153-alert warning">
        <strong>A conexão bancária ainda não está disponível neste ambiente.</strong>
        A configuração da integração precisa ser concluída pelo responsável pelo CashPilot. Você
        pode continuar registrando suas movimentações normalmente ou <a href="importar.php">importar um CSV</a>.
    </div>

    <?php endif; ?>


    <?php if (!$migrationOk): ?>


    <div class="cp153-alert warning">
        <strong>Conexão bancária ainda não preparada.</strong>
        A estrutura deste recurso precisa ser habilitada pelo responsável pelo CashPilot.
    </div>

    <?php endif; ?>


    <?php if ($migrationOk && !$migrationWebhookOk): ?>


    <div class="cp153-alert warning">
        <strong>Atualizações automáticas ainda não preparadas.</strong>
        A fila segura de atualizações precisa ser habilitada pelo responsável. A conexão
        manual continua disponível enquanto isso.
    </div>

    <?php endif; ?>


    <div class="cp153-of-summary">
        <article class="cartao">
            <span>Instituições</span>
            <strong><?= (int) $resumo['instituicoes'] ?></strong>
            <small>conexões salvas</small>
        </article>
        <article class="cartao">
            <span>Contas e cartões</span>
            <strong><?= (int) $resumo['contas'] ?></strong>
            <small>produtos encontrados</small>
        </article>
        <article class="cartao">
            <span>Para revisar</span>
            <strong><?= (int) $resumo['pendentes'] ?></strong>
            <small>movimentações aguardando decisão</small>
        </article>
    </div>

    <section class="cp153-section">
        <div class="cp153-section-title">
            <div>
                <span>CONEXÕES</span>
                <h2>Instituições conectadas</h2>
            </div>
        </div>

        <?php if (!$itens): ?>


        <div class="cartao cp153-empty-state">
            <div class="cp153-bank-mark">▦</div>
            <h3>Nenhuma instituição conectada</h3>
            <p>Quando a Pluggy estiver configurada, use “Conectar instituição” para abrir o fluxo
                seguro de conexão.</p>
        </div>

        <?php else: ?>


        <div class="cp153-institution-grid">

            <?php foreach ($itens as $item): ?>


            <?php
            $itemId = (string) $item['pluggy_item_id'];
            $estadoItem = pluggyEstadoApresentacao($item);
            $itemContas = array_values(
                array_filter(
                    $contas,
                    static fn($conta) => (string) $conta['pluggy_item_id'] === $itemId
                )
            );
            ?>


            <article class="cartao cp153-institution-card">
                <div class="cp153-institution-top">
                    <div class="cp153-bank-mark">▦</div>
                    <div>
                        <h3><?= limpar($item['instituicao_nome'] ?: 'Instituição conectada') ?></h3>
                        <p><?= count($itemContas) ?> conta(s) encontrada(s)</p>
                    </div>
                    <span class="cp153-status <?= limpar($estadoItem['classe']) ?>">
                        <?= limpar($estadoItem['rotulo']) ?>
                         </span>
                </div>

                <p class="cp165-connection-state"><?= limpar($estadoItem['descricao']) ?></p>

                <div class="cp153-account-list">

                    <?php foreach ($itemContas as $conta): ?>


                    <div>
                        <span>
                            <?= limpar($conta['nome']) ?>
                             <small><?= limpar(
                                $conta['tipo'] === 'CREDIT' ? 'Cartão' : 'Conta bancária'
                            ) ?></small>
                        </span>
                        <strong><?= formatarMoeda((float) ($conta['saldo'] ?? 0)) ?></strong>
                    </div>

                    <?php endforeach; ?>


                </div>

                <div class="cp153-institution-meta">
                    Última sincronização:
                    <strong><?= $item['ultima_sincronizacao']
                    ? date('d/m/Y H:i', strtotime($item['ultima_sincronizacao']))
                    : '—' ?></strong>
                </div>

                <div class="cp153-institution-actions">
                    <button type="button" class="btn btn-secundario cp153-sync-btn"
                        data-item-id="<?= limpar($itemId) ?>">Sincronizar</button>
                    <button type="button" class="btn btn-secundario cp153-update-btn"
                        data-item-id="<?= limpar($itemId) ?>">Atualizar conexão</button>
                    <form action="../actions/pluggy_disconnect.php" method="POST"
                        data-confirm="Desconectar esta instituição? Os lançamentos já importados serão mantidos.">
                        <?= csrfCampo() ?>

                        <input type="hidden" name="item_id" value="<?= limpar($itemId) ?>">
                        <button type="submit" class="btn btn-perigo">Desconectar</button>
                    </form>
                </div>
            </article>

            <?php endforeach; ?>


        </div>

        <?php endif; ?>


    </section>

    <section class="cp153-section" id="movimentacoes">
        <div class="cp153-section-title">
            <div>
                <span>REVISÃO</span>
                <h2>Movimentações encontradas</h2>
                <p>Revise antes de enviar para Receitas e Despesas. O CashPilot destaca possíveis
                    duplicidades.</p>
            </div>
        </div>

        <?php if (!$transacoes): ?>


        <div class="cartao cp153-empty-state compact">
            <h3>Nada aguardando revisão</h3>
            <p>Conecte ou sincronize uma instituição para buscar novas movimentações.</p>
        </div>

        <?php else: ?>


        <form action="../actions/pluggy_importar.php" method="POST"
            class="cartao cp153-review-card">
            <?= csrfCampo() ?>
             <div class="cp153-review-toolbar">
                <label>
                    <input type="checkbox" id="pluggySelectAll"> Selecionar todas
                </label>
                <div>
                    <button class="btn btn-secundario" type="submit" name="acao" value="ignorar">
                        Ignorar selecionadas</button>
                    <button class="btn btn-primario" type="submit" name="acao" value="importar">
                        Importar selecionadas</button>
                </div>
            </div>

            <div class="cp153-tx-list">

                <?php foreach ($transacoes as $tx): ?>


                <?php $txId = (string) $tx['pluggy_transaction_id']; ?>


                <article class="cp153-tx-row <?= $tx['possivel_duplicada'] ? 'duplicate' : '' ?>">
                    <label class="cp153-tx-check">
                        <input type="checkbox" name="selecionar[<?= limpar($txId) ?>]" value="1"
                            class="pluggy-tx-check">
                    </label>
                    <div class="cp153-tx-main">
                        <strong><?= limpar($tx['descricao']) ?></strong>
                        <span><?= date('d/m/Y', strtotime($tx['data_transacao'])) ?> · <?= limpar(
                            $tx['instituicao_nome']
                        ) ?> · <?= limpar($tx['conta_nome']) ?></span>

                        <?php if ($tx['possivel_duplicada']): ?>


                        <small class="cp153-duplicate-badge">Possível duplicidade</small>
                        <label class="cp165-duplicate-confirm">
                            <input type="checkbox"
                                name="confirmar_duplicada[<?= limpar($txId) ?>]" value="1">
                            Importar mesmo assim
                        </label>

                        <?php endif; ?>


                    </div>
                    <select name="tipo[<?= limpar($txId) ?>]" aria-label="Tipo da movimentação">
                        <option value="despesa" <?= $tx['tipo_sugerido'] === 'despesa'
                        ? 'selected'
                        : '' ?>>Despesa</option>
                        <option value="receita" <?= $tx['tipo_sugerido'] === 'receita'
                        ? 'selected'
                        : '' ?>>Receita</option>
                    </select>
                    <strong class="cp153-tx-value <?= $tx['tipo_sugerido'] === 'receita'
                    ? 'positivo'
                    : 'negativo' ?>">
                        <?= formatarMoeda(abs((float) $tx['valor'])) ?>
                         </strong>
                </article>

                <?php endforeach; ?>


            </div>
        </form>

        <?php endif; ?>


    </section>
</section>

<div id="pluggyFeedback" class="cp153-pluggy-feedback" hidden></div>

<script>
    window.CashPilotOpenFinance = {
        csrf: <?= json_encode(csrfToken(), JSON_UNESCAPED_UNICODE) ?>,
        perfil: <?= json_encode($perfilAtual, JSON_UNESCAPED_UNICODE) ?>,
        configured: <?= $pluggyPronto && $migrationOk ? 'true' : 'false' ?>,
        webhookQueue: <?= $migrationWebhookOk ? 'true' : 'false' ?>

    };
</script>
<script src="https://cdn.pluggy.ai/pluggy-connect/v2.8.2/pluggy-connect.js"></script>
<script src="../assets/js/open-finance.js?v=16.6"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
