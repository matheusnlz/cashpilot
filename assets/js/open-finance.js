(() => {
    'use strict';

    const config = window.CashPilotOpenFinance || {};
    const connectButton = document.getElementById('pluggyConnectButton');
    const feedback = document.getElementById('pluggyFeedback');
    const selectAll = document.getElementById('pluggySelectAll');
    let persistindoConexao = false;

    function showFeedback(message, type = 'info') {
        if (!feedback) return;
        feedback.textContent = message;
        feedback.className = `cp153-pluggy-feedback ${type}`;
        feedback.hidden = false;
        window.clearTimeout(showFeedback.timer);
        showFeedback.timer = window.setTimeout(() => {
            feedback.hidden = true;
        }, 4800);
    }

    async function post(url, data = {}) {
        const body = new URLSearchParams({ csrf_token: config.csrf || '', ...data });
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            credentials: 'same-origin',
            body: body.toString()
        });
        const payload = await response
            .json()
            .catch(() => ({ ok: false, message: 'Resposta inválida do servidor.' }));
        if (!response.ok || payload.ok === false) {
            throw new Error(payload.message || 'Não foi possível concluir esta ação.');
        }
        return payload;
    }

    async function persistItem(itemData) {
        const item = itemData && itemData.item ? itemData.item : itemData;
        const itemId = item && item.id ? item.id : '';
        if (!itemId) throw new Error('A Pluggy não retornou o identificador da conexão.');

        persistindoConexao = true;
        try {
            showFeedback('Conexão recebida. Buscando os dados disponíveis...', 'loading');
            const resultado = await post('../actions/pluggy_item_save.php', {
                item_id: itemId,
                perfil: config.perfil || 'pessoa_fisica'
            });
            showFeedback(resultado.message || 'Conexão salva.', 'success');
            window.setTimeout(() => {
                window.location.href = 'open_finance.php?conectado=1';
            }, 550);
        } finally {
            persistindoConexao = false;
        }
    }

    async function openWidget(itemId = '') {
        if (!config.configured) {
            showFeedback('Configure a Pluggy e a migration antes de conectar.', 'error');
            return;
        }
        if (typeof window.PluggyConnect !== 'function') {
            showFeedback('Não foi possível carregar o Pluggy Connect.', 'error');
            return;
        }

        try {
            showFeedback('Preparando conexão segura...', 'loading');
            const token = await post(
                '../actions/pluggy_connect_token.php',
                itemId ? { item_id: itemId } : {}
            );
            if (!token.accessToken) throw new Error('Connect Token não recebido.');

            const widget = new window.PluggyConnect({
                connectToken: token.accessToken,
                includeSandbox: Boolean(token.includeSandbox),
                updateItem: itemId || undefined,
                language: 'pt',
                onSuccess: (itemData) => {
                    persistItem(itemData).catch((error) => {
                        showFeedback(
                            error.message || 'Não foi possível salvar a conexão.',
                            'error'
                        );
                    });
                },
                onError: (error) => {
                    const message =
                        error && error.message ? error.message : 'A conexão não foi concluída.';
                    showFeedback(message, 'error');
                },
                onClose: () => {
                    if (
                        !persistindoConexao &&
                        feedback &&
                        !feedback.hidden &&
                        feedback.classList.contains('loading')
                    ) {
                        feedback.hidden = true;
                    }
                }
            });
            widget.init();
        } catch (error) {
            showFeedback(error.message || 'Não foi possível abrir a conexão.', 'error');
        }
    }

    connectButton?.addEventListener('click', () => openWidget());

    document.querySelectorAll('.cp153-update-btn').forEach((button) => {
        button.addEventListener('click', () => openWidget(button.dataset.itemId || ''));
    });

    document.querySelectorAll('.cp153-sync-btn').forEach((button) => {
        button.addEventListener('click', async () => {
            const original = button.textContent;
            button.disabled = true;
            button.textContent = 'Sincronizando...';
            try {
                const result = await post('../actions/pluggy_sync.php', {
                    item_id: button.dataset.itemId || ''
                });
                showFeedback(result.message || 'Sincronização concluída.', 'success');
                window.setTimeout(() => window.location.reload(), 650);
            } catch (error) {
                showFeedback(error.message || 'Falha ao sincronizar.', 'error');
                button.disabled = false;
                button.textContent = original;
            }
        });
    });

    selectAll?.addEventListener('change', () => {
        document.querySelectorAll('.pluggy-tx-check').forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
    });

    async function processPendingWebhookEvents() {
        if (!config.webhookQueue) return;

        try {
            const result = await post('../actions/pluggy_eventos_processar.php');
            if ((Number(result.processados) || 0) > 0) {
                const url = new URL(window.location.href);
                if (url.searchParams.get('eventos') !== '1') {
                    url.searchParams.set('eventos', '1');
                    window.location.replace(url.toString());
                }
            }
        } catch (error) {
            // A fila é complementar. O usuário ainda pode sincronizar manualmente.
            console.warn('Não foi possível processar eventos bancários pendentes.');
        }
    }

    processPendingWebhookEvents();
})();
