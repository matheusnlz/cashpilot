<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';

exigirLogin();

$tituloPagina = 'Copiloto';
$classeBody = 'cp1631-copiloto-body';
$pdo = conectar();
$usuarioId = (int) usuarioLogadoId();
$iaAutorizada = cp17PermiteIA($pdo, $usuarioId);
$empreendedor = usuarioLogadoTipo() === 'mei';
$primeiroNome = trim(explode(' ', usuarioLogadoNome())[0] ?? '');
$hora = (int) date('G');
$periodo = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');

$saudacaoCopiloto =
    $periodo . ($primeiroNome !== '' ? ', ' . $primeiroNome : '') . '. Como posso ajudar hoje?';

$stmt = $pdo->prepare(
    'SELECT
            id,
            titulo,
            atualizado_em
        FROM
            copiloto_conversas
        WHERE
            usuario_id = :uid
        ORDER BY
            atualizado_em DESC
        LIMIT
            50'
);
$stmt->execute(['uid' => $usuarioId]);
$conversas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$conversaId = (int) ($_GET['conversa'] ?? 0);
$mensagens = [];

if ($conversaId > 0) {
    $check = $pdo->prepare(
        'SELECT id
         FROM copiloto_conversas
         WHERE id = :id
           AND usuario_id = :uid'
    );
    $check->execute([
        'id' => $conversaId,
        'uid' => $usuarioId
    ]);

    if ($check->fetchColumn()) {
        $stmt = $pdo->prepare(
            'SELECT
                    papel,
                    mensagem,
                    criado_em
                FROM
                    copiloto_mensagens
                WHERE
                    conversa_id = :cid
                    AND usuario_id = :uid
                ORDER BY
                    id'
        );
        $stmt->execute([
            'cid' => $conversaId,
            'uid' => $usuarioId
        ]);
        $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $conversaId = 0;
    }
}

$atalhos = $empreendedor
    ? [
        'Como está meu negócio este mês?',
        'Compare meu faturamento e despesas',
        'Como está minha margem?',
        'Quais custos merecem atenção?'
    ]
    : [
        'Como estão meus gastos este mês?',
        'Onde estou gastando mais?',
        'Compare receitas e despesas',
        'Como está meu CashScore?'
    ];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>


<div class="cp152-copiloto-page">
    <header class="cp152-copiloto-topbar">
        <div class="cp152-copiloto-brand">
            <button type="button" class="cp152-history-toggle" id="historyToggle"
                aria-label="Abrir histórico de conversas" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>

            <div class="cp152-copiloto-symbol" aria-hidden="true">
                <?= cpIcon('copilot') ?>
            </div>

            <div>
                <strong>Copiloto</strong>
                <small>
                    <?= $empreendedor ? 'Contexto do seu negócio' : 'Contexto das suas finanças' ?>
                     </small>
            </div>
        </div>

        <a href="copiloto.php" class="cp152-new-chat" aria-label="Nova conversa">
            <span><?= cpIcon('plus') ?></span> Nova conversa
        </a>
    </header>

    <div class="cp152-copiloto-layout" id="copilotoWorkspace">
        <div class="cp152-history-backdrop" id="historyBackdrop" hidden></div>

        <aside class="cp152-history" id="copilotoHistory" aria-hidden="true">
            <div class="cp152-history-head">
                <div>
                    <strong>Conversas</strong>
                    <small>Continue de onde parou</small>
                </div>
                <button type="button" id="historyClose" aria-label="Fechar histórico">
                    <?= cpIcon('close') ?>
                </button>
            </div>

            <a href="copiloto.php" class="cp152-history-new">
                <?= cpIcon('plus') ?>
                <span>Nova conversa</span>
            </a>

            <?php if (!$conversas): ?>


            <div class="cp152-history-empty">
                <span><?= cpIcon('history') ?></span>
                <p>Suas conversas salvas aparecerão aqui.</p>
            </div>

            <?php else: ?>


            <div class="cp152-history-list">

                <?php foreach ($conversas as $conversa): ?>


                <div class="cp152-history-item <?= $conversaId === (int) $conversa['id']
                ? 'ativo'
                : '' ?>">
                    <a href="?conversa=<?= (int) $conversa['id'] ?>">
                        <span class="cp152-history-icon"><?= cpIcon('history') ?></span>
                        <span>
                            <strong><?= limpar($conversa['titulo']) ?></strong>
                            <small><?= date(
                                'd/m · H:i',
                                strtotime($conversa['atualizado_em'])
                            ) ?></small>
                        </span>
                    </a>
                    <form action="../actions/copiloto_conversa.php" method="POST"
                        data-confirm="Excluir esta conversa?"
                        data-confirm-message="O histórico desta conversa será removido.">
                        <?= csrfCampo() ?>

                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" value="<?= (int) $conversa['id'] ?>">
                        <button type="submit" aria-label="Excluir conversa">
                            <?= cpIcon('trash') ?>
                        </button>
                    </form>
                </div>

                <?php endforeach; ?>


            </div>

            <?php endif; ?>


        </aside>

        <main class="cp152-chat-panel">
            <div class="cp152-chat-messages" id="cpPageMensagens" aria-live="polite">

                <?php if (!$mensagens): ?>


                <section class="cp152-chat-welcome" id="chatWelcome">
                    <div class="cp152-welcome-mark"><?= cpIcon('copilot') ?></div>
                    <h1><?= limpar($saudacaoCopiloto) ?></h1>
                    <p>
                        <?= $empreendedor
                        ? 'Use os dados do CashPilot para entender faturamento, despesas, margem, caixa e próximos passos.'
                        : 'Use seus dados do CashPilot para entender gastos, orçamento, metas, evolução e decisões financeiras.' ?>
                         </p>

                    <div class="cp152-starter-grid" id="starterGrid">

                        <?php foreach ($atalhos as $atalho): ?>


                        <button type="button" data-cp-starter="<?= limpar($atalho) ?>">
                            <span><?= cpIcon('copilot') ?></span>
                            <strong><?= limpar($atalho) ?></strong>
                            <small>Perguntar ao Copiloto</small>
                        </button>

                        <?php endforeach; ?>


                    </div>
                </section>

                <?php else: ?>


                <?php foreach ($mensagens as $mensagem): ?>


                <article class="cp152-message <?= $mensagem['papel'] === 'usuario'
                ? 'user'
                : 'assistant' ?>">

                    <?php if ($mensagem['papel'] === 'assistente'): ?>


                    <div class="cp152-message-avatar" aria-hidden="true">
                        <?= cpIcon('copilot') ?>
                    </div>

                    <?php endif; ?>


                    <div class="cp152-message-content">

                        <?php if ($mensagem['papel'] === 'assistente'): ?>


                        <div class="cp-markdown-server" data-markdown="<?= limpar(
                            $mensagem['mensagem']
                        ) ?>"></div>

                        <?php else: ?>


                        <p><?= nl2br(limpar($mensagem['mensagem'])) ?></p>

                        <?php endif; ?>


                        <time><?= date('H:i', strtotime($mensagem['criado_em'])) ?></time>
                    </div>
                </article>

                <?php endforeach; ?>


                <?php endif; ?>


            </div>

            <div class="cp152-thinking" id="chatThinking" hidden>
                <div class="cp152-message-avatar"><?= cpIcon('copilot') ?></div>
                <div>
                    <span></span><span></span><span></span>
                </div>
            </div>

            <footer class="cp152-chat-footer">
                <div class="cp152-followups" id="chatFollowups"></div>

                <form id="cpPageForm" class="cp152-composer" autocomplete="off">
                    <textarea id="cpPageInput" maxlength="800" rows="1"
                        placeholder="Pergunte ao Copiloto"
                        aria-label="Mensagem para o Copiloto"></textarea>
                    <button type="submit" id="cpPageSend" class="cp152-send"
                        aria-label="Enviar mensagem">
                        <?= cpIcon('send') ?>
                    </button>
                </form>

                <p class="cp152-disclaimer">
                    O Copiloto pode cometer erros. Confira decisões financeiras importantes.
                </p>
            </footer>
        </main>
    </div>
</div>

<?php if (!$iaAutorizada): ?>

<div class="cp1702-consent-overlay" role="presentation">
    <section class="cp1702-consent-dialog" role="dialog" aria-modal="true"
        aria-labelledby="cpIaConsentTitle" aria-describedby="cpIaConsentText">
        <div class="cp1702-consent-icon" aria-hidden="true"><?= cpIcon('copilot') ?></div>
        <span class="eyebrow">ANTES DE COMEÇAR</span>
        <h2 id="cpIaConsentTitle">Uso responsável do Copiloto</h2>
        <?php if (isset($_SESSION['mensagem_erro_copiloto'])): ?>

        <p class="alerta-mensagem erro" role="alert"><?= limpar(
            $_SESSION['mensagem_erro_copiloto']
        ) ?></p>
        <?php unset($_SESSION['mensagem_erro_copiloto']); ?>

        <?php endif; ?>

        <p id="cpIaConsentText">O Copiloto utiliza a inteligência artificial da Groq para
            interpretar sua pergunta e os resumos financeiros preparados pelo CashPilot.</p>
        <ul>
            <li>A inteligência artificial pode cometer erros ou apresentar informações imprecisas.</li>
            <li>As respostas servem como apoio e não substituem contador, banco ou profissional financeiro.</li>
            <li>Não envie senhas, documentos, credenciais ou outros dados sigilosos pelo chat.</li>
            <li>Sua pergunta e um contexto financeiro resumido serão enviados à Groq.</li>
        </ul>
        <form method="POST" action="../actions/copiloto_consentimento.php">
            <?= csrfCampo() ?>
            <label class="cp1702-consent-check">
                <input type="checkbox" name="aceite_ia" value="1" required>
                <span>Li e concordo com o uso da inteligência artificial e estou ciente de
                    que as respostas podem conter erros.</span>
            </label>
            <button type="submit" class="btn btn-primario btn-bloco">Aceitar e usar o Copiloto</button>
        </form>
        <p class="cp1702-consent-links">Ao continuar, você também declara que leu a
            <a href="politica_privacidade.php">Política de Privacidade</a>.</p>
    </section>
</div>

<?php endif; ?>

<script>
    window.CashPilotCopilotoPage = <?= json_encode(
        [
            'csrf' => csrfToken(),
            'perfilMei' => $empreendedor,
            'conversaId' => $conversaId ?: null,
            'perguntaInicial' => (string) ($_GET['pergunta'] ?? ''),
            'iconeCopiloto' => cpIcon('copilot')
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) ?>;
</script>
<script src="../assets/js/copiloto-page.js?v=17.1" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
