(() => {
    const type = document.getElementById('cp152Scenario');
    if (!type) return;
    const cat = document.getElementById('cp152Category'),
        catGroup = document.getElementById('cp152CategoryGroup'),
        value = document.getElementById('cp152Value'),
        months = document.getElementById('cp152Months');
    const label = document.getElementById('cp152ValueLabel'),
        impact = document.getElementById('cp152Impact'),
        monthly = document.getElementById('cp152Monthly'),
        after = document.getElementById('cp152After'),
        text = document.getElementById('cp152ImpactText');
    const money = (n) =>
        Number(n || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    function calc() {
        const t = type.value,
            v = Math.max(0, Number(value.value) || 0),
            m = Math.max(1, Number(months.value) || 12),
            base = Math.max(0, Number(cat?.value) || 0);
        let per = 0,
            final = '—',
            desc = '';
        catGroup.hidden = t !== 'reduzir';
        label.textContent = t === 'reduzir' ? 'Redução desejada (%)' : 'Valor mensal (R$)';
        if (t === 'reduzir') {
            const pct = Math.min(100, v);
            per = (base * pct) / 100;
            final = money(base - per);
            desc = `Reduzindo ${pct.toLocaleString('pt-BR')}% desta categoria.`;
        }
        if (t === 'economizar') {
            per = v;
            final = money(v * m);
            desc = 'Valor acumulado se você mantiver esse ritmo.';
        }
        if (t === 'renda') {
            per = v;
            final = money(v * m);
            desc = 'Renda adicional acumulada no período.';
        }
        if (t === 'despesa') {
            per = -v;
            final = money(v * m);
            desc = 'Custo adicional acumulado no período.';
        }
        impact.textContent = money(per * m);
        monthly.textContent = money(per);
        after.textContent = final;
        text.textContent = desc;
        impact.classList.toggle('negativo', per < 0);
    }
    [type, cat, value, months].forEach((x) => x?.addEventListener('input', calc));
    calc();
})();
