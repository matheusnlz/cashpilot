function formatarMesLabel(mesStr){const [ano,mes]=mesStr.split('-');const nomes=['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];return nomes[parseInt(mes,10)-1]+'/'+ano.slice(2);}
let graficoEvolucaoAtual=null;
function inicializarGraficoEvolucao(fonte,canvasId='graficoEvolucao'){
 const canvas=document.getElementById(canvasId);if(!canvas)return;
 const pacote=Array.isArray(fonte)?{mensal:fonte,diario:[]}:fonte||{};const mensal=Array.isArray(pacote.mensal)?pacote.mensal:[];const diario=Array.isArray(pacote.diario)?pacote.diario:[];
 const estilos=getComputedStyle(document.documentElement),destaque=estilos.getPropertyValue('--cor-destaque').trim()||'#2F5D62',erro=estilos.getPropertyValue('--cor-erro').trim()||'#B3453F',texto=estilos.getPropertyValue('--cor-texto-suave').trim()||'#6B6E76',borda=estilos.getPropertyValue('--cor-borda').trim()||'#E4E2DD';
 function render(periodo){
   let labels=[],receitas=[],despesas=[];
   if(periodo===1&&diario.length){labels=diario.map(d=>String(d.dia).padStart(2,'0'));receitas=diario.map(d=>Number(d.receitas||0));despesas=diario.map(d=>Number(d.despesas||0));}
   else{const fatias=mensal.slice(-periodo);labels=fatias.map(d=>formatarMesLabel(d.mes));receitas=fatias.map(d=>Number(d.receitas||0));despesas=fatias.map(d=>Number(d.despesas||0));}
   if(!labels.length){if(graficoEvolucaoAtual)graficoEvolucaoAtual.destroy();canvas.parentElement.innerHTML='<p class="texto-vazio">Ainda não há movimentações suficientes para exibir o gráfico.</p>';return;}
   if(graficoEvolucaoAtual)graficoEvolucaoAtual.destroy();
   graficoEvolucaoAtual=new Chart(canvas.getContext('2d'),{type:'line',data:{labels,datasets:[{label:'Receitas',data:receitas,borderColor:destaque,backgroundColor:destaque+'1A',tension:.28,fill:true,pointRadius:periodo===1?1.8:3,borderWidth:2},{label:'Despesas',data:despesas,borderColor:erro,backgroundColor:erro+'12',tension:.28,fill:true,pointRadius:periodo===1?1.8:3,borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom',labels:{color:texto,boxWidth:10,font:{size:12}}},tooltip:{callbacks:{title:it=>periodo===1?`Dia ${it[0].label}`:it[0].label,label:c=>`${c.dataset.label}: ${Number(c.raw||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'})}`}}},scales:{x:{grid:{display:false},ticks:{color:texto,font:{size:10},maxTicksLimit:periodo===1?10:12}},y:{grid:{color:borda},ticks:{color:texto,font:{size:10},callback:v=>Number(v).toLocaleString('pt-BR',{notation:'compact'})}}}}});
 }
 render(1);
 document.querySelectorAll('.periodo').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('.periodo').forEach(b=>b.classList.remove('ativo'));btn.classList.add('ativo');render(Number(btn.dataset.meses));}));
 document.querySelectorAll('.metrica-interativa').forEach(btn=>btn.addEventListener('click',()=>{const d=document.getElementById(btn.dataset.detalhe);if(d)d.hidden=!d.hidden;}));
}
