<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';

exigirLogin();

$tituloPagina = 'Copiloto';
$pdo = conectar();
$usuarioId = (int) usuarioLogadoId();
$empreendedor = usuarioLogadoTipo() === 'mei';
$primeiroNome = trim(explode(' ', usuarioLogadoNome())[0] ?? '');
$hora = (int) date('G');
$periodo = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');

$saudacoes = [
    $periodo . ($primeiroNome !== '' ? ', ' . $primeiroNome : '') . '. O que vamos analisar hoje?',
    'O que você quer entender melhor nas suas finanças' . ($primeiroNome !== '' ? ', ' . $primeiroNome : '') . '?',
    'Qual decisão financeira você quer avaliar hoje' . ($primeiroNome !== '' ? ', ' . $primeiroNome : '') . '?',
    'Vamos transformar seus dados em próximos passos' . ($primeiroNome !== '' ? ', ' . $primeiroNome : '') . '?',
];
$saudacaoCopiloto = $saudacoes[array_rand($saudacoes)];

$stmt = $pdo->prepare(
    'SELECT id, titulo, atualizado_em
     FROM copiloto_conversas
     WHERE usuario_id = :uid
     ORDER BY atualizado_em DESC
     LIMIT 50'
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
        'uid' => $usuarioId,
    ]);

    if ($check->fetchColumn()) {
        $stmt = $pdo->prepare(
            'SELECT papel, mensagem, criado_em
             FROM copiloto_mensagens
             WHERE conversa_id = :cid
               AND usuario_id = :uid
             ORDER BY id'
        );
        $stmt->execute([
            'cid' => $conversaId,
            'uid' => $usuarioId,
        ]);
        $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $conversaId = 0;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-head copiloto-full-head">
    <div>
        <span class="eyebrow">INTELIGÊNCIA CASHPILOT</span>
        <h1>Copiloto</h1>
        <p>
            <?= $empreendedor
                ? 'Pergunte sobre vendas, margem, custos, caixa e decisões do seu negócio usando os dados cadastrados.'
                : 'Pergunte sobre gastos, metas, orçamento e planejamento usando os dados atuais do CashPilot.' ?>
        </p>
    </div>

    <a class="btn btn-secundario" href="copiloto.php">
        ＋ Nova conversa
    </a>
</div>

<section
    class="copiloto-workspace history-collapsed"
    id="copilotoWorkspace"
>
    <aside class="copiloto-history" id="copilotoHistory">
        <div class="copiloto-history-head">
            <div>
                <strong>Conversas</strong>
                <small>Seu histórico salvo</small>
            </div>

            <button
                type="button"
                id="historyClose"
                aria-label="Recolher histórico"
            >
                ‹
            </button>
        </div>

        <?php if (!$conversas): ?>
            <div class="mini-empty">
                <p>Suas conversas aparecerão aqui.</p>
            </div>
        <?php else: ?>
            <div class="copiloto-history-list">
                <?php foreach ($conversas as $conversa): ?>
                    <div class="history-item <?= $conversaId === (int) $conversa['id'] ? 'ativo' : '' ?>">
                        <a href="?conversa=<?= (int) $conversa['id'] ?>">
                            <span class="history-icon">✦</span>
                            <div>
                                <strong><?= limpar($conversa['titulo']) ?></strong>
                                <small><?= date('d/m · H:i', strtotime($conversa['atualizado_em'])) ?></small>
                            </div>
                        </a>

                        <form
                            action="../actions/copiloto_conversa.php"
                            method="POST"
                            data-confirm="Excluir esta conversa?"
                            data-confirm-message="O histórico desta conversa será removido."
                        >
                            <?= csrfCampo() ?>
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= (int) $conversa['id'] ?>">
                            <button type="submit" aria-label="Excluir conversa">×</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </aside>

    <div class="copiloto-chat-shell">
        <header class="chat-panel-head">
            <div class="chat-brand">
                <button
                    type="button"
                    class="history-toggle"
                    id="historyToggle"
                    aria-label="Abrir conversas"
                >
                    ☰
                </button>

                <span>✦</span>

                <div>
                    <strong>Copiloto CashPilot</strong>
                    <small><i></i> conectado ao contexto do CashPilot</small>
                </div>
            </div>

            <span class="chat-context-badge">
                <?= $empreendedor ? 'Empreendedor' : 'Pessoa Física' ?>
            </span>
        </header>

        <div class="chat-messages" id="cpPageMensagens">
            <?php if (!$mensagens): ?>
                <div class="chat-welcome" id="chatWelcome">
                    <span class="welcome-mark">✦</span>
                    <h2><?= limpar($saudacaoCopiloto) ?></h2>
                    <p>
                        <?= $empreendedor
                            ? 'Posso explicar seus indicadores, comparar períodos e ajudar a transformar análises em próximos passos.'
                            : 'Posso explicar sua situação financeira, comparar períodos e ajudar no planejamento das suas decisões.' ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($mensagens as $mensagem): ?>
                    <div class="chat-message <?= $mensagem['papel'] === 'usuario' ? 'user' : 'assistant' ?>">
                        <?php if ($mensagem['papel'] === 'assistente'): ?>
                            <span class="message-avatar">✦</span>
                        <?php endif; ?>

                        <div class="message-bubble">
                            <?php if ($mensagem['papel'] === 'assistente'): ?>
                                <small>Copiloto</small>
                                <div
                                    class="cp-markdown cp-markdown-server"
                                    data-markdown="<?= limpar($mensagem['mensagem']) ?>"
                                ></div>
                            <?php else: ?>
                                <p><?= nl2br(limpar($mensagem['mensagem'])) ?></p>
                            <?php endif; ?>

                            <time><?= date('H:i', strtotime($mensagem['criado_em'])) ?></time>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-thinking" id="chatThinking" hidden>
            <span>✦</span>
            <div>
                <strong>Analisando seus dados</strong>
                <span class="digitando"><i></i><i></i><i></i></span>
            </div>
        </div>

        <div class="chat-bottom">
            <div class="chat-followups" id="chatFollowups"></div>

            <form id="cpPageForm" class="chat-composer" autocomplete="off">
                <textarea
                    id="cpPageInput"
                    maxlength="800"
                    rows="1"
                    placeholder="Pergunte sobre suas finanças..."
                ></textarea>

                <button type="submit" aria-label="Enviar mensagem">➜</button>
            </form>

            <div class="chat-disclaimer">
                O Copiloto pode cometer erros. Confira decisões financeiras importantes.
            </div>
        </div>
    </div>
</section>

<script>
const mensagens = document.getElementById('cpPageMensagens');

const input = document.getElementById('cpPageInput');

const form = document.getElementById('cpPageForm');

const thinking = document.getElementById('chatThinking');

const followups = document.getElementById('chatFollowups');

const workspace = document.getElementById('copilotoWorkspace');

const csrf = <?= json_encode(csrfToken()) ?>;

const perfilMei = <?= json_encode($empreendedor) ?>;

let conversaId = <?= json_encode($conversaId ?: null) ?>;

let enviando = false;


const starters = perfilMei
    ? [
        'Explique meu negócio',
        'Como está minha margem?',
        'Qual custo merece atenção?',
        'Como está meu caixa?'
    ]
    : [
        'Explique meu mês',
        'Como está meu CashScore?',
        'Onde estou gastando mais?',
        'Como está minha reserva?'
    ];


function esc(texto) {

    return String(texto ?? '').replace(
        /[&<>]/g,
        (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[char])
    );

}


function formatarMarkdownPagina(texto) {

    let conteudo = esc(texto).replace(/\r\n?/g, '\n');


    conteudo = conteudo
        .replace(/^###\s+(.+)$/gm, '<h4>$1</h4>')
        .replace(/^##\s+(.+)$/gm, '<h3>$1</h3>')
        .replace(/^#\s+(.+)$/gm, '<h2>$1</h2>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');


    const linhas = conteudo.split('\n');

    let html = '';

    let lista = null;


    function fecharLista() {

        if (!lista) return;

        html += lista === 'ol' ? '</ol>' : '</ul>';

        lista = null;

    }


    linhas.forEach((linha) => {
        const numerada = linha.match(/^\s*\d+[.)]\s+(.+)/);

        const marcador = linha.match(/^\s*[-•]\s+(.+)/);


        if (numerada) {

            if (lista !== 'ol') {

                fecharLista();

                html += '<ol>';

                lista = 'ol';

            }

            html += '<li>' + numerada[1] + '</li>';

            return;

        }


        if (marcador) {

            if (lista !== 'ul') {

                fecharLista();

                html += '<ul>';

                lista = 'ul';

            }

            html += '<li>' + marcador[1] + '</li>';

            return;

        }


        fecharLista();


        if (/^<h[234]>/.test(linha)) {

            html += linha;

        }
 else if (linha.trim() !== '') {

            html += '<p>' + linha + '</p>';

        }

    }
);


    fecharLista();


    return '<div class="cp-markdown">' + html + '</div>';

}


function scrollChat() {

    requestAnimationFrame(() => {
        mensagens.scrollTop = mensagens.scrollHeight;
    });

}


function renderSuggestions(lista) {

    followups.innerHTML = '';


    (lista || []).slice(0, 4).forEach((texto) => {
        const botao = document.createElement('button');
        botao.type = 'button';
        botao.textContent = texto;
        botao.addEventListener('click', () => send(texto));
        followups.appendChild(botao);
    });

}


function sugestoesRelacionadas(pergunta) {

    const texto = pergunta.toLowerCase();


    if (/despesa|gasto|categoria/.test(texto)) {

        return [
            'Compare com o mês passado',
            'Qual categoria mais cresceu?',
            'O que devo reduzir primeiro?'
        ];

    }


    if (/meta|objetivo/.test(texto)) {

        return [
            'Estou no ritmo certo?',
            'Quanto preciso guardar por mês?',
            'Monte um plano para esta meta'
        ];

    }


    if (/margem|produto|venda/.test(texto)) {

        return [
            'Qual item é mais rentável?',
            'Compare com o mês anterior',
            'O que devo priorizar?'
        ];

    }


    if (/caixa|previs/.test(texto)) {

        return [
            'Quais compromissos pesam mais?',
            'O que pode pressionar meu caixa?',
            'Monte um plano de ação'
        ];

    }


    if (/score|reserva/.test(texto)) {

        return [
            'O que mais influencia isso?',
            'Como posso melhorar?',
            'Monte um plano de 3 passos'
        ];

    }


    return [
        'Aprofunde essa análise',
        'Compare com o mês passado',
        'O que devo fazer primeiro?'
    ];

}


function addUser(texto) {

    document.getElementById('chatWelcome')?.remove();


    mensagens.insertAdjacentHTML(
        'beforeend',
        `<div class="chat-message user">
            <div class="message-bubble">
                <p>${esc(texto)}</p>
                <time>agora</time>
            </div>
        </div>`
    );

}


function fmtUI(metrica) {

    const valor = Number(metrica?.valor || 0);


    if (metrica?.formato === 'percentual') {

        return valor.toLocaleString('pt-BR', {maximumFractionDigits: 1}) + '%';

    }


    if (metrica?.formato === 'meses') {

        return valor.toLocaleString('pt-BR', {maximumFractionDigits: 1}) + ' mês(es)';

    }


    return valor.toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    });

}


function renderUI(ui) {

    if (!ui) return '';


    let html = '<div class="copiloto-ui-card full">';


    if (ui.titulo) {

        html += '<strong class="copiloto-ui-title">' + esc(ui.titulo) + '</strong>';

    }


    if (ui.tipo === 'score') {

        html += '<div class="copiloto-score-mini"><b>'
            + Number(ui.score || 0)
            + '</b><span>/100 · '
            + esc(ui.nivel || '')
            + '</span></div>';

    }


    if (Array.isArray(ui.metricas) && ui.metricas.length) {

        html += '<div class="copiloto-ui-metrics">'
            + ui.metricas.map((metrica) => (
                '<div><span>'
                + esc(metrica.rotulo || '')
                + '</span><strong>'
                + esc(fmtUI(metrica))
                + '</strong></div>'
            )).join('')
            + '</div>';

    }


    if (ui.plano) {

        html += '<button type="button" class="copiloto-save-plan" data-save-plan="'
            + encodeURIComponent(JSON.stringify(ui.plano))
            + '">✓ Salvar como plano de ação</button>';

    }


    return html + '</div>';

}


function addAssistant(texto, ui = null) {

    mensagens.insertAdjacentHTML(
        'beforeend',
        `<div class="chat-message assistant">
            <span class="message-avatar">✦</span>
            <div class="message-bubble">
                <small>Copiloto</small>
                ${formatarMarkdownPagina(texto)}
                ${renderUI(ui)}
                <time>agora</time>
            </div>
        </div>`
    );

}


async function send(mensagem) {

    const msg = String(mensagem || '').trim();

    if (!msg || enviando) return;


    enviando = true;

    addUser(msg);

    renderSuggestions([]);

    scrollChat();

    thinking.hidden = false;

    input.disabled = true;


    const dados = new FormData();

    dados.append('mensagem', msg);

    dados.append('csrf_token', csrf);

    dados.append('salvar_historico', '1');

    dados.append('pagina', 'copiloto.php');


    if (conversaId) {

        dados.append('conversa_id', conversaId);

    }


    try {

        const response = await fetch('../actions/chatbot.php', {
            method: 'POST',
            body: dados,
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });


        const raw = await response.text();

        let data;


        try {

            data = JSON.parse(raw);

        }
 catch (_) {

            throw new Error('Resposta inválida');

        }


        if (!response.ok) {

            throw new Error(data.resposta || 'Falha ao consultar o Copiloto');

        }


        conversaId = data.conversa_id || conversaId;

        addAssistant(data.resposta || 'Não consegui responder agora.', data.ui || null);

        renderSuggestions(sugestoesRelacionadas(msg));


        if (!location.search && conversaId) {

            history.replaceState({}, '', `?conversa=${conversaId}`);

        }

    }
 catch (_) {

        addAssistant('Não consegui consultar o Copiloto agora. Tente novamente em alguns instantes.');

        renderSuggestions(['Tentar novamente', 'Explique meu mês']);

    }
 finally {

        thinking.hidden = true;

        enviando = false;

        input.disabled = false;

        input.focus();

        scrollChat();

    }

}


form.addEventListener('submit', (event) => {
    event.preventDefault();

    const msg = input.value.trim();
    if (!msg || enviando) return;

    input.value = '';
    input.style.height = 'auto';
    send(msg);
});


input.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        form.requestSubmit();
    }
});


input.addEventListener('input', () => {
    input.style.height = 'auto';
    input.style.height = Math.min(140, input.scrollHeight) + 'px';
});


document.getElementById('historyToggle')?.addEventListener('click', () => {
    workspace.classList.toggle('history-collapsed');
});


document.getElementById('historyClose')?.addEventListener('click', () => {
    workspace.classList.add('history-collapsed');
});


document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-save-plan]');
    if (!button) return;

    try {
        const plano = JSON.parse(decodeURIComponent(button.dataset.savePlan));
        const formPlano = document.createElement('form');
        formPlano.method = 'POST';
        formPlano.action = '../actions/planos_acao.php';

        const campos = {
            csrf_token: csrf,
            acao: 'criar',
            titulo: plano.titulo || 'Plano do Copiloto',
            descricao: plano.descricao || '',
            origem: 'copiloto'
        };

        Object.entries(campos).forEach(([nome, valor]) => {
            const campo = document.createElement('input');
            campo.type = 'hidden';
            campo.name = nome;
            campo.value = valor;
            formPlano.appendChild(campo);
        });

        (plano.itens || []).forEach((valor) => {
            const campo = document.createElement('input');
            campo.type = 'hidden';
            campo.name = 'itens[]';
            campo.value = valor;
            formPlano.appendChild(campo);
        });

        document.body.appendChild(formPlano);
        formPlano.submit();
    } catch (_) {}
});


document.querySelectorAll('.cp-markdown-server').forEach((elemento) => {
    elemento.outerHTML = formatarMarkdownPagina(elemento.dataset.markdown || '');
});


renderSuggestions(
    <?= json_encode(!empty($mensagens)) ?>
        ? ['Aprofunde essa análise', 'Compare com o mês passado', 'O que devo fazer primeiro?']
        : starters
);


scrollChat();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
