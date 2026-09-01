/* CashPilot 13.4.1 — consistência visual e controles */
(() => {

            'use strict';

            function classificarIconesFinanceiros() {

                        document.querySelectorAll('.transacao-icone, .movimentacao-icone, .cp-flow-icon').forEach((el) => {

                                    const texto = (el.textContent || '').trim();

                                    const classes = el.className || '';

                                    if (/receita|entrada/.test(classes) || texto.includes('↗')) {

                                                el.classList.add('entrada');

            }
                                    else if (/despesa|saida/.test(classes) || texto.includes('↘')) {

                                                el.classList.add('saida');

            }
                                    else if (/transfer/.test(classes) || texto.includes('⇄')) {

                                                el.classList.add('transferencia');

            }

        });

    }

            function melhorarBotoesIcone() {

                        const regras = [
                            ['.row-actions a', 'Editar'],
                            ['.row-actions button', 'Excluir'],
                            ['.catalog-actions a', 'Editar'],
                            ['.catalog-actions button', 'Desativar'],
                            ['.drawer-close', 'Fechar']
                        ];

                        regras.forEach(([selector, label]) => {

                                    document.querySelectorAll(selector).forEach((el) => {

                                                if (!el.getAttribute('aria-label') && !el.textContent.trim()) {

                                                            el.setAttribute('aria-label', label);

                }

            });

        });

    }

            document.addEventListener('DOMContentLoaded', () => {

                        classificarIconesFinanceiros();

                        melhorarBotoesIcone();

    });

})();
