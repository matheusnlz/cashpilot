<?php
require_once __DIR__.'/../includes/auth.php';

require_once __DIR__.'/../database/conexao.php';


exigirLogin();


if (usuarioLogadoTipo() !== 'pessoa_fisica') {

    header('Location: dashboard.php');

    exit;
}


$tituloPagina = 'Financiamentos';

$pdo = conectar();

$uid = usuarioLogadoId();


$stmt = $pdo->prepare(
    'SELECT *
     FROM financiamentos_simulados
     WHERE usuario_id = :uid
     ORDER BY criado_em DESC
     LIMIT 20'
);

$stmt->execute(['uid' => $uid]);

$salvos = $stmt->fetchAll();


$msg = $_SESSION['mensagem_pf'] ?? null;

unset($_SESSION['mensagem_pf']);


require_once __DIR__.'/../includes/header.php';

require_once __DIR__.'/../includes/navbar.php';?>
<div class="page-head">
<div>
<span class="eyebrow">PLANEJAMENTO DE CRÉDITO</span>
<h1>Financiamentos</h1>
<p>Simule antes de assumir uma parcela e compare o custo total da decisão.</p>
</div>
</div>

<?php if ($msg):?>
    <div class="alerta-mensagem sucesso"><?=limpar($msg)?></div>
<?php endif;?>

<section class="finance-intro surface-card">
<div>
<span class="finance-intro-icon">▤</span>
<div>
<span class="eyebrow">ANTES DE DECIDIR</span>
<h2>Veja a parcela e também o custo completo</h2>
<p>A simulação é uma estimativa. Taxas reais, seguros e tarifas podem alterar a proposta final da instituição financeira.</p>
</div>
</div>
<button type="button" class="btn btn-primario" data-drawer-open="drawerFinanciamento">Nova simulação</button>
</section>
<section class="section-block">
<div class="section-title">
<div>
<span class="eyebrow">HISTÓRICO</span>
<h2>Simulações salvas</h2>
</div>
<span class="soft-badge"><?=count($salvos)?> salva(s)</span>
</div>

    <?php if (!$salvos):?>
        <div class="surface-card estado-vazio clean-empty">
<span>▤</span>
<h3>Nenhuma simulação salva</h3>
<p>Faça uma simulação para comparar parcela, juros e custo total.</p>
<button type="button" class="btn btn-secundario" data-drawer-open="drawerFinanciamento">Simular financiamento</button>
</div>
    <?php else:?>
        <div class="finance-saved-grid">
            <?php foreach ($salvos as $x):?>
                <article class="surface-card finance-saved-card">
<div class="finance-saved-head">
<div>
<span class="soft-badge"><?=$x['parcelas']?> parcelas</span>
<h3><?=limpar($x['nome'])?></h3>
</div>
<strong><?=formatarMoeda((float)$x['valor_bem'])?></strong>
</div>
<div class="finance-saved-metrics">
<div>
<small>Parcela estimada</small>
<strong><?=formatarMoeda((float)$x['valor_parcela'])?></strong>
</div>
<div>
<small>Entrada</small>
<strong><?=formatarMoeda((float)$x['entrada'])?></strong>
</div>
<div>
<small>Juros ao mês</small>
<strong><?=number_format((float)$x['taxa_mensal'],2,',','.')?>%</strong>
</div>
</div>
<div class="finance-saved-actions">
<button type="button" class="btn btn-copiloto-soft" data-copiloto-pergunta="Analise o impacto deste financiamento nas minhas finanças: <?=limpar($x['nome'])?>, parcela <?=formatarMoeda((float)$x['valor_parcela'])?> por <?=$x['parcelas']?> meses.">✦ Analisar com Copiloto</button>
<form action="../actions/pf.php" method="POST" data-confirm="Excluir esta simulação?" data-confirm-message="A simulação será removida do seu histórico.">
                            <?=csrfCampo()?>
                            <input type="hidden" name="acao" value="excluir_financiamento">
<input type="hidden" name="id" value="<?=$x['id']?>">
<button class="action-danger" type="submit">Excluir</button>
</form>
</div>
</article>
            <?php endforeach;?>
        </div>
    <?php endif;?>
</section>
<aside class="cp-drawer wide" id="drawerFinanciamento">
<div class="drawer-head">
<div>
<span class="eyebrow">NOVA SIMULAÇÃO</span>
<h2>Simular financiamento</h2>
<p>Preencha os dados da proposta que você quer avaliar.</p>
</div>
<button type="button" class="drawer-close" data-drawer-close aria-label="Fechar">×</button>
</div>
<div class="drawer-body">
<form id="formFin" autocomplete="off">
<div class="form-grupo">
<label>Nome da simulação</label>
<input id="finNome" value="Financiamento">
</div>
<div class="form-linha">
<div class="form-grupo">
<label>Valor do bem</label>
<input id="finBem" type="number" min="0" step="0.01" required>
</div>
<div class="form-grupo">
<label>Entrada</label>
<input id="finEntrada" type="number" min="0" step="0.01" value="0">
</div>
</div>
<div class="form-linha">
<div class="form-grupo">
<label>Juros ao mês (%)</label>
<input id="finTaxa" type="number" min="0" step="0.01" value="1">
</div>
<div class="form-grupo">
<label>Parcelas</label>
<input id="finParcelas" type="number" min="1" max="600" value="36">
</div>
</div>
<button class="btn btn-primario btn-bloco" type="submit">Calcular simulação</button>
</form>
<div id="finResultado" class="finance-result" hidden>
</div>
</div>
</aside>
<form id="salvarFin" action="../actions/pf.php" method="POST" hidden>
    <?=csrfCampo()?>
    <input type="hidden" name="acao" value="salvar_financiamento">
<input name="nome">
<input name="valor_bem">
<input name="entrada">
<input name="taxa_mensal">
<input name="parcelas">
</form>
<script>
const F = (id) => document.getElementById(id);

const moeda = (valor) => Number(valor).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});


F('formFin').addEventListener('submit', (event) => {
    event.preventDefault();

    const bem = +F('finBem').value || 0;
    const entrada = Math.min(bem, +F('finEntrada').value || 0);
    const taxa = (+F('finTaxa').value || 0) / 100;
    const parcelas = Math.max(1, +F('finParcelas').value || 1);
    const financiado = bem - entrada;
    const parcela = taxa > 0
        ? (financiado * taxa * Math.pow(1 + taxa, parcelas)) / (Math.pow(1 + taxa, parcelas) - 1)
        : financiado / parcelas;
    const total = entrada + parcela * parcelas;
    const juros = Math.max(0, total - bem);

    const resultado = F('finResultado');
    resultado.hidden = false;
    resultado.innerHTML = `
        <div class="finance-result-head">
<div>
<span class="eyebrow">RESULTADO ESTIMADO</span>
<h3>${parcelas}x de ${moeda(parcela)}</h3>
</div>
<small>Taxa ${(taxa * 100).toFixed(2).replace('.', ',')}% a.m.</small>
</div>
<div class="sim-grid">
<div>
<span>Valor financiado</span>
<strong>${moeda(financiado)}</strong>
</div>
<div>
<span>Parcela estimada</span>
<strong>${moeda(parcela)}</strong>
</div>
<div>
<span>Total pago</span>
<strong>${moeda(total)}</strong>
</div>
<div>
<span>Juros totais</span>
<strong>${moeda(juros)}</strong>
</div>
</div>
<div class="finance-cost-bar">
<span style="width:${total > 0 ? Math.min(100, bem / total * 100) : 0}%">
</span>
</div>
<div class="finance-cost-legend">
<span>Valor do bem</span>
<span>Juros: ${moeda(juros)}</span>
</div>
<div class="sim-acoes">
<button id="saveFin" class="btn btn-secundario">Salvar simulação</button>
<button class="btn btn-copiloto-meta" type="button" data-copiloto-pergunta="Estou simulando um financiamento de ${moeda(bem)}, com entrada ${moeda(entrada)}, ${parcelas} parcelas de aproximadamente ${moeda(parcela)} e juros de ${(taxa * 100).toFixed(2)}% ao mês. Analise o impacto nas minhas finanças.">✦ Analisar com Copiloto</button>
</div>`;

    F('saveFin').addEventListener('click', (e) => {
        e.preventDefault();
        const formSalvar = F('salvarFin');
        formSalvar.elements.nome.value = F('finNome').value;
        formSalvar.elements.valor_bem.value = bem;
        formSalvar.elements.entrada.value = entrada;
        formSalvar.elements.taxa_mensal.value = taxa * 100;
        formSalvar.elements.parcelas.value = parcelas;
        formSalvar.submit();
    });
});
</script>
<?php require_once __DIR__.'/../includes/footer.php';?>
