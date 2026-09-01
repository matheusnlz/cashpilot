function formatarMesLabel(mesStr) {

         const [ano, mes] = mesStr.split('-');

         const nomes = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

         return nomes[parseInt(mes, 10) - 1] + '/' + ano.slice(2);

}

let graficoEvolucaoAtual = null;

function inicializarGraficoEvolucao(fonte, canvasId = 'graficoEvolucao') {

            const canvas = document.getElementById(canvasId);

            if (!canvas)
                return;

            const pacote = Array.isArray(fonte) ? {

                 mensal: fonte, diario: []

    }

         : fonte || {

    }, mensal = Array.isArray(pacote.mensal) ? pacote.mensal : [], diario = Array.isArray(pacote.diario) ? pacote.diario : [];

            let periodoAtual = 1;

            function cores() {

                 const e = getComputedStyle(document.documentElement);

                 return {

                         destaque: e.getPropertyValue('--cor-destaque').trim() || '#2F5D62', erro: e.getPropertyValue('--cor-erro').trim() || '#B3453F', texto: e.getPropertyValue('--cor-texto-suave').trim() || '#6B6E76', borda: e.getPropertyValue('--cor-borda').trim() || '#E4E2DD'

        };

    }

            function render(periodo) {

                 periodoAtual = periodo;

                 let labels = [], receitas = [], despesas = [];

                 if (periodo === 1 && diario.length) {

                                labels = diario.map(d => String(d.dia).padStart(2, '0'));

                                receitas = diario.map(d => Number(d.receitas || 0));

                                despesas = diario.map(d => Number(d.despesas || 0));

        }
                    else {

                                const f = mensal.slice(-periodo);

                                labels = f.map(d => formatarMesLabel(d.mes));

                                receitas = f.map(d => Number(d.receitas || 0));

                                despesas = f.map(d => Number(d.despesas || 0));

        }

                 if (!labels.length) {

                                if (graficoEvolucaoAtual)
                                    graficoEvolucaoAtual.destroy();

                                return;

        }

                 const c = cores();

                 if (graficoEvolucaoAtual)
                        graficoEvolucaoAtual.destroy();

                 graficoEvolucaoAtual = new Chart(canvas.getContext('2d'), {

                         type: 'line', data: {

                                 labels, datasets: [ {

                                         label: 'Receitas', data: receitas, borderColor: c.destaque, backgroundColor: c.destaque + '1A', tension: .42, fill: true, pointRadius: periodo === 1 ? 0 : 2.5, pointHoverRadius: 5, borderWidth: 2.3, borderCapStyle: 'round', borderJoinStyle: 'round'

                }, {

                                         label: 'Despesas', data: despesas, borderColor: c.erro, backgroundColor: c.erro + '12', tension: .42, fill: true, pointRadius: periodo === 1 ? 0 : 2.5, pointHoverRadius: 5, borderWidth: 2.3, borderCapStyle: 'round', borderJoinStyle: 'round'

                }]

            }, options: {

                                 responsive: true, maintainAspectRatio: false, interaction: {

                                         mode: 'index', intersect: false

                }, plugins: {

                                         legend: {

                                                 position: 'bottom', labels: {

                                                         color: c.texto, boxWidth: 10, font: {

                                                                 size: 12

                            }

                        }

                    }, tooltip: {

                                                 callbacks: {

                                                         title: it => periodo === 1 ? `Dia ${it[0].label}` : it[0].label, label: x => `${x.dataset.label}: ${Number(x.raw || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}`

                        }

                    }

                }, scales: {

                                         x: {

                                                 grid: {

                                                         display: false

                        }, ticks: {

                                                         color: c.texto, font: {

                                                                 size: 10

                            }, maxTicksLimit: periodo === 1 ? 10 : 12

                        }

                    }, y: {

                                                 grid: {

                                                         color: c.borda

                        }, ticks: {

                                                         color: c.texto, font: {

                                                                 size: 10

                            }, callback: v => Number(v).toLocaleString('pt-BR', {

                                                                 notation: 'compact'

                            })

                        }

                    }

                }

            }

        });

    }

            render(1);

            document.querySelectorAll('.periodo[data-meses]').forEach(btn => btn.addEventListener('click', () => {

                 document.querySelectorAll('.periodo[data-meses]').forEach(b => b.classList.remove('ativo'));
          btn.classList.add('ativo');
          render(Number(btn.dataset.meses));

    }));

            document.querySelectorAll('.metrica-interativa').forEach(btn => btn.addEventListener('click', () => {

                 const d = document.getElementById(btn.dataset.detalhe);
          if (d)
                        d.hidden = !d.hidden;

    }));

            document.addEventListener('cashpilot:themechange', () => setTimeout(() => render(periodoAtual), 20));

}
