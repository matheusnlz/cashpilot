    </div>
</main>
</div>
<div class="drawer-overlay" id="drawerOverlay">
</div>
<div class="cp-confirm-overlay" id="cpConfirmOverlay" hidden>
<section class="cp-confirm-modal" role="dialog" aria-modal="true" aria-labelledby="cpConfirmTitle">
<div class="cp-confirm-icon">!</div>
<div>
<h2 id="cpConfirmTitle">Confirmar ação</h2>
<p id="cpConfirmMessage">Deseja continuar?</p>
</div>
<div class="cp-confirm-actions">
<button type="button" class="btn btn-secundario" id="cpConfirmCancel">Cancelar</button>
<button type="button" class="btn btn-perigo" id="cpConfirmAccept">Confirmar</button>
</div>
</section>
</div>

<?php require __DIR__ . '/primeira_visita.php';?>


<?php if (basename($_SERVER['PHP_SELF']) !== 'copiloto.php'):?>
<button type="button" class="copiloto-botao" id="copilotoBotao" aria-label="Abrir Copiloto">
<span>✦</span>
<i>Copiloto</i>
</button>
<section class="copiloto-widget" id="copiloto" hidden aria-label="Copiloto CashPilot">
<div class="copiloto-resize-handle" id="copilotoResizeHandle" aria-hidden="true">
</div>
<header class="copiloto-widget-head">
<div class="copiloto-identidade">
<span class="copiloto-avatar">✦</span>
<div>
<strong>Copiloto CashPilot</strong>
<small>
<i class="status-online">
</i> conectado aos seus dados</small>
</div>
</div>
<div class="copiloto-widget-actions">
<button type="button" id="copilotoNovaConversa" title="Nova conversa" aria-label="Nova conversa">＋</button>
<a href="copiloto.php" class="copiloto-expandir" id="copilotoExpandir" title="Expandir" aria-label="Expandir Copiloto">↗</a>
<button type="button" id="copilotoFechar" aria-label="Fechar">×</button>
</div>
</header>
<div class="copiloto-widget-contexto">
<span>✦</span>
<p id="copilotoContextoTexto">Preparando o contexto desta página...</p>
</div>
<div class="copiloto-mensagens" id="copilotoMensagens">
<div class="copiloto-context-ready">
<span>✦</span>
<div>
<strong>Contexto preparado</strong>
<p id="copilotoPreparacaoTexto">Os dados desta área serão priorizados quando você fizer uma pergunta.</p>
</div>
</div>
</div>
<div class="copiloto-sugestoes" id="copilotoSugestoes">
</div>
<form id="copilotoForm" class="copiloto-widget-form" autocomplete="off">
<textarea id="copilotoInput" autocomplete="off" maxlength="800" rows="1" placeholder="Pergunte ao Copiloto...">
</textarea>
<button type="submit" aria-label="Enviar">➜</button>
</form>
</section>
<?php endif;?>

<script>
(() => {
'use strict';

const cb=document.getElementById('copilotoBotao'),
      cp=document.getElementById('copiloto'),
      cf=document.getElementById('copilotoFechar'),
      cn=document.getElementById('copilotoNovaConversa'),
      cm=document.getElementById('copilotoMensagens'),
      copilotoFormWidget=document.getElementById('copilotoForm'),
      copilotoInputWidget=document.getElementById('copilotoInput');


const csrfCopiloto=<?= json_encode(csrfToken(),JSON_UNESCAPED_UNICODE)?>;

const perfilCopiloto=<?= json_encode(usuarioLogadoTipo(),JSON_UNESCAPED_UNICODE)?>;

const paginaCopiloto=location.pathname.split('/').pop()||'dashboard.php';

let conversaWidgetId=Number(sessionStorage.getItem('cashpilot_copiloto_widget_conversa')||0)||null;

const sugestoesCopiloto={

 'dashboard.php': perfilCopiloto==='mei'
   ? ['Explique meu negócio','Como está minha previsão de caixa?','Qual ponto merece mais atenção?']
   : ['Explique meu mês','Como está meu CashScore?','Onde estou gastando mais?'],
 'metas.php':['Analise esta meta','Quanto devo guardar por mês?','Meu ritmo está adequado?'],
 'orcamentos.php':['Estou perto de algum limite?','Onde devo reduzir gastos?','Sugira ajustes no meu orçamento'],
 'saude_financeira.php':['Explique meu CashScore','Como está minha reserva?','Crie um plano para melhorar minha saúde financeira'],
 'relatorios.php':['Compare meus últimos meses','Explique este relatório','Qual foi minha maior mudança?'],
 'produtos_servicos.php':['Qual item tem maior margem?','O que mais vende?','Minha precificação merece atenção?'],
 'vendas.php':['Como está meu ticket médio?','Quais vendas mais contribuem para o resultado?','Como está minha margem?'],
 'funcionarios.php':['Quanto minha equipe custa?','Qual o impacto de contratar mais alguém?','Minha folha está pesada?'],
 'fornecedores.php':['Qual fornecedor pesa mais?','Tenho concentração em algum fornecedor?','Como reduzir custos com fornecedores?'],
 'custos.php':['Como estão meus custos fixos e variáveis?','Qual custo mais pesa?','Crie um plano para reduzir custos'],
 'desempenho.php':['Explique meu desempenho','Qual produto é mais lucrativo?','Analise minha previsão de caixa'],
 'radar.php':['Explique meus alertas','Qual alerta devo priorizar?','Crie um plano de ação'],
 'planos_acao.php':['Qual plano devo priorizar?','Analise meus planos ativos','Crie um novo plano de ação']
}
;

const contextoPaginaCopiloto={

 'dashboard.php':'Visão geral, evolução e indicadores principais preparados.',
 'receitas.php':'Receitas, categorias e movimentações recentes preparadas.',
 'despesas.php':'Despesas, categorias e movimentações recentes preparadas.',
 'transacoes.php':'Histórico de receitas e despesas preparado.',
 'metas.php':'Metas, prazos e planejamento preparados.',
 'orcamentos.php':'Limites por categoria e gastos do mês preparados.',
 'recorrencias.php':'Compromissos recorrentes e planejamento preparados.',
 'saude_financeira.php':'CashScore, reserva e fatores financeiros preparados.',
 'relatorios.php':'Comparações, categorias e evolução financeira preparadas.',
 'produtos_servicos.php':'Catálogo, custos, preços, estoque e margem preparados.',
 'vendas.php':'Vendas, ticket, itens e margem preparados.',
 'funcionarios.php':'Equipe e custos com funcionários preparados.',
 'fornecedores.php':'Fornecedores, periodicidades e custos preparados.',
 'custos.php':'Custos fixos e variáveis preparados.',
 'desempenho.php':'Desempenho, margem, vendas e previsão de caixa preparados.',
 'radar.php':'Alertas e pontos de atenção preparados.'
}
;

const textoContexto=contextoPaginaCopiloto[paginaCopiloto]||(perfilCopiloto==='mei'?'Dados financeiros e operacionais do negócio preparados.':'Dados financeiros atuais preparados.');

const contextoTextoEl=document.getElementById('copilotoContextoTexto');

const preparacaoTextoEl=document.getElementById('copilotoPreparacaoTexto');

if(contextoTextoEl)contextoTextoEl.textContent=textoContexto;

if(preparacaoTextoEl)preparacaoTextoEl.textContent=textoContexto+' Faça uma pergunta quando quiser.';


const sugestoesPadrao=perfilCopiloto==='mei'
 ? ['Explique meu negócio','Como está minha margem?','Quais custos merecem atenção?']
 : ['Explique meu mês','Como está meu orçamento?','Como estão minhas metas?'];


function sugestoesRelacionadasWidget(pergunta){

    const p=String(pergunta||'').toLowerCase();

    if(/receita|renda|entrada/.test(p))return ['Compare com o mês passado','Qual fonte mais cresceu?','Existe alguma tendência?'];

    if(/despesa|gasto|categoria|custo/.test(p))return ['Qual categoria mais pesa?','Compare com o mês passado','O que devo reduzir primeiro?'];

    if(/meta|objetivo/.test(p))return ['Estou no ritmo certo?','Quanto preciso guardar?','Monte um plano para esta meta'];

    if(/venda|produto|margem|ticket/.test(p))return ['Qual item é mais rentável?','Compare com o mês anterior','O que devo priorizar?'];

    if(/caixa|previs/.test(p))return ['Quais compromissos pesam mais?','O que pode pressionar o caixa?','Monte um plano de ação'];

    if(/score|reserva/.test(p))return ['O que mais influencia isso?','Como posso melhorar?','Monte um plano de 3 passos'];

    return ['Aprofunde essa análise','Compare com o mês passado','O que devo fazer primeiro?'];

}


function renderSugestoesWidget(lista){

    const alvo=document.getElementById('copilotoSugestoes');

    if(!alvo)return;

    alvo.innerHTML='';

    (lista||[]).slice(0,4).forEach(s=>{
        const b=document.createElement('button');
        b.type='button';b.textContent=s;
        b.addEventListener('click',()=>enviarMensagemCopiloto(s));
        alvo.appendChild(b);
    });

}


function escaparChat(v){

    return String(v).replace(/[&<>]/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[s]));

}


function formatarMarkdownSeguro(texto){

    let s=escaparChat(texto||'').replace(/\r\n?/g,'\n');

    s=s.replace(/^###\s+(.+)$/gm,'<h4>$1</h4>')
       .replace(/^##\s+(.+)$/gm,'<h3>$1</h3>')
       .replace(/^#\s+(.+)$/gm,'<h2>$1</h2>')
       .replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>');

    const linhas=s.split('\n');

    let html='',lista=null;

    const fechar=()=>{
if(lista){
html+=lista==='ol'?'</ol>':'</ul>';
lista=null}
}
;

    linhas.forEach(l=>{
        const num=l.match(/^\s*\d+[.)]\s+(.+)/);

        const bullet=l.match(/^\s*[-•]\s+(.+)/);

        if(num){
if(lista!=='ol'){
fechar();
html+='<ol>';
lista='ol'}
html+='<li>'+num[1]+'</li>';
return}

        if(bullet){
if(lista!=='ul'){
fechar();
html+='<ul>';
lista='ul'}
html+='<li>'+bullet[1]+'</li>';
return}

        fechar();

        if(/^<h[234]>/.test(l)){
html+=l;
return}

        if(l.trim()===''){
return}

        html+='<p>'+l+'</p>';

    }
);

    fechar();

    return '<div class="cp-markdown">'+html+'</div>';

}


function formatarValorUI(item){

    const v=Number(item?.valor||0);

    if(item?.formato==='percentual')return v.toLocaleString('pt-BR',{maximumFractionDigits:1})+'%';

    if(item?.formato==='meses')return v.toLocaleString('pt-BR',{maximumFractionDigits:1})+' mês(es)';

    return v.toLocaleString('pt-BR',{style:'currency',currency:'BRL'});

}

function renderCopilotoUI(ui) {
    if (!ui) {
        return '';
    }

    let html = '<div class="copiloto-ui-card">';

    if (ui.titulo) {
        html += '<strong class="copiloto-ui-title">' +
            escaparChat(ui.titulo) +
            '</strong>';
    }

    if (ui.tipo === 'score') {
        html += `
            <div class="copiloto-score-mini">
                <b>${Number(ui.score || 0)}</b>
                <span>/100 · ${escaparChat(ui.nivel || '')}</span>
            </div>
        `;
    }

    if (Array.isArray(ui.metricas) && ui.metricas.length) {
        html += '<div class="copiloto-ui-metrics">' +
            ui.metricas.map((metrica) => `
                <div>
                    <span>${escaparChat(metrica.rotulo || '')}</span>
                    <strong>${escaparChat(formatarValorUI(metrica))}</strong>
                </div>
            `).join('') +
            '</div>';
    }

    if (Array.isArray(ui.itens) && ui.itens.length) {
        const maximo = Math.max(
            ...ui.itens.map((item) => Number(item.valor || 0)),
            1
        );

        html += '<div class="copiloto-ui-bars">' +
            ui.itens.map((item) => {
                const largura = Math.min(
                    100,
                    Number(item.valor || 0) / maximo * 100
                );

                const valor = Number(item.valor || 0).toLocaleString(
                    'pt-BR',
                    {
                        style: 'currency',
                        currency: 'BRL',
                    }
                );

                return `
                    <div>
                        <div>
                            <span>${escaparChat(item.nome || '')}</span>
                            <b>${escaparChat(valor)}</b>
                        </div>
                        <i>
                            <em style="width:${largura}%"></em>
                        </i>
                    </div>
                `;
            }).join('') +
            '</div>';
    }

    if (Array.isArray(ui.ranking) && ui.ranking.length) {
        html += '<div class="copiloto-ui-ranking">' +
            ui.ranking.map((item, indice) => {
                const valor = Number(item.valor || 0).toLocaleString(
                    'pt-BR',
                    {
                        style: 'currency',
                        currency: 'BRL',
                    }
                );

                return `
                    <div>
                        <b>${indice + 1}</b>
                        <span>${escaparChat(item.nome || '')}</span>
                        <strong>${escaparChat(valor)}</strong>
                    </div>
                `;
            }).join('') +
            '</div>';
    }

    if (ui.plano && Array.isArray(ui.plano.itens)) {
        const payload = encodeURIComponent(JSON.stringify(ui.plano));

        html +=
            '<button type="button" class="copiloto-save-plan" ' +
            'data-save-plan="' + payload + '">' +
            '✓ Salvar como plano de ação</button>';
    }

    return html + '</div>';
}

function abrirCopiloto(){

    if(!cp||!copilotoInputWidget)return;

    cp.hidden=false;

    requestAnimationFrame(()=>cp.classList.add('aberto'));

    setTimeout(()=>copilotoInputWidget.focus(),120);

}


function fecharCopiloto(){

    if(!cp)return;

    cp.classList.remove('aberto');

    setTimeout(()=>cp.hidden=true,170);

}


async function enviarMensagemCopiloto(msg){

    if(!msg||!cm)return;

    abrirCopiloto();


    cm.insertAdjacentHTML(
        'beforeend',
        '<div class="copiloto-msg usuario">'+escaparChat(msg)+'</div>'
    );


    cm.insertAdjacentHTML(
        'beforeend',
        `
            <div class="copiloto-msg assistente copiloto-carregando">
                <span class="msg-origem">Copiloto</span>
                <span class="digitando">
                    <i></i>
                    <i></i>
                    <i></i>
                </span>
                <small>Analisando seus dados...</small>
            </div>
        `
    );


    cm.scrollTop=cm.scrollHeight;

    const carregando=cm.querySelector('.copiloto-carregando:last-child');


    const fd=new FormData();

    fd.append('mensagem',msg);

    fd.append('csrf_token',csrfCopiloto);

    fd.append('pagina',paginaCopiloto);

    fd.append('salvar_historico','1');

    if(conversaWidgetId)fd.append('conversa_id',String(conversaWidgetId));


    try{

        const r=await fetch('../actions/chatbot.php',{
            method:'POST',
            body:fd,
            headers:{'X-Requested-With':'XMLHttpRequest'}
        });


        const texto=await r.text();

        let d;


        try{
 d=JSON.parse(texto);
 }

        catch(_){
 throw new Error('Resposta inválida do servidor');
 }


        if(!r.ok)throw new Error(d.resposta||'Falha no Copiloto');

        if(d.conversa_id){

            conversaWidgetId=Number(d.conversa_id);

            sessionStorage.setItem('cashpilot_copiloto_widget_conversa',String(conversaWidgetId));

            atualizarLinkExpandirCopiloto();

        }


        if(carregando){

            carregando.outerHTML =
                '<div class="copiloto-msg assistente">' +
                '<span class="msg-origem">Copiloto</span>' +
                formatarMarkdownSeguro(
                    d.resposta || 'Não consegui responder agora.'
                ) +
                renderCopilotoUI(d.ui) +
                '</div>';

        }

        renderSugestoesWidget(sugestoesRelacionadasWidget(msg));

    }
catch(e){

        if(carregando){

            carregando.outerHTML =
                '<div class="copiloto-msg assistente">' +
                '<span class="msg-origem">Copiloto</span>' +
                'Não foi possível consultar o Copiloto agora.' +
                '</div>';

        }

        renderSugestoesWidget(['Tentar novamente','Explique esta página']);

    }


    cm.scrollTop=cm.scrollHeight;

}


window.abrirCopilotoComMensagem=function(msg){

    enviarMensagemCopiloto(msg);

}
;


cb?.addEventListener('click',abrirCopiloto);

cf?.addEventListener('click',fecharCopiloto);

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        fecharCopiloto();
    }
});


cn?.addEventListener('click',()=>{
    conversaWidgetId=null;
    sessionStorage.removeItem('cashpilot_copiloto_widget_conversa');
    atualizarLinkExpandirCopiloto();
    if (cm) {
        cm.innerHTML = `
            <div class="copiloto-context-ready">
                <span>✦</span>
                <div>
                    <strong>Nova conversa pronta</strong>
                    <p>${escaparChat(textoContexto)} Faça uma pergunta quando quiser.</p>
                </div>
            </div>
        `;
    }
    renderSugestoesWidget(sugestoesCopiloto[paginaCopiloto]||sugestoesPadrao);
});


renderSugestoesWidget(sugestoesCopiloto[paginaCopiloto]||sugestoesPadrao);


document.addEventListener('click',e=>{
    const alvo=e.target.closest('[data-copiloto-pergunta]');
    if(alvo){
        e.preventDefault();
        enviarMensagemCopiloto(alvo.dataset.copilotoPergunta||'Explique este ponto para mim.');
    }
});


copilotoFormWidget?.addEventListener('submit',e=>{
    e.preventDefault();
    const msg=copilotoInputWidget.value.trim();
    if(!msg)return;
    copilotoInputWidget.value='';
    input.style.height='';
    enviarMensagemCopiloto(msg);
});


copilotoInputWidget?.addEventListener('input',()=>{
    copilotoInputWidget.style.height='auto';
    copilotoInputWidget.style.height=Math.min(110,copilotoInputWidget.scrollHeight)+'px';
});



document.addEventListener('click',e=>{
    const b=e.target.closest('[data-save-plan]');
    if(!b)return;
    try{
        const plano=JSON.parse(decodeURIComponent(b.dataset.savePlan));
        const f=document.createElement('form');f.method='POST';f.action='../actions/planos_acao.php';
        const campos={csrf_token:csrfCopiloto,acao:'criar',titulo:plano.titulo||'Plano do Copiloto',descricao:plano.descricao||'',origem:'copiloto'};
        Object.entries(campos).forEach(([n,v])=>{const i=document.createElement('input');i.type='hidden';i.name=n;i.value=v;f.appendChild(i)});
        (plano.itens||[]).forEach(v=>{const i=document.createElement('input');i.type='hidden';i.name='itens[]';i.value=v;f.appendChild(i)});
        document.body.appendChild(f);f.submit();
    }catch(_){}
});


/* Interações centrais de drawers e confirmações ficam em
   assets/js/core-interactions.js para não depender do widget do Copiloto. */

/* Redimensionamento do widget do Copiloto no desktop. */
const copilotoResizeHandle = document.getElementById('copilotoResizeHandle');
const copilotoExpandir = document.getElementById('copilotoExpandir');

if (cp && window.innerWidth > 800) {
    try {
        const tamanhoSalvo = JSON.parse(localStorage.getItem('cashpilot_copiloto_widget_size') || 'null');
        if (tamanhoSalvo?.width) cp.style.width = Math.min(720, Math.max(360, tamanhoSalvo.width)) + 'px';
        if (tamanhoSalvo?.height) cp.style.height = Math.min(window.innerHeight - 100, Math.max(460, tamanhoSalvo.height)) + 'px';
    } catch (_) {}
}

copilotoResizeHandle?.addEventListener('pointerdown', (event) => {
    if (!cp || window.innerWidth <= 800) return;
    event.preventDefault();
    copilotoResizeHandle.setPointerCapture(event.pointerId);
    const inicioX = event.clientX;
    const inicioY = event.clientY;
    const larguraInicial = cp.getBoundingClientRect().width;
    const alturaInicial = cp.getBoundingClientRect().height;

    const mover = (e) => {
        const largura = Math.min(720, Math.max(360, larguraInicial + (inicioX - e.clientX)));
        const altura = Math.min(window.innerHeight - 96, Math.max(460, alturaInicial + (inicioY - e.clientY)));
        cp.style.width = largura + 'px';
        cp.style.height = altura + 'px';
    };

    const terminar = () => {
        copilotoResizeHandle.removeEventListener('pointermove', mover);
        copilotoResizeHandle.removeEventListener('pointerup', terminar);
        const rect = cp.getBoundingClientRect();
        localStorage.setItem('cashpilot_copiloto_widget_size', JSON.stringify({width: rect.width, height: rect.height}));
    };

    copilotoResizeHandle.addEventListener('pointermove', mover);
    copilotoResizeHandle.addEventListener('pointerup', terminar);
});

function atualizarLinkExpandirCopiloto() {
    if (!copilotoExpandir) return;
    copilotoExpandir.href = conversaWidgetId ? `copiloto.php?conversa=${conversaWidgetId}` : 'copiloto.php';
}

atualizarLinkExpandirCopiloto();

/* autocomplete desligado de forma consistente */
document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('form').forEach(f=>f.setAttribute('autocomplete','off'));
    document.querySelectorAll('input:not([type="hidden"]):not([type="file"]),textarea')
        .forEach(i=>i.setAttribute('autocomplete','off'));
});

})();
</script>
<script>
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link) return;
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (link.target === '_blank' || link.hasAttribute('download')) return;

    const href = link.getAttribute('href') || '';
    if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

    let destino;
    try { destino = new URL(link.href, window.location.href); } catch (_) { return; }
    if (destino.origin !== window.location.origin) return;
    if (destino.pathname === window.location.pathname && destino.search === window.location.search) return;

    event.preventDefault();
    document.body.classList.add('cp-page-leaving');
    window.setTimeout(() => { window.location.href = destino.href; }, 120);
});
</script>

<script src="../assets/js/interface.js?v=14.8.2"></script>
<script src="../assets/js/ui-controls.js?v=14.8.2"></script>
<script src="../assets/js/core-interactions.js?v=14.8.2"></script>
<script src="../assets/js/mobile-navigation.js?v=14.8.2"></script>
</body>
</html>
