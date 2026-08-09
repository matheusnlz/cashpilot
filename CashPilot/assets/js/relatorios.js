/**
 * CashPilot - Gráfico de despesas por categoria (Relatórios)
 */

function inicializarGraficoCategorias(dados) {
    const canvas = document.getElementById('graficoCategorias');
    if (!canvas || !Array.isArray(dados) || dados.length === 0) return;

    const paleta = ['#2F5D62', '#B5654A', '#7A6A53', '#3B6E71', '#5A6E5D', '#6B7280', '#8A7B5A', '#4B5563', '#7A3B3B', '#8A8A8A'];

    new Chart(canvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: dados.map(d => d.categoria),
            datasets: [{
                data: dados.map(d => d.total),
                backgroundColor: dados.map((_, i) => paleta[i % paleta.length]),
                borderWidth: 2,
                borderColor: '#FFFFFF',
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } },
            },
        },
    });
}
