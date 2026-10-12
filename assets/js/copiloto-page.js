(() => {
    'use strict';

    const config = window.CashPilotCopilotoPage || {};
    const messages = document.getElementById('cpPageMensagens');
    const input = document.getElementById('cpPageInput');
    const form = document.getElementById('cpPageForm');
    const sendButton = document.getElementById('cpPageSend');
    const thinking = document.getElementById('chatThinking');
    const followups = document.getElementById('chatFollowups');
    const history = document.getElementById('copilotoHistory');
    const historyToggle = document.getElementById('historyToggle');
    const historyClose = document.getElementById('historyClose');
    const historyBackdrop = document.getElementById('historyBackdrop');

    if (!messages || !input || !form) {
        return;
    }

    let conversationId = config.conversaId || null;
    let sending = false;

    function escapeHtml(value) {
        return String(value ?? '').replace(
            /[&<>"']/g,
            (character) =>
                ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                })[character]
        );
    }

    function formatMarkdown(value) {
        let content = escapeHtml(value).replace(/\r\n?/g, '\n');
        content = content
            .replace(/^###\s+(.+)$/gm, '<h4>$1</h4>')
            .replace(/^##\s+(.+)$/gm, '<h3>$1</h3>')
            .replace(/^#\s+(.+)$/gm, '<h2>$1</h2>')
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

        const lines = content.split('\n');
        let html = '';
        let list = null;

        const closeList = () => {
            if (!list) return;
            html += list === 'ol' ? '</ol>' : '</ul>';
            list = null;
        };

        lines.forEach((line) => {
            const numbered = line.match(/^\s*\d+[.)]\s+(.+)/);
            const bullet = line.match(/^\s*[-•]\s+(.+)/);

            if (numbered) {
                if (list !== 'ol') {
                    closeList();
                    html += '<ol>';
                    list = 'ol';
                }
                html += `<li>${numbered[1]}</li>`;
                return;
            }

            if (bullet) {
                if (list !== 'ul') {
                    closeList();
                    html += '<ul>';
                    list = 'ul';
                }
                html += `<li>${bullet[1]}</li>`;
                return;
            }

            closeList();
            if (/^<h[234]>/.test(line)) {
                html += line;
            } else if (line.trim()) {
                html += `<p>${line}</p>`;
            }
        });

        closeList();
        return `<div class="cp152-markdown">${html}</div>`;
    }

    function scrollToBottom() {
        requestAnimationFrame(() => {
            messages.scrollTop = messages.scrollHeight;
        });
    }

    function openHistory() {
        history?.classList.add('open');
        history?.setAttribute('aria-hidden', 'false');
        historyToggle?.setAttribute('aria-expanded', 'true');
        if (historyBackdrop) historyBackdrop.hidden = false;
        document.body.classList.add('cp152-history-open');
    }

    function closeHistory() {
        history?.classList.remove('open');
        history?.setAttribute('aria-hidden', 'true');
        historyToggle?.setAttribute('aria-expanded', 'false');
        if (historyBackdrop) historyBackdrop.hidden = true;
        document.body.classList.remove('cp152-history-open');
    }

    historyToggle?.addEventListener('click', () => {
        if (history?.classList.contains('open')) {
            closeHistory();
        } else {
            openHistory();
        }
    });
    historyClose?.addEventListener('click', closeHistory);
    historyBackdrop?.addEventListener('click', closeHistory);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeHistory();
    });

    function renderSuggestions(list) {
        if (!followups) return;
        followups.innerHTML = '';
        (list || []).slice(0, 4).forEach((text) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = text;
            button.addEventListener('click', () => send(text));
            followups.appendChild(button);
        });
    }

    function relatedSuggestions(question) {
        const text = question.toLowerCase();
        if (/despesa|gasto|categoria/.test(text)) {
            return [
                'Compare com o mês passado',
                'Qual categoria mais cresceu?',
                'O que merece atenção?'
            ];
        }
        if (/meta|objetivo|planejamento/.test(text)) {
            return [
                'Estou no ritmo certo?',
                'Quanto preciso guardar por mês?',
                'Quais riscos existem?'
            ];
        }
        if (/margem|produto|venda|faturamento/.test(text)) {
            return ['Qual item rende mais?', 'Compare com o mês anterior', 'O que devo priorizar?'];
        }
        return [
            'Aprofunde essa análise',
            'Compare com o mês passado',
            'O que devo fazer primeiro?'
        ];
    }

    function addUser(text) {
        document.getElementById('chatWelcome')?.remove();
        messages.insertAdjacentHTML(
            'beforeend',
            `
            <article class="cp152-message user">
                <div class="cp152-message-content">
                    <p>${escapeHtml(text)}</p>
                    <time>agora</time>
                </div>
            </article>
        `
        );
    }

    function formatMetric(metric) {
        const value = Number(metric?.valor || 0);
        if (metric?.formato === 'percentual') {
            return `${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
        }
        if (metric?.formato === 'meses') {
            return `${value.toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mês(es)`;
        }
        return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function createChart(id, type, labels, datasets) {
        const canvas = document.getElementById(id);
        if (!canvas || typeof Chart === 'undefined') return;

        const styles = getComputedStyle(document.documentElement);
        const text = styles.getPropertyValue('--cp141-text').trim() || '#26312d';
        const muted = styles.getPropertyValue('--cp141-muted').trim() || '#77807c';

        new Chart(canvas.getContext('2d'), {
            type,
            data: { labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: datasets.length > 1,
                        labels: { color: text, boxWidth: 10, boxHeight: 10 }
                    }
                },
                scales: {
                    x: { ticks: { color: muted }, grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: muted,
                            callback: (value) => `R$ ${Number(value).toLocaleString('pt-BR')}`
                        },
                        grid: { color: 'rgba(120,130,125,.12)' }
                    }
                }
            }
        });
    }

    function renderUi(ui) {
        if (!ui) return '';
        let html = '<div class="cp152-data-card">';
        if (ui.titulo) html += `<strong class="cp152-data-title">${escapeHtml(ui.titulo)}</strong>`;

        if (ui.tipo === 'score') {
            html += `<div class="cp152-score"><b>${Number(ui.score || 0)}</b><span>/100 · ${escapeHtml(ui.nivel || '')}</span></div>`;
        }

        if (Array.isArray(ui.metricas) && ui.metricas.length) {
            html += '<div class="cp152-data-metrics">';
            html += ui.metricas
                .map(
                    (metric) => `
                <div>
                    <span>${escapeHtml(metric.rotulo || '')}</span>
                    <strong>${escapeHtml(formatMetric(metric))}</strong>
                </div>
            `
                )
                .join('');
            html += '</div>';
        }

        if (ui.tipo === 'barras' && Array.isArray(ui.itens) && ui.itens.length) {
            const chartId = `cpChart${Math.random().toString(36).slice(2)}`;
            html += `<div class="cp152-chart-wrap"><canvas id="${chartId}"></canvas></div>`;
            setTimeout(
                () =>
                    createChart(
                        chartId,
                        'bar',
                        ui.itens.map((item) => item.nome),
                        [
                            {
                                label: 'Despesas',
                                data: ui.itens.map((item) => Number(item.valor || 0))
                            }
                        ]
                    ),
                0
            );
        }

        if (ui.tipo === 'grafico_linhas' && Array.isArray(ui.series) && ui.series.length) {
            const chartId = `cpChart${Math.random().toString(36).slice(2)}`;
            html += `<div class="cp152-chart-wrap"><canvas id="${chartId}"></canvas></div>`;
            setTimeout(
                () =>
                    createChart(
                        chartId,
                        'line',
                        ui.series.map((item) => item.rotulo),
                        [
                            {
                                label: 'Receitas',
                                data: ui.series.map((item) => Number(item.receitas || 0)),
                                tension: 0.32
                            },
                            {
                                label: 'Despesas',
                                data: ui.series.map((item) => Number(item.despesas || 0)),
                                tension: 0.32
                            }
                        ]
                    ),
                0
            );
        }

        return `${html}</div>`;
    }

    function addAssistant(text, ui = null) {
        messages.insertAdjacentHTML(
            'beforeend',
            `
            <article class="cp152-message assistant">
                <div class="cp152-message-avatar" aria-hidden="true">
                    ${config.iconeCopiloto || ''}
                </div>
                <div class="cp152-message-content">
                    ${formatMarkdown(text)}
                    ${renderUi(ui)}
                    <time>agora</time>
                </div>
            </article>
        `
        );
    }

    async function send(message) {
        const text = String(message || '').trim();
        if (!text || sending) return;

        sending = true;
        sendButton?.setAttribute('disabled', 'disabled');
        addUser(text);
        renderSuggestions([]);
        thinking.hidden = false;
        input.disabled = true;
        scrollToBottom();

        const data = new FormData();
        data.append('mensagem', text);
        data.append('csrf_token', config.csrf || '');
        data.append('salvar_historico', '1');
        data.append('pagina', 'copiloto.php');
        if (conversationId) data.append('conversa_id', conversationId);

        let responseRendered = false;

        try {
            const response = await fetch('../actions/chatbot.php', {
                method: 'POST',
                body: data,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const raw = await response.text();
            let result;
            try {
                result = JSON.parse(raw);
            } catch (_) {
                throw new Error('O servidor devolveu uma resposta inválida.');
            }

            if (!response.ok) {
                throw new Error(result.resposta || 'Não foi possível consultar o Copiloto.');
            }

            conversationId = result.conversa_id || conversationId;
            addAssistant(result.resposta || 'Não consegui responder agora.', result.ui || null);
            responseRendered = true;
            renderSuggestions(relatedSuggestions(text));

            if (!new URLSearchParams(window.location.search).has('conversa') && conversationId) {
                history.replaceState({}, '', `?conversa=${conversationId}`);
            }
        } catch (error) {
            console.error('CashPilot/Copiloto:', error);
            if (!responseRendered) {
                addAssistant(
                    'Não consegui consultar o Copiloto agora. Verifique a conexão e tente novamente.'
                );
                renderSuggestions(['Tentar novamente', 'Explique meu mês']);
            }
        } finally {
            thinking.hidden = true;
            sending = false;
            input.disabled = false;
            sendButton?.removeAttribute('disabled');
            input.focus();
            scrollToBottom();
        }
    }

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text || sending) return;
        input.value = '';
        input.style.height = 'auto';
        send(text);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(160, input.scrollHeight)}px`;
    });

    document.querySelectorAll('[data-cp-starter]').forEach((button) => {
        button.addEventListener('click', () => send(button.dataset.cpStarter || ''));
    });

    document.querySelectorAll('.cp-markdown-server').forEach((element) => {
        element.outerHTML = formatMarkdown(element.dataset.markdown || '');
    });

    const initialQuestion = String(config.perguntaInicial || '').trim();
    if (initialQuestion) {
        input.value = initialQuestion;
        input.focus();
        input.dispatchEvent(new Event('input'));
    }

    scrollToBottom();
})();
