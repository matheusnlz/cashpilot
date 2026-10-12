(() => {
    'use strict';

    function prepareSendButton(button) {
        if (!button || button.dataset.cp15Plane === '1') return;
        button.dataset.cp15Plane = '1';
        button.classList.add('cp15-send-button');
        button.innerHTML = '<span class="cp15-plane" aria-hidden="true">➤</span>';
    }

    function animateSend(form) {
        const button = form?.querySelector('button[type="submit"]');
        if (!button) return;
        prepareSendButton(button);
        button.classList.remove('cp15-waiting');
        void button.offsetWidth;
        button.classList.add('cp15-sending');
        setTimeout(() => {
            button.classList.remove('cp15-sending');
            button.classList.add('cp15-waiting');
        }, 620);
    }
    function finishSend(form) {
        const b = form?.querySelector('button[type="submit"]');
        b?.classList.remove('cp15-waiting', 'cp15-sending');
    }

    ['copilotoForm'].forEach((id) => {
        const form = document.getElementById(id);
        if (!form) return;
        const button = form.querySelector('button[type="submit"]');
        prepareSendButton(button);
        form.addEventListener('submit', () => animateSend(form), { capture: true });
    });

    // O próprio fluxo do chat pode disparar este evento ao finalizar; o fallback
    // também restaura o botão após alguns segundos para nunca deixá-lo travado.
    document.addEventListener('cashpilot:copiloto:done', () => {
        finishSend(document.getElementById('copilotoForm'));
    });
    setInterval(
        () =>
            document
                .querySelectorAll('.cp15-send-button.cp15-waiting')
                .forEach((b) => b.classList.remove('cp15-waiting')),
        12000
    );

    document.querySelectorAll('.surface-card').forEach((el, i) => {
        if (i < 10) el.classList.add('cp15-enter');
    });
    document.querySelectorAll('[style*="width:"]').forEach((el) => {
        if (el.matches('span,i') && !el.closest('.cp14-allocation-bar'))
            el.classList.add('cp15-progress-animate');
    });

    // Telefone: valida o DDD também no cliente sem substituir a validação do servidor.
    const phone = document.getElementById('telefone');
    if (phone) {
        const validDDDs = new Set([
            11, 12, 13, 14, 15, 16, 17, 18, 19, 21, 22, 24, 27, 28, 31, 32, 33, 34, 35, 37, 38, 41,
            42, 43, 44, 45, 46, 47, 48, 49, 51, 53, 54, 55, 61, 62, 64, 63, 65, 66, 67, 68, 69, 71,
            73, 74, 75, 77, 79, 81, 87, 82, 83, 84, 85, 88, 86, 89, 91, 93, 94, 92, 97, 95, 96, 98,
            99
        ]);
        phone.addEventListener('blur', () => {
            const d = (phone.value || '').replace(/\D/g, '');
            if (d.length >= 2 && !validDDDs.has(Number(d.slice(0, 2)))) {
                phone.setCustomValidity('Informe um DDD brasileiro válido.');
            } else {
                phone.setCustomValidity('');
            }
        });
        phone.addEventListener('input', () => phone.setCustomValidity(''));
    }
})();
