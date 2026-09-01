(() => {

            'use strict';

            const dados = window.CASH_PILOT_COMPRA || {

    };

            const form = document.getElementById('pcForm');

            const resultado = document.getElementById('pcResultado');

            const forma = document.getElementById('pcForma');

            const parcelamento = document.getElementById('pcParcelamento');

            if (!form || !resultado || !forma || !parcelamento) {

                        return;

    }

            const moeda = (valor) => Number(valor || 0).toLocaleString('pt-BR', {

                        style: 'currency',
                        currency: 'BRL'

    });

            const percentual = (valor) => {

                        if (!Number.isFinite(valor)) {

                                    return '—';

        }

                        return `${valor.toFixed(0)}%`;

    };

            const escapeHtml = (texto) => String(texto || '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            function atualizarForma() {

                        const parcelado = forma.value === 'parcelado';

                        parcelamento.hidden = !parcelado;

    }

            function calcularParcela(principal, parcelas, jurosMensal) {

                        if (principal <= 0) {

                                    return 0;

        }

                        if (jurosMensal <= 0) {

                                    return principal / parcelas;

        }

                        const taxa = jurosMensal / 100;

                        return principal * taxa / (1 - Math.pow(1 + taxa, -parcelas));

    }

            function avaliarCompra(simulacao) {

                        const folga = Number(dados.folgaMedia || 0);

                        const receita = Number(dados.receitaMedia || 0);

                        const saldo = Number(dados.saldoRegistrado || 0);

                        const reservaMeses = Number(dados.reservaMeses || 0);

                        const metasMensais = Number(dados.metasMensais || 0);

                        const disponivelPlanejado = dados.temPlanejamento
                            ? Number(dados.disponivelPlanejado || 0)
                            : null;

                        const compromissoMensal = simulacao.parcelado ? simulacao.parcela : 0;

                        const desembolsoAgora = simulacao.parcelado ? simulacao.entrada : simulacao.valor;

                        const folgaDepois = folga - compromissoMensal;

                        const pctFolga = folga > 0 ? compromissoMensal / folga * 100 : Infinity;

                        const pctReceita = receita > 0 ? compromissoMensal / receita * 100 : Infinity;

                        const saldoDepois = saldo - desembolsoAgora;

                        const metasComCompra = metasMensais + compromissoMensal;

                        let pontos = 0;

                        const motivos = [];

                        const atencoes = [];

                        const positivos = [];

                        if (simulacao.parcelado) {

                                    if (folga <= 0) {

                                                pontos += 4;

                                                atencoes.push('Sua média dos últimos 3 meses não apresenta folga positiva para absorver uma nova parcela.');

            }
                                    else if (pctFolga > 100) {

                                                pontos += 4;

                                                atencoes.push('A parcela supera toda a folga média mensal observada pelo CashPilot.');

            }
                                    else if (pctFolga > 50) {

                                                pontos += 3;

                                                atencoes.push(`A parcela consumiria cerca de ${percentual(pctFolga)} da sua folga média mensal.`);

            }
                                    else if (pctFolga > 30) {

                                                pontos += 2;

                                                atencoes.push(`A parcela consumiria cerca de ${percentual(pctFolga)} da sua folga média mensal.`);

            }
                                    else if (pctFolga > 0) {

                                                positivos.push(`A parcela utiliza aproximadamente ${percentual(pctFolga)} da folga média mensal.`);

            }

                                    if (pctReceita > 20) {

                                                pontos += 2;

                                                atencoes.push(`A parcela representa aproximadamente ${percentual(pctReceita)} da sua receita média mensal.`);

            }
                                    else if (pctReceita > 0 && Number.isFinite(pctReceita)) {

                                                positivos.push(`A parcela representa cerca de ${percentual(pctReceita)} da receita média.`);

            }

                                    if (simulacao.juros > 0 && simulacao.jurosTotais > 0) {

                                                pontos += simulacao.jurosTotais > simulacao.valor * 0.2 ? 2 : 1;

                                                atencoes.push(`O parcelamento adiciona aproximadamente ${moeda(simulacao.jurosTotais)} ao custo da compra.`);

            }

                                    if (metasMensais > 0 && folga > 0 && metasComCompra > folga) {

                                                pontos += 2;

                                                atencoes.push('Parcela e ritmo necessário das metas, juntos, ultrapassam a folga média observada.');

            }

        }
                        else {

                                    if (saldo <= 0) {

                                                pontos += 4;

                                                atencoes.push('O saldo financeiro acompanhado pelo CashPilot não está positivo para absorver o pagamento à vista.');

            }
                                    else if (simulacao.valor > saldo) {

                                                pontos += 4;

                                                atencoes.push('O valor da compra é maior que o saldo financeiro atualmente acompanhado.');

            }
                                    else if (simulacao.valor > saldo * 0.5) {

                                                pontos += 3;

                                                atencoes.push(`A compra consumiria mais da metade do saldo acompanhado e deixaria cerca de ${moeda(saldoDepois)}.`);

            }
                                    else if (simulacao.valor > saldo * 0.25) {

                                                pontos += 1;

                                                atencoes.push(`A compra reduziria o saldo acompanhado para aproximadamente ${moeda(saldoDepois)}.`);

            }
                                    else {

                                                positivos.push(`Após a compra, o saldo acompanhado ficaria em aproximadamente ${moeda(saldoDepois)}.`);

            }

        }

                        if (reservaMeses < 1) {

                                    pontos += 2;

                                    atencoes.push('Sua reserva registrada cobre menos de 1 mês de gastos essenciais, reduzindo a margem para imprevistos.');

        }
                        else if (reservaMeses >= 3) {

                                    positivos.push(`A reserva registrada cobre cerca de ${reservaMeses.toFixed(1).replace('.', ',')} meses de gastos essenciais.`);

        }

                        if (disponivelPlanejado !== null) {

                                    const impactoPlanejado = simulacao.parcelado
                                        ? compromissoMensal
                                        : simulacao.valor;

                                    if (disponivelPlanejado <= 0) {

                                                pontos += 2;

                                                atencoes.push('O planejamento mensal já não apresenta valor disponível para um novo compromisso.');

            }
                                    else if (impactoPlanejado > disponivelPlanejado) {

                                                pontos += 2;

                                                atencoes.push(`O impacto da compra é maior que os ${moeda(disponivelPlanejado)} ainda disponíveis no planejamento.`);

            }
                                    else {

                                                positivos.push('O impacto inicial fica dentro do valor ainda disponível no planejamento informado.');

            }

        }
                        else {

                                    motivos.push('Sem planejamento mensal, a leitura fica mais dependente do histórico de receitas e despesas.');

        }

                        let nivel = 'confortavel';

                        let titulo = 'Impacto controlado pelos dados atuais';

                        let descricao = 'A compra parece ocupar uma parcela limitada da margem financeira registrada.';

                        if (pontos >= 8) {

                                    nivel = 'critico';

                                    titulo = 'Impacto muito alto no cenário atual';

                                    descricao = 'Os dados registrados mostram pouca margem para assumir este compromisso sem pressionar outras áreas.';

        }
                        else if (pontos >= 5) {

                                    nivel = 'alto';

                                    titulo = 'A compra pressiona o orçamento';

                                    descricao = 'O compromisso tem peso relevante e merece revisão antes de ser assumido.';

        }
                        else if (pontos >= 2) {

                                    nivel = 'atencao';

                                    titulo = 'Cabe com atenção';

                                    descricao = 'A compra pode caber nos números atuais, mas reduz sua margem para metas, planejamento ou imprevistos.';

        }

                        if (atencoes.length === 0) {

                                    motivos.push('Nenhum sinal forte de pressão foi identificado com os dados atualmente cadastrados.');

        }

                        return {

                                    nivel,
                                    titulo,
                                    descricao,
                                    pontos,
                                    compromissoMensal,
                                    desembolsoAgora,
                                    folgaDepois,
                                    pctFolga,
                                    pctReceita,
                                    saldoDepois,
                                    positivos,
                                    atencoes,
                                    motivos

        };

    }

            function renderizar(simulacao, leitura) {

                        const nome = escapeHtml(simulacao.nome || 'Compra');

                        const formaTexto = simulacao.parcelado
                            ? `${simulacao.parcelas}x de ${moeda(simulacao.parcela)}`
                            : 'Pagamento à vista';

                        const totalTexto = simulacao.parcelado
                            ? moeda(simulacao.totalPago)
                            : moeda(simulacao.valor);

                        const listaPositivos = leitura.positivos.length
                            ? leitura.positivos.map((item) => `<li><i>✓</i><span>${escapeHtml(item)}</span></li>`).join('')
                            : '<li class="neutro"><i>•</i><span>Nenhum ponto positivo adicional foi identificado nesta simulação.</span></li>';

                        const listaAtencoes = leitura.atencoes.length
                            ? leitura.atencoes.map((item) => `<li><i>!</i><span>${escapeHtml(item)}</span></li>`).join('')
                            : '<li class="neutro"><i>✓</i><span>Nenhum alerta forte foi identificado com os dados atuais.</span></li>';

                        const listaContexto = leitura.motivos.length
                            ? `<div class="cp1431-result-note">${leitura.motivos.map((item) => `<p>$ {

                        escapeHtml(item)

        }

                </p>`).join('')}</div>`
                            : '';

                        const antesDepois = simulacao.parcelado
                            ? `
                <div><span>Folga média antes</span><strong>${moeda(dados.folgaMedia)}</strong></div>
                <div><span>Nova parcela</span><strong>− ${moeda(simulacao.parcela)}</strong></div>
                <div><span>Folga estimada depois</span><strong class="${leitura.folgaDepois >= 0 ? 'positivo' : 'negativo'}">${moeda(leitura.folgaDepois)}</strong></div>
            `
                            : `
                <div><span>Saldo acompanhado antes</span><strong>${moeda(dados.saldoRegistrado)}</strong></div>
                <div><span>Pagamento agora</span><strong>− ${moeda(simulacao.valor)}</strong></div>
                <div><span>Saldo acompanhado depois</span><strong class="${leitura.saldoDepois >= 0 ? 'positivo' : 'negativo'}">${moeda(leitura.saldoDepois)}</strong></div>
            `;

                        const custos = simulacao.parcelado
                            ? `
                <div><span>Entrada</span><strong>${moeda(simulacao.entrada)}</strong></div>
                <div><span>Parcela</span><strong>${moeda(simulacao.parcela)}</strong></div>
                <div><span>Total estimado</span><strong>${totalTexto}</strong></div>
                <div><span>Juros totais</span><strong>${moeda(simulacao.jurosTotais)}</strong></div>
            `
                            : `
                <div><span>Valor da compra</span><strong>${moeda(simulacao.valor)}</strong></div>
                <div><span>Forma</span><strong>À vista</strong></div>
                <div><span>% do saldo acompanhado</span><strong>${Number(dados.saldoRegistrado) > 0 ? percentual(simulacao.valor / Number(dados.saldoRegistrado) * 100) : '—'}</strong></div>
                <div><span>Reserva registrada</span><strong>${moeda(dados.reservaAtual)}</strong></div>
            `;

                        const perguntaCopiloto = escapeHtml(`Estou avaliando ${simulacao.nome || 'uma compra'} por ${moeda(simulacao.valor)}, ` +
                            `${simulacao.parcelado ? `$ {

                        simulacao.parcelas

        }

                 parcelas de $ {

                        moeda(simulacao.parcela)

        }

                ` : 'à vista'}. ` +
                            `O CashPilot classificou o impacto como ${leitura.titulo}. ` +
                            'Explique os principais riscos e o que eu deveria conferir nos meus próprios dados antes de decidir, sem decidir por mim.');

                        resultado.innerHTML = `
            <div class="cp1431-result-hero ${leitura.nivel}">
                <div>
                    <span class="eyebrow">LEITURA DA COMPRA</span>
                    <h2>${nome}</h2>
                    <p>${escapeHtml(formaTexto)} · ${totalTexto} no total estimado</p>
                </div>
                <div class="cp1431-impact-badge">
                    <span>Impacto</span>
                    <strong>${escapeHtml(leitura.titulo)}</strong>
                </div>
            </div>

            <div class="cp1431-result-grid">
                <section class="surface-card">
                    <span class="eyebrow">ANTES × DEPOIS</span>
                    <h3>O que muda no seu caixa</h3>
                    <div class="cp1431-comparison">${antesDepois}</div>
                </section>

                <section class="surface-card">
                    <span class="eyebrow">CUSTO DA DECISÃO</span>
                    <h3>Detalhes financeiros</h3>
                    <div class="cp1431-cost-grid">${custos}</div>
                </section>
            </div>

            <div class="cp1431-result-grid">
                <section class="surface-card">
                    <span class="eyebrow">SINAIS FAVORÁVEIS</span>
                    <h3>O que ajuda esta compra</h3>
                    <ul class="cp1431-buy-signals positivo-list">${listaPositivos}</ul>
                </section>

                <section class="surface-card">
                    <span class="eyebrow">PONTOS DE ATENÇÃO</span>
                    <h3>O que pode pressionar seu orçamento</h3>
                    <ul class="cp1431-buy-signals alerta-list">${listaAtencoes}</ul>
                </section>
            </div>

            <section class="surface-card cp1431-result-conclusion">
                <div>
                    <span class="eyebrow">LEITURA FINAL</span>
                    <h3>${escapeHtml(leitura.titulo)}</h3>
                    <p>${escapeHtml(leitura.descricao)}</p>
                    ${listaContexto}
                </div>
                <button
                    class="btn btn-secundario"
                    type="button"
                    data-copiloto-pergunta="${perguntaCopiloto}"
                >
                    ✦ Analisar com o Copiloto
                </button>
            </section>
        `;

                        resultado.hidden = false;

                        resultado.scrollIntoView( {

                         behavior: 'smooth', block: 'start'

        });

    }

            forma.addEventListener('change', atualizarForma);

            atualizarForma();

            form.addEventListener('submit', (event) => {

                        event.preventDefault();

                        const nome = document.getElementById('pcNome').value.trim() || 'Compra';

                        const valor = Number(document.getElementById('pcValor').value || 0);

                        const parcelado = forma.value === 'parcelado';

                        const entradaInformada = Number(document.getElementById('pcEntrada').value || 0);

                        const parcelas = Math.max(2, Number(document.getElementById('pcParcelas').value || 2));

                        const juros = Math.max(0, Number(document.getElementById('pcJuros').value || 0));

                        if (!Number.isFinite(valor) || valor <= 0) {

                                    return;

        }

                        const entrada = parcelado ? Math.min(Math.max(0, entradaInformada), valor) : valor;

                        const principal = parcelado ? Math.max(0, valor - entrada) : 0;

                        const parcela = parcelado ? calcularParcela(principal, parcelas, juros) : 0;

                        const totalPago = parcelado ? entrada + parcela * parcelas : valor;

                        const jurosTotais = Math.max(0, totalPago - valor);

                        const simulacao = {

                                    nome,
                                    valor,
                                    parcelado,
                                    entrada,
                                    parcelas,
                                    juros,
                                    principal,
                                    parcela,
                                    totalPago,
                                    jurosTotais

        };

                        renderizar(simulacao, avaliarCompra(simulacao));

    });

})();
