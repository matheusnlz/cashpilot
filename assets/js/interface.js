(() => {

            'use strict';

            const norm = v => String(v || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

            function buscas() {

                 ['.data-list', '.supplier-grid', '.people-grid', '.catalog-grid', '.cost-list', '.action-plan-grid'].forEach(sel => document.querySelectorAll(sel).forEach(lista => {

                         if (lista.dataset.cpSearchReady === '1')
                                return;
              const itens = [...lista.children].filter(x => x.nodeType === 1);
              if (itens.length < 5)
                                return;
              lista.dataset.cpSearchReady = '1';
              const box = document.createElement('div');
              box.className = 'cp-page-search';
              const wrap = document.createElement('div');
              wrap.className = 'cp-page-search-wrap';
              const input = document.createElement('input');
              input.type = 'search';
              input.placeholder = 'Buscar nesta página...';
              input.setAttribute('aria-label', 'Buscar registros nesta página');
              const count = document.createElement('span');
              count.className = 'cp-page-search-count';
              count.textContent = `${itens.length} registros`;
              wrap.appendChild(input);
              box.append(wrap, count);
              lista.parentElement?.insertBefore(box, lista);
              input.addEventListener('input', () => {

                                 const q = norm(input.value);
                  let v = 0;
                  itens.forEach(item => {

                                         const ok = !q || norm(item.textContent).includes(q);
                      item.hidden = !ok;
                      if (ok)
                                                v++;

                });
                  count.textContent = `${v} de ${itens.length}`;

            });

        }));

    }

            function tips() {

                 [['.row-actions a', 'Editar'], ['.row-actions button.excluir', 'Excluir'], ['.catalog-actions a', 'Editar'], ['.catalog-actions button.excluir', 'Desativar'], ['.history-item form button', 'Excluir conversa']].forEach(([sel, p]) => document.querySelectorAll(sel).forEach(el => {

                         if (!el.dataset.cpTooltip)
                                el.dataset.cpTooltip = el.getAttribute('aria-label') || el.getAttribute('title') || p;

        }));

    }

            function icones() {

                 document.querySelectorAll('.transacao-icone,.movimentacao-icone').forEach(el => {

                         const t = el.textContent.trim();
              el.classList.add('cp-flow-icon');
              if (t.includes('↗'))
                                el.classList.add('entrada');

                            else if (t.includes('↘'))
                                el.classList.add('saida');

                            else if (t.includes('⇄'))
                                el.classList.add('transferencia');

        });

    }

            document.addEventListener('submit', e => {

                 const f = e.target;
          if (!(f instanceof HTMLFormElement))
                        return;
          if (f.dataset.confirm && f.dataset.confirmBypass !== '1')
                        return;
          const b = f.querySelector('button[type="submit"],button:not([type])');
          if (!b || b.classList.contains('cp-is-loading'))
                        return;
          b.classList.add('cp-is-loading');
          b.setAttribute('aria-busy', 'true');
          setTimeout(() => {

                         if (document.body.contains(b)) {

                                        b.classList.remove('cp-is-loading');

                                        b.removeAttribute('aria-busy');

            }

        }, 8000);

    }, true);

            document.addEventListener('DOMContentLoaded', () => {

                 buscas();
          tips();
          icones();
          document.documentElement.classList.add('cp134-ready');

    });

})();
