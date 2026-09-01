<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/cashpilot14_financeiro.php';


exigirLogin();


if (usuarioLogadoTipo() !== 'pessoa_fisica') {

    header('Location: dashboard.php');

    exit;
}


$tituloPagina = 'Investimentos';

$pdo = conectar();

$usuarioId = (int) usuarioLogadoId();


$migrationOk = cp14TabelaExiste(
    $pdo,
    'investimentos'
);


$resumo = $migrationOk
    ? cp14ResumoInvestimentos($pdo, $usuarioId)
    : [
        'quantidade' => 0,
        'valor_aplicado' => 0,
        'valor_atual' => 0,
        'resultado' => 0,
        'rentabilidade' => null,
        'classes' => [],
        'aportes_mes' => 0,
    ];


$investimentos = $migrationOk
    ? cp14ListarInvestimentos($pdo, $usuarioId)
    : [];


$selecionadoId = (int) (
    $_GET['investimento']
    ?? ($investimentos[0]['id'] ?? 0)
);


$selecionado = null;


foreach ($investimentos as $investimento) {

    if ((int) $investimento['id'] === $selecionadoId) {

        $selecionado = $investimento;

        break;
}
}


$movimentacoes = $selecionado
    ? cp14MovimentacoesInvestimento(
        $pdo,
        $usuarioId,
        (int) $selecionado['id']
    )
    : [];


$metas = [];


try {

    $stmt = $pdo->prepare(
        'SELECT id, titulo, valor_meta, valor_atual
         FROM metas
         WHERE usuario_id = :uid
           AND concluida = 0
         ORDER BY prazo IS NULL, prazo, titulo'
    );


    $stmt->execute([
        'uid' => $usuarioId,
    ]);


    $metas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
 catch (Throwable $e) {

    $metas = [];
}


$mensagem =
    $_SESSION['mensagem_investimentos']
    ?? null;


unset($_SESSION['mensagem_investimentos']);


require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';


function cp14RentabilidadeAtivo(array $investimento): ?float
{

    $aplicado =
        (float) ($investimento['valor_aplicado'] ?? 0);


    if ($aplicado <= 0) {

        return null;
}


    return (
        (float) $investimento['valor_atual']
        - $aplicado
    ) / $aplicado * 100;
}?>

<div class="page-head">
<div>
<span class="eyebrow">PATRIMÔNIO E APORTES</span>
<h1>Investimentos</h1>
<p>
            Acompanhe sua carteira e seus aportes sem transformar
            o CashPilot em uma corretora.
        </p>
</div>
<div class="cp14-head-actions">
<button
            type="button"
            class="btn btn-secundario"
            data-copiloto-pergunta="Analise minha carteira de investimentos. Explique distribuição, aportes e relação com minha reserva, sem indicar compra ou venda de ativos."
        >
            ✦ Analisar carteira
        </button>
<button
            type="button"
            class="btn btn-primario"
            data-drawer-open="drawerNovoInvestimento"
        >
            + Adicionar investimento
        </button>
</div>
</div>

<?php if ($mensagem):?>
    <div class="alerta-mensagem sucesso">
        <?= limpar($mensagem)?>
    </div>
<?php endif;?>

<?php if (!$migrationOk):?>
    <section class="surface-card cp14-migration-warning">
<span class="eyebrow">CONFIGURAÇÃO NECESSÁRIA</span>
<h2>Investimentos ainda não está habilitado no banco</h2>
<p>
            Execute
            <code>database/migrations/012_cashpilot14.sql</code>
            no phpMyAdmin e atualize a página.
        </p>
</section>
<?php else:?>

<section class="cp14-kpi-grid">
<article>
<span>Patrimônio investido</span>
<strong><?= formatarMoeda($resumo['valor_atual'])?></strong>
<small>
            <?= $resumo['quantidade']?>
            posição(ões) ativa(s)
        </small>
</article>
<article>
<span>Total aportado</span>
<strong><?= formatarMoeda($resumo['valor_aplicado'])?></strong>
<small>
            <?= formatarMoeda($resumo['aportes_mes'])?>
            aportados neste mês
        </small>
</article>
<article>
<span>Resultado acompanhado</span>
<strong class="<?= $resumo['resultado'] >= 0 ? 'positivo' : 'negativo'?>">
            <?= ($resumo['resultado'] >= 0 ? '+' : '')?>
            <?= formatarMoeda($resumo['resultado'])?>
        </strong>
<small>
            Diferença entre valor atual e capital aplicado
        </small>
</article>
<article>
<span>Rentabilidade simples</span>

        <?php if ($resumo['rentabilidade'] !== null):?>
            <strong class="<?= $resumo['rentabilidade'] >= 0 ? 'positivo' : 'negativo'?>">
                <?= $resumo['rentabilidade'] >= 0 ? '+' : ''?>
                <?= number_format(
                    $resumo['rentabilidade'],
                    2,
                    ',',
                    '.'
                )?>%
            </strong>
<small>
                Não considera impostos, taxas ou benchmark.
            </small>
        <?php else:?>
            <span class="cp-insufficient">
                Dados insuficientes
            </span>
<small class="cp-insufficient-note">
                Cadastre uma posição com valor aplicado.
            </small>
        <?php endif;?>
    </article>
</section>
<div class="cp14-investment-layout">
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">ALOCAÇÃO</span>
<h2>Distribuição da carteira</h2>
</div>
</div>

        <?php if (!$resumo['classes']):?>
            <div class="estado-vazio clean-empty">
<span><?= cpIcon('chart')?></span>
<h3>Sua carteira começa aqui</h3>
<p>
                    Cadastre um investimento para visualizar
                    como seu patrimônio está distribuído.
                </p>
</div>
        <?php else:?>
            <div class="cp14-allocation-bar">
                <?php foreach ($resumo['classes'] as $indice => $classe):?>
                    <i
                        class="cp14-class-<?= $indice % 7?>"
                        style="width: <?= min(100, $classe['percentual'])?>%"
                        title="<?= limpar(cp14NomeClasseInvestimento($classe['classe']))?>"
                    >
</i>
                <?php endforeach;?>
            </div>
<div class="cp14-allocation-list">
                <?php foreach ($resumo['classes'] as $indice => $classe):?>
                    <div>
<span>
<i class="cp14-dot cp14-class-<?= $indice % 7?>">
</i>
                            <?= limpar(cp14NomeClasseInvestimento($classe['classe']))?>
                        </span>
<strong>
                            <?= formatarMoeda((float) $classe['valor'])?>
                            <small>
                                <?= number_format(
                                    $classe['percentual'],
                                    1,
                                    ',',
                                    '.'
                                )?>%
                            </small>
</strong>
</div>
                <?php endforeach;?>
            </div>
        <?php endif;?>
    </section>
<section class="surface-card">
<div class="section-title">
<div>
<span class="eyebrow">PRINCÍPIO CASHPILOT</span>
<h2>Antes de buscar retorno</h2>
</div>
</div>
<div class="cp14-invest-readiness">
<span><?= cpIcon('shield')?></span>
<div>
<strong>Investimento vem depois de organização.</strong>
<p>
                    O CashPilot relaciona carteira, reserva,
                    planejamento, despesas e metas. Ter investimentos
                    não significa automaticamente estar financeiramente
                    protegido.
                </p>
</div>
</div>
<a
            href="saude_financeira.php"
            class="btn btn-secundario"
        >
            Ver minha saúde financeira
        </a>
</section>
</div>
<div class="cp14-investment-app">
<aside class="surface-card cp14-investment-list">
<div class="section-title">
<div>
<span class="eyebrow">CARTEIRA</span>
<h2>Minhas posições</h2>
</div>
<span class="cp14-count">
                <?= count($investimentos)?>
            </span>
</div>

        <?php if (!$investimentos):?>
            <p class="texto-vazio">
                Nenhum investimento ativo.
            </p>
        <?php else:?>
            <?php foreach ($investimentos as $investimento):?>
                <?php
                $rentabilidade =
                    cp14RentabilidadeAtivo($investimento);?>

                <a
                    class="cp14-investment-item <?= $selecionado && (int) $selecionado['id'] === (int) $investimento['id'] ? 'ativo' : ''?>"
                    href="?investimento=<?= (int) $investimento['id']?>"
                >
<div class="cp14-investment-symbol">
                        <?= cpIcon('chart')?>
                    </div>
<div>
<strong>
                            <?= limpar($investimento['nome'])?>
                        </strong>
<small>
                            <?= limpar(cp14NomeClasseInvestimento($investimento['classe']))?>
                            <?php if ($investimento['instituicao']):?>
                                · <?= limpar($investimento['instituicao'])?>
                            <?php endif;?>
                        </small>
</div>
<div class="cp14-investment-item-value">
<strong>
                            <?= formatarMoeda((float) $investimento['valor_atual'])?>
                        </strong>

                        <?php if ($rentabilidade !== null):?>
                            <small class="<?= $rentabilidade >= 0 ? 'positivo' : 'negativo'?>">
                                <?= $rentabilidade >= 0 ? '+' : ''?>
                                <?= number_format($rentabilidade, 1, ',', '.')?>%
                            </small>
                        <?php endif;?>
                    </div>
</a>
            <?php endforeach;?>
        <?php endif;?>
    </aside>
<section class="surface-card cp14-investment-detail">
        <?php if (!$selecionado):?>
            <div class="estado-vazio clean-empty">
<span><?= cpIcon('chart')?></span>
<h3>Selecione um investimento</h3>
<p>
                    Abra uma posição para acompanhar valores,
                    aportes, meta relacionada e histórico.
                </p>
</div>
        <?php else:?>
            <?php
            $resultadoAtivo =
                (float) $selecionado['valor_atual']
                - (float) $selecionado['valor_aplicado'];


            $rentabilidadeAtivo =
                cp14RentabilidadeAtivo($selecionado);?>

            <div class="cp14-investment-detail-head">
<div>
<span class="eyebrow">
                        <?= limpar(cp14NomeClasseInvestimento($selecionado['classe']))?>
                    </span>
<h2><?= limpar($selecionado['nome'])?></h2>
<p>
                        <?= limpar($selecionado['instituicao'] ?: 'Instituição não informada')?>
                    </p>
</div>
<button
                    class="btn btn-secundario"
                    type="button"
                    data-copiloto-pergunta="Analise meu investimento <?= limpar($selecionado['nome'])?>. Tenho <?= formatarMoeda((float) $selecionado['valor_atual'])?> em valor atual e <?= formatarMoeda((float) $selecionado['valor_aplicado'])?> de capital aplicado. Relacione com meu planejamento e minha reserva, sem recomendar compra ou venda."
                >
                    ✦ Conversar
                </button>
</div>
<div class="cp14-detail-kpis">
<div>
<span>Aplicado</span>
<strong>
                        <?= formatarMoeda((float) $selecionado['valor_aplicado'])?>
                    </strong>
</div>
<div>
<span>Valor atual</span>
<strong>
                        <?= formatarMoeda((float) $selecionado['valor_atual'])?>
                    </strong>
</div>
<div>
<span>Resultado</span>
<strong class="<?= $resultadoAtivo >= 0 ? 'positivo' : 'negativo'?>">
                        <?= $resultadoAtivo >= 0 ? '+' : ''?>
                        <?= formatarMoeda($resultadoAtivo)?>
                    </strong>
</div>
<div>
<span>Rentabilidade</span>
<strong class="<?= ($rentabilidadeAtivo ?? 0) >= 0 ? 'positivo' : 'negativo'?>">
                        <?= $rentabilidadeAtivo !== null
                            ? (($rentabilidadeAtivo >= 0 ? '+' : '')
                                . number_format($rentabilidadeAtivo, 2, ',', '.')
                                . '%')
                            : '—'?>
                    </strong>
</div>
</div>
<div class="cp14-investment-actions">
<form
                    action="../actions/investimentos.php"
                    method="POST"
                    class="cp14-compact-form"
                >
                    <?= csrfCampo()?>

                    <input
                        type="hidden"
                        name="acao"
                        value="aporte"
                    >
<input
                        type="hidden"
                        name="investimento_id"
                        value="<?= (int) $selecionado['id']?>"
                    >
<label>
                        Novo aporte
                        <input
                            type="number"
                            step="0.01"
                            min="0.01"
                            name="valor"
                            placeholder="R$ 0,00"
                            required
                        >
</label>
<input
                        type="date"
                        name="data_movimentacao"
                        value="<?= date('Y-m-d')?>"
                    >
<button class="btn btn-primario">
                        Registrar aporte
                    </button>
</form>
<form
                    action="../actions/investimentos.php"
                    method="POST"
                    class="cp14-compact-form"
                >
                    <?= csrfCampo()?>

                    <input
                        type="hidden"
                        name="acao"
                        value="atualizar_valor"
                    >
<input
                        type="hidden"
                        name="investimento_id"
                        value="<?= (int) $selecionado['id']?>"
                    >
<label>
                        Atualizar valor atual
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="valor_atual"
                            value="<?= limpar((string) $selecionado['valor_atual'])?>"
                            required
                        >
</label>
<button class="btn btn-secundario">
                        Atualizar posição
                    </button>
</form>
</div>
<div class="cp14-investment-meta">
<div>
<span class="eyebrow">META RELACIONADA</span>
<h3>
                        <?= $selecionado['meta_titulo']
                            ? limpar($selecionado['meta_titulo'])
                            : 'Nenhuma meta vinculada'?>
                    </h3>
<p>
                        Relacionar uma posição a uma meta ajuda
                        o CashPilot a interpretar o objetivo daquele dinheiro.
                    </p>
</div>
<form
                    action="../actions/investimentos.php"
                    method="POST"
                >
                    <?= csrfCampo()?>

                    <input
                        type="hidden"
                        name="acao"
                        value="vincular_meta"
                    >
<input
                        type="hidden"
                        name="investimento_id"
                        value="<?= (int) $selecionado['id']?>"
                    >
<select name="meta_id">
<option value="0">
                            Sem meta
                        </option>

                        <?php foreach ($metas as $meta):?>
                            <option
                                value="<?= (int) $meta['id']?>"
                                <?= (int) ($selecionado['meta_id'] ?? 0) === (int) $meta['id'] ? 'selected' : ''?>
                            >
                                <?= limpar($meta['titulo'])?>
                            </option>
                        <?php endforeach;?>
                    </select>
<button class="btn btn-secundario">
                        Salvar relação
                    </button>
</form>
</div>
<div class="cp14-history">
<div class="section-title">
<div>
<span class="eyebrow">HISTÓRICO</span>
<h3>Aportes e movimentações</h3>
</div>
</div>

                <?php if (!$movimentacoes):?>
                    <p class="texto-vazio">
                        Nenhuma movimentação registrada.
                    </p>
                <?php else:?>
                    <?php foreach ($movimentacoes as $movimentacao):?>
                        <div class="cp14-history-row">
<span class="cp14-history-type <?= limpar($movimentacao['tipo'])?>">
                                <?= limpar(ucfirst($movimentacao['tipo']))?>
                            </span>
<div>
<strong>
                                    <?= date(
                                        'd/m/Y',
                                        strtotime($movimentacao['data_movimentacao'])
                                    )?>
                                </strong>
<small>
                                    <?= limpar(
                                        $movimentacao['observacao']
                                        ?: 'Movimentação da carteira'
                                    )?>
                                </small>
</div>
<strong>
                                <?= formatarMoeda((float) $movimentacao['valor'])?>
                            </strong>
</div>
                    <?php endforeach;?>
                <?php endif;?>
            </div>
<form
                action="../actions/investimentos.php"
                method="POST"
                data-confirm="Remover este investimento da carteira ativa?"
                class="cp14-danger-zone"
            >
                <?= csrfCampo()?>

                <input
                    type="hidden"
                    name="acao"
                    value="desativar"
                >
<input
                    type="hidden"
                    name="investimento_id"
                    value="<?= (int) $selecionado['id']?>"
                >
<button class="excluir">
                    Remover da carteira ativa
                </button>
</form>
        <?php endif;?>
    </section>
</div>
<aside
    class="cp-drawer"
    id="drawerNovoInvestimento"
>
<div class="drawer-head">
<div>
<span class="eyebrow">NOVA POSIÇÃO</span>
<h2>Adicionar investimento</h2>
<p>
                Registre manualmente uma posição que você já possui.
            </p>
</div>
<button
            type="button"
            class="drawer-close"
            data-drawer-close
            aria-label="Fechar"
        >
            ×
        </button>
</div>
<div class="drawer-body">
<form
            action="../actions/investimentos.php"
            method="POST"
            autocomplete="off"
        >
            <?= csrfCampo()?>

            <input
                type="hidden"
                name="acao"
                value="criar"
            >
<div class="form-grupo">
<label>Nome</label>
<input
                    name="nome"
                    required
                    placeholder="Ex.: CDB Reserva"
                >
</div>
<div class="form-grupo">
<label>Tipo</label>
<select name="classe" required>
<option value="renda_fixa">Renda fixa / CDB / LCI / LCA</option>
<option value="tesouro">Tesouro Direto</option>
<option value="acoes">Ações</option>
<option value="fiis">FIIs</option>
<option value="etfs">ETFs</option>
<option value="fundos">Fundos</option>
<option value="cripto">Criptomoedas</option>
<option value="poupanca">Poupança</option>
<option value="outros">Outros</option>
</select>
</div>
<div class="form-grupo">
<label>Descrição do ativo</label>
<input
                    name="subtipo"
                    placeholder="Ex.: Tesouro Selic 2029"
                >
</div>
<div class="form-grupo">
<label>Instituição</label>
<input
                    name="instituicao"
                    placeholder="Ex.: Nubank"
                >
</div>
<div class="grade-dois-campos">
<div class="form-grupo">
<label>Valor aplicado</label>
<input
                        type="number"
                        step="0.01"
                        min="0"
                        name="valor_aplicado"
                        value="0"
                        required
                    >
</div>
<div class="form-grupo">
<label>Valor atual</label>
<input
                        type="number"
                        step="0.01"
                        min="0"
                        name="valor_atual"
                        value="0"
                        required
                    >
</div>
</div>
<div class="grade-dois-campos">
<div class="form-grupo">
<label>Quantidade (opcional)</label>
<input
                        type="number"
                        step="0.00000001"
                        min="0"
                        name="quantidade"
                    >
</div>
<div class="form-grupo">
<label>Preço médio (opcional)</label>
<input
                        type="number"
                        step="0.0001"
                        min="0"
                        name="preco_medio"
                    >
</div>
</div>
<div class="form-grupo">
<label>Data da aplicação</label>
<input
                    type="date"
                    name="data_inicio"
                    value="<?= date('Y-m-d')?>"
                >
</div>
<div class="form-grupo">
<label>Meta relacionada</label>
<select name="meta_id">
<option value="0">
                        Sem meta relacionada
                    </option>

                    <?php foreach ($metas as $meta):?>
                        <option value="<?= (int) $meta['id']?>">
                            <?= limpar($meta['titulo'])?>
                        </option>
                    <?php endforeach;?>
                </select>
</div>
<div class="form-grupo">
<label>Observação</label>
<textarea
                    name="observacao"
                    placeholder="Informação opcional sobre esta posição"
                >
</textarea>
</div>
<button class="btn btn-primario btn-bloco">
                Adicionar à carteira
            </button>
</form>
</div>
</aside>

<?php endif;?>

<?php require_once __DIR__ . '/../includes/footer.php';?>
