/* Proteção visual complementar. A garantia de idempotência fica no servidor. */
document.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) {
        return;
    }
    const destino = form.getAttribute('action') || '';
    if (!/actions\/(receitas|despesas|vendas|importar_confirmar)\.php/.test(destino)) {
        return;
    }
    if (form.dataset.cp17Enviando === '1') {
        event.preventDefault();
        return;
    }
    // Não desabilitar antes da serialização: botões podem conter name/value.
    window.setTimeout(function () {
        if (event.defaultPrevented) {
            return;
        }
        form.dataset.cp17Enviando = '1';
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button:not([type]), button[type="submit"], input[type="submit"]').forEach(
            function (botao) {
                if (!botao.disabled) {
                    botao.dataset.cp17Bloqueado = '1';
                    botao.disabled = true;
                }
            }
        );
    }, 0);
});

window.addEventListener('pageshow', function () {
    document.querySelectorAll('[data-cp17-enviando]').forEach(function (form) {
        delete form.dataset.cp17Enviando;
        form.removeAttribute('aria-busy');
    });
    document.querySelectorAll('[data-cp17-bloqueado]').forEach(function (botao) {
        botao.disabled = false;
        delete botao.dataset.cp17Bloqueado;
    });
});
