/**
 * CashPilot - Gráfico de evolução mensal (Dashboard)
 * Usa Chart.js (carregado via CDN no header.php)
 */

function formatarMesLabel(mesStr) {
    const [ano, mes] = mesStr.split('-');
    const nomesMeses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
    return nomesMeses[parseInt(mes, 10) - 1] + '/' + ano.slice(2);
}

function inicializarGraficoEvolucao(dados, canvasId = 'graficoEvolucao') {
    const canvas = document.getElementById(canvasId);
    if (!canvas || !Array.isArray(dados) || dados.length === 0) return;

    const estilos = getComputedStyle(document.documentElement);
    const corDestaque = estilos.getPropertyValue('--cor-destaque').trim() || '#2F5D62';
    const corErro = estilos.getPropertyValue('--cor-erro').trim() || '#B3453F';
    const corTexto = estilos.getPropertyValue('--cor-texto-suave').trim() || '#6B6E76';
    const corBorda = estilos.getPropertyValue('--cor-borda').trim() || '#E4E2DD';

    const labels = dados.map(d => formatarMesLabel(d.mes));
    const receitas = dados.map(d => d.receitas);
    const despesas = dados.map(d => d.despesas);

    new Chart(canvas.getContext('2d'), {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Receitas',
                    data: receitas,
                    borderColor: corDestaque,
                    backgroundColor: corDestaque + '22',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 3,
                },
                {
                    label: 'Despesas',
                    data: despesas,
                    borderColor: corErro,
                    backgroundColor: corErro + '18',
                    tension: 0.35,
                    fill: true,
                    pointRadius: 3,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: corTexto, boxWidth: 10, font: { size: 12 } } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: corTexto, font: { size: 11 } } },
                y: { grid: { color: corBorda }, ticks: { color: corTexto, font: { size: 11 } } },
            },
        },
    });
}
