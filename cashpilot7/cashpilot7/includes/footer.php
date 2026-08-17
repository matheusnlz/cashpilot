    </main>
</div>
<button type="button" class="copiloto-botao" id="copilotoBotao" aria-label="Abrir Copiloto"><span>✦</span><i>Copiloto</i></button>
<div class="copiloto" id="copiloto" hidden>
    <div class="copiloto-topo"><div class="copiloto-identidade"><span class="copiloto-avatar">✦</span><div><strong>Copiloto CashPilot</strong><small><i class="status-online"></i> IA conectada aos seus dados</small></div></div><button type="button" id="copilotoFechar" aria-label="Fechar">×</button></div>
    <div class="copiloto-mensagens" id="copilotoMensagens"><div class="copiloto-msg assistente"><?= usuarioLogadoTipo() === 'mei' ? 'Olá! Posso analisar caixa, custos, estrutura e alertas do seu negócio usando os dados registrados no CashPilot.' : 'Olá! Posso explicar seus números, metas e alertas usando os dados registrados no CashPilot.' ?></div></div>
    <div class="copiloto-sugestoes"><?php if (usuarioLogadoTipo() === 'mei'): ?><button type="button">Explique meu negócio</button><button type="button">Onde estão meus maiores custos?</button><button type="button">Como está meu caixa?</button><button type="button">Como posso crescer?</button><?php else: ?><button type="button">Explique meu mês</button><button type="button">Onde estou gastando mais?</button><button type="button">Quanto posso gastar?</button><button type="button">Como está minha meta?</button><?php endif; ?></div>
    <form id="copilotoForm" autocomplete="off"><input id="copilotoInput" autocomplete="off" maxlength="800" placeholder="Pergunte ao Copiloto..."><button type="submit">Enviar</button></form>
</div>
<script>
const cb=document.getElementById('copilotoBotao'),cp=document.getElementById('copiloto'),cf=document.getElementById('copilotoFechar'),cm=document.getElementById('copilotoMensagens'),form=document.getElementById('copilotoForm'),input=document.getElementById('copilotoInput');
const csrfCopiloto=<?= json_encode(csrfToken(), JSON_UNESCAPED_UNICODE) ?>;
function escaparChat(v){return String(v).replace(/[&<>]/g,s=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[s]));}
function abrirCopiloto(){if(!cp||!input)return;cp.hidden=false;input.focus();}
async function enviarMensagemCopiloto(msg){
    if(!msg||!cm)return;abrirCopiloto();
    cm.insertAdjacentHTML('beforeend','<div class="copiloto-msg usuario">'+escaparChat(msg)+'</div>');
    cm.insertAdjacentHTML('beforeend','<div class="copiloto-msg assistente copiloto-carregando"><span class="digitando"><i></i><i></i><i></i></span></div>');
    cm.scrollTop=cm.scrollHeight;
    const carregando=cm.querySelector('.copiloto-carregando:last-child');
    const fd=new FormData();fd.append('mensagem',msg);fd.append('csrf_token',csrfCopiloto);
    try{
        const r=await fetch('../actions/chatbot.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}});
        const d=await r.json();
        if(carregando)carregando.outerHTML='<div class="copiloto-msg assistente">'+escaparChat(d.resposta||'Não consegui responder agora.')+'</div>';
    }catch(e){if(carregando)carregando.outerHTML='<div class="copiloto-msg assistente">Não foi possível consultar o Copiloto agora.</div>';}
    cm.scrollTop=cm.scrollHeight;
}
window.abrirCopilotoComMensagem=function(msg){enviarMensagemCopiloto(msg);};
cb?.addEventListener('click',abrirCopiloto);cf?.addEventListener('click',()=>cp.hidden=true);
document.querySelectorAll('.copiloto-sugestoes button').forEach(b=>b.addEventListener('click',()=>enviarMensagemCopiloto(b.textContent)));
document.addEventListener('click',e=>{const alvo=e.target.closest('[data-copiloto-pergunta]');if(alvo){e.preventDefault();enviarMensagemCopiloto(alvo.dataset.copilotoPergunta||'Explique este ponto para mim.');}});
form?.addEventListener('submit',e=>{e.preventDefault();const msg=input.value.trim();if(!msg)return;input.value='';enviarMensagemCopiloto(msg);});
</script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 document.querySelectorAll('form').forEach(f=>f.setAttribute('autocomplete','off'));
 document.querySelectorAll('input:not([type="hidden"]):not([type="file"]), textarea').forEach(i=>i.setAttribute('autocomplete','off'));
});
</script>
</body>
</html>
