<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/cashpilot14_financeiro.php';

exigirLogin();

if (usuarioLogadoTipo() !== 'pessoa_fisica') {
    header('Location: dashboard.php');

    exit();
}

$tituloPagina = 'Investimentos';

$pdo = conectar();

$usuarioId = (int) usuarioLogadoId();

$perfilInvestidor = null;
try {
    $stmtPerfilInvestidor = $pdo->prepare(
        'SELECT * FROM perfil_investidor WHERE usuario_id = :uid LIMIT 1'
    );
    $stmtPerfilInvestidor->execute(['uid' => $usuarioId]);
    $perfilInvestidor = $stmtPerfilInvestidor->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
    $perfilInvestidor = null;
}

$migrationOk = cp14TabelaExiste($pdo, 'investimentos');

$resumo = $migrationOk
    ? cp14ResumoInvestimentos($pdo, $usuarioId)
    : [
        'quantidade' => 0,
        'valor_aplicado' => 0,
        'valor_atual' => 0,
        'resultado' => 0,
        'rentabilidade' => null,
        'classes' => [],
        'aportes_mes' => 0
    ];

$investimentos = $migrationOk ? cp14ListarInvestimentos($pdo, $usuarioId) : [];

$selecionadoId = (int) ($_GET['investimento'] ?? ($investimentos[0]['id'] ?? 0));

$selecionado = null;

foreach ($investimentos as $investimento) {
    if ((int) $investimento['id'] === $selecionadoId) {
        $selecionado = $investimento;

        break;
    }
}

$movimentacoes = $selecionado
    ? cp14MovimentacoesInvestimento($pdo, $usuarioId, (int) $selecionado['id'])
    : [];

$metas = [];

try {
    $stmt = $pdo->prepare(
        'SELECT
                id,
                titulo,
                valor_meta,
                valor_atual
            FROM
                metas
            WHERE
                usuario_id = :uid
                AND concluida = 0
            ORDER BY
                prazo IS NULL,
                prazo,
                titulo'
    );

    $stmt->execute([
        'uid' => $usuarioId
    ]);

    $metas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $metas = [];
}

$mensagem = $_SESSION['mensagem_investimentos'] ?? null;

unset($_SESSION['mensagem_investimentos']);

require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';

function cp14RentabilidadeAtivo(array $investimento): ?float
{
    $aplicado = (float) ($investimento['valor_aplicado'] ?? 0);

    if ($aplicado <= 0) {
        return null;
    }

    return (((float) $investimento['valor_atual'] - $aplicado) / $aplicado) * 100;
}
?>


<div class="page-head">
    <div>
        <span class="eyebrow">PATRIMÔNIO E APORTES</span>
        <h1>Investimentos</h1>
        <p>
            Acompanhe sua carteira e seus aportes sem transformar
            o CashPilot em uma corretora.
        </p>
    </div>

    <?php if ($perfilInvestidor): ?>


    <div class="cp14-head-actions">
        <button type="button" class="btn btn-secundario"
            data-copiloto-pergunta="Analise minha carteira de investimentos. Explique distribuição, aportes e relação com minha reserva, sem indicar compra ou venda de ativos.">
            <?= cpIcon('copilot', 'cp-icon-inline') ?> Analisar carteira
        </button>
        <button type="button" class="btn btn-primario" data-drawer-open="drawerNovoInvestimento">
            <?= cpIcon('plus', 'cp-icon-inline') ?> Adicionar investimento
        </button>
    </div>

    <?php endif; ?>


</div>

<?php if ($mensagem): ?>


<div class="alerta-mensagem sucesso">
    <?= limpar($mensagem) ?>
     </div>

<?php endif; ?>


<?php if ($perfilInvestidor): ?>


<?php if (!$migrationOk): ?>


<section class="surface-card cp14-migration-warning">
    <span class="eyebrow">CONFIGURAÇÃO NECESSÁRIA</span>
    <h2>Investimentos ainda não estão disponíveis</h2>
    <p>
        Este recurso precisa ser habilitado pelo responsável pela instalação do CashPilot.
        Seus demais dados financeiros continuam disponíveis normalmente.
    </p>
</section>

<?php else: ?>


<section class="cp14-kpi-grid">
    <article>
        <span>Patrimônio investido</span>
        <strong><?= formatarMoeda($resumo['valor_atual']) ?></strong>
        <small>
            <?= $resumo['quantidade'] ?>
             posição(ões) ativa(s)
        </small>
    </article>
    <article>
        <span>Total aportado</span>
        <strong><?= formatarMoeda($resumo['valor_aplicado']) ?></strong>
        <small>
            <?= formatarMoeda($resumo['aportes_mes']) ?>
             aportados neste mês
        </small>
    </article>
    <article>
        <span>Resultado acompanhado</span>
        <strong class="<?= $resumo['resultado'] >= 0 ? 'positivo' : 'negativo' ?>">
            <?= $resumo['resultado'] >= 0 ? '+' : '' ?>
             <?= formatarMoeda($resumo['resultado']) ?>
             </strong>
        <small>
            Diferença entre valor atual e capital aplicado
        </small>
    </article>
    <article>
        <span>Rentabilidade simples</span>

        <?php if ($resumo['rentabilidade'] !== null): ?>


        <strong class="<?= $resumo['rentabilidade'] >= 0 ? 'positivo' : 'negativo' ?>">
            <?= $resumo['rentabilidade'] >= 0 ? '+' : '' ?>
             <?= number_format($resumo['rentabilidade'], 2, ',', '.') ?>%
        </strong>
        <small>
            Não considera impostos, taxas ou benchmark.
        </small>

        <?php else: ?>


        <span class="cp-insufficient">
            Dados insuficientes
        </span>
        <small class="cp-insufficient-note">
            Cadastre uma posição com valor aplicado.
        </small>

        <?php endif; ?>


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

        <?php if (!$resumo['classes']): ?>


        <div class="estado-vazio clean-empty">
            <span><?= cpIcon('chart') ?></span>
            <h3>Sua carteira começa aqui</h3>
            <p>
                Cadastre um investimento para visualizar
                como seu patrimônio está distribuído.
            </p>
        </div>

        <?php else: ?>


        <div class="cp14-allocation-bar">

            <?php foreach ($resumo['classes'] as $indice => $classe): ?>


            <i class="cp14-class-<?= $indice % 7 ?>" style="width: <?= min(
                100,
                $classe['percentual']
            ) ?>%" title="<?= limpar(cp14NomeClasseInvestimento($classe['classe'])) ?>">
            </i>

            <?php endforeach; ?>


        </div>
        <div class="cp14-allocation-list">

            <?php foreach ($resumo['classes'] as $indice => $classe): ?>


            <div>
                <span>
                    <i class="cp14-dot cp14-class-<?= $indice % 7 ?>">
                    </i>
                    <?= limpar(cp14NomeClasseInvestimento($classe['classe'])) ?>
                     </span>
                <strong>
                    <?= formatarMoeda((float) $classe['valor']) ?>
                     <small>
                        <?= number_format($classe['percentual'], 1, ',', '.') ?>%
                    </small>
                </strong>
            </div>

            <?php endforeach; ?>


        </div>

        <?php endif; ?>


    </section>
    <section class="surface-card">
        <div class="section-title">
            <div>
                <span class="eyebrow">PRINCÍPIO CASHPILOT</span>
                <h2>Antes de buscar retorno</h2>
            </div>
        </div>
        <div class="cp14-invest-readiness">
            <span><?= cpIcon('shield') ?></span>
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
        <a href="saude_financeira.php" class="btn btn-secundario">
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
                <?= count($investimentos) ?>
                 </span>
        </div>

        <?php if (!$investimentos): ?>


        <p class="texto-vazio">
            Nenhum investimento ativo.
        </p>

        <?php else: ?>


        <?php foreach ($investimentos as $investimento): ?>


        <?php $rentabilidade = cp14RentabilidadeAtivo($investimento); ?>


        <a class="cp14-investment-item <?= $selecionado &&
        (int) $selecionado['id'] === (int) $investimento['id']
            ? 'ativo'
            : '' ?>" href="?investimento=<?= (int) $investimento['id'] ?>">
            <div class="cp14-investment-symbol">
                <?= cpIcon('chart') ?>
                 </div>
            <div>
                <strong>
                    <?= limpar($investimento['nome']) ?>
                     </strong>
                <small>
                    <?= limpar(cp14NomeClasseInvestimento($investimento['classe'])) ?>


                    <?php if ($investimento['instituicao']): ?>


                    · <?= limpar($investimento['instituicao']) ?>


                    <?php endif; ?>


                </small>
            </div>
            <div class="cp14-investment-item-value">
                <strong>
                    <?= formatarMoeda((float) $investimento['valor_atual']) ?>
                     </strong>

                <?php if ($rentabilidade !== null): ?>


                <small class="<?= $rentabilidade >= 0 ? 'positivo' : 'negativo' ?>">
                    <?= $rentabilidade >= 0 ? '+' : '' ?>
                     <?= number_format($rentabilidade, 1, ',', '.') ?>%
                </small>

                <?php endif; ?>


            </div>
        </a>

        <?php endforeach; ?>


        <?php endif; ?>


    </aside>
    <section class="surface-card cp14-investment-detail">

        <?php if (!$selecionado): ?>


        <div class="estado-vazio clean-empty">
            <span><?= cpIcon('chart') ?></span>
            <h3>Selecione um investimento</h3>
            <p>
                Abra uma posição para acompanhar valores,
                aportes, meta relacionada e histórico.
            </p>
        </div>

        <?php else: ?>


        <?php
        $resultadoAtivo =
            (float) $selecionado['valor_atual'] - (float) $selecionado['valor_aplicado'];
        
        $rentabilidadeAtivo = cp14RentabilidadeAtivo($selecionado);
        ?>


        <div class="cp14-investment-detail-head">
            <div>
                <span class="eyebrow">
                    <?= limpar(cp14NomeClasseInvestimento($selecionado['classe'])) ?>
                     </span>
                <h2><?= limpar($selecionado['nome']) ?></h2>
                <p>
                    <?= limpar($selecionado['instituicao'] ?: 'Instituição não informada') ?>
                     </p>
            </div>
            <button class="btn btn-secundario" type="button"
                data-copiloto-pergunta="Analise meu investimento <?= limpar(
                    $selecionado['nome']
                ) ?>. Tenho <?= formatarMoeda(
                    (float) $selecionado['valor_atual']
                ) ?> em valor atual e <?= formatarMoeda(
                    (float) $selecionado['valor_aplicado']
                ) ?> de capital aplicado. Relacione com meu planejamento e minha reserva, sem recomendar compra ou venda.">
                <?= cpIcon('copilot', 'cp-icon-inline') ?> Conversar
            </button>
        </div>
        <div class="cp14-detail-kpis">
            <div>
                <span>Aplicado</span>
                <strong>
                    <?= formatarMoeda((float) $selecionado['valor_aplicado']) ?>
                     </strong>
            </div>
            <div>
                <span>Valor atual</span>
                <strong>
                    <?= formatarMoeda((float) $selecionado['valor_atual']) ?>
                     </strong>
            </div>
            <div>
                <span>Resultado</span>
                <strong class="<?= $resultadoAtivo >= 0 ? 'positivo' : 'negativo' ?>">
                    <?= $resultadoAtivo >= 0 ? '+' : '' ?>
                     <?= formatarMoeda($resultadoAtivo) ?>
                     </strong>
            </div>
            <div>
                <span>Rentabilidade</span>
                <strong class="<?= ($rentabilidadeAtivo ?? 0) >= 0 ? 'positivo' : 'negativo' ?>">
                    <?= $rentabilidadeAtivo !== null
                    ? ($rentabilidadeAtivo >= 0 ? '+' : '') .
                        number_format($rentabilidadeAtivo, 2, ',', '.') .
                        '%'
                    : '—' ?>
                     </strong>
            </div>
        </div>
        <div class="cp14-investment-actions">
            <form action="../actions/investimentos.php" method="POST" class="cp14-compact-form">
                <?= csrfCampo() ?>

                <input type="hidden" name="acao" value="aporte">
                <input type="hidden" name="investimento_id" value="<?= (int) $selecionado['id'] ?>">
                <label>
                    Novo aporte
                    <input type="number" step="0.01" min="0.01" name="valor" placeholder="R$ 0,00"
                        required>
                </label>
                <input type="date" name="data_movimentacao" value="<?= date('Y-m-d') ?>">
                <button class="btn btn-primario">
                    Registrar aporte
                </button>
            </form>
            <form action="../actions/investimentos.php" method="POST" class="cp14-compact-form">
                <?= csrfCampo() ?>

                <input type="hidden" name="acao" value="atualizar_valor">
                <input type="hidden" name="investimento_id" value="<?= (int) $selecionado['id'] ?>">
                <label>
                    Atualizar valor atual
                    <input type="number" step="0.01" min="0" name="valor_atual"
                        value="<?= limpar((string) $selecionado['valor_atual']) ?>" required>
                </label>
                <label>
                    Composição do patrimônio
                    <select name="incluido_no_saldo">
                        <option value="1" <?= !empty($selecionado['incluido_no_saldo'])
                        ? 'selected'
                        : '' ?>>Já incluído no saldo das contas</option>
                        <option value="0" <?= empty($selecionado['incluido_no_saldo'])
                        ? 'selected'
                        : '' ?>>Fora do saldo das contas</option>
                    </select>
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
                    : 'Nenhuma meta vinculada' ?>
                     </h3>
                <p>
                    Relacionar uma posição a uma meta ajuda
                    o CashPilot a interpretar o objetivo daquele dinheiro.
                </p>
            </div>
            <form action="../actions/investimentos.php" method="POST">
                <?= csrfCampo() ?>

                <input type="hidden" name="acao" value="vincular_meta">
                <input type="hidden" name="investimento_id" value="<?= (int) $selecionado['id'] ?>">
                <select name="meta_id">
                    <option value="0">
                        Sem meta
                    </option>

                    <?php foreach ($metas as $meta): ?>


                    <option value="<?= (int) $meta['id'] ?>" <?= (int) ($selecionado['meta_id'] ??
                    0) === (int) $meta['id']
                    ? 'selected'
                    : '' ?>
                    >
                        <?= limpar($meta['titulo']) ?>
                         </option>

                    <?php endforeach; ?>


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

            <?php if (!$movimentacoes): ?>


            <p class="texto-vazio">
                Nenhuma movimentação registrada.
            </p>

            <?php else: ?>


            <?php foreach ($movimentacoes as $movimentacao): ?>


            <div class="cp14-history-row">
                <span class="cp14-history-type <?= limpar($movimentacao['tipo']) ?>">
                    <?= limpar(ucfirst($movimentacao['tipo'])) ?>
                     </span>
                <div>
                    <strong>
                        <?= date('d/m/Y', strtotime($movimentacao['data_movimentacao'])) ?>
                         </strong>
                    <small>
                        <?= limpar($movimentacao['observacao'] ?: 'Movimentação da carteira') ?>
                         </small>
                </div>
                <strong>
                    <?= formatarMoeda((float) $movimentacao['valor']) ?>
                     </strong>
            </div>

            <?php endforeach; ?>


            <?php endif; ?>


        </div>
        <form action="../actions/investimentos.php" method="POST"
            data-confirm="Remover este investimento da carteira ativa?" class="cp14-danger-zone">
            <?= csrfCampo() ?>

            <input type="hidden" name="acao" value="desativar">
            <input type="hidden" name="investimento_id" value="<?= (int) $selecionado['id'] ?>">
            <button class="excluir">
                Remover da carteira ativa
            </button>
        </form>

        <?php endif; ?>


    </section>
</div>
<aside class="cp-drawer" id="drawerNovoInvestimento">
    <div class="drawer-head">
        <div>
            <span class="eyebrow">NOVA POSIÇÃO</span>
            <h2>Adicionar investimento</h2>
            <p>
                Registre manualmente uma posição que você já possui.
            </p>
        </div>
        <button type="button" class="drawer-close" data-drawer-close aria-label="Fechar">
            <?= cpIcon('close') ?>
        </button>
    </div>
    <div class="drawer-body">
        <form action="../actions/investimentos.php" method="POST" autocomplete="off">
            <?= csrfCampo() ?>

            <input type="hidden" name="acao" value="criar">
            <div class="form-grupo">
                <label>Nome</label>
                <input name="nome" required placeholder="Ex.: CDB Reserva">
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
                <input name="subtipo" placeholder="Ex.: Tesouro Selic 2029">
            </div>
            <div class="form-grupo">
                <label>Instituição</label>
                <input name="instituicao" placeholder="Ex.: Nubank">
            </div>
            <div class="grade-dois-campos">
                <div class="form-grupo">
                    <label>Valor aplicado</label>
                    <input type="number" step="0.01" min="0" name="valor_aplicado" value="0"
                        required>
                </div>
                <div class="form-grupo">
                    <label>Valor atual</label>
                    <input type="number" step="0.01" min="0" name="valor_atual" value="0" required>
                </div>
            </div>
            <div class="grade-dois-campos">
                <div class="form-grupo">
                    <label>Este investimento já está no saldo das contas?</label>
                    <select name="incluido_no_saldo">
                        <option value="1">Sim — não somar novamente ao patrimônio</option>
                        <option value="0">Não — somar ao patrimônio</option>
                    </select>
                    <small>Aportes e retiradas atualizam a carteira, sem movimentar automaticamente
                        suas contas. Confira o saldo para não contar o mesmo dinheiro duas
                        vezes.</small>
                </div>
                <div class="form-grupo">
                    <label>Quantidade (opcional)</label>
                    <input type="number" step="0.00000001" min="0" name="quantidade">
                </div>
                <div class="form-grupo">
                    <label>Preço médio (opcional)</label>
                    <input type="number" step="0.0001" min="0" name="preco_medio">
                </div>
            </div>
            <div class="form-grupo">
                <label>Data da aplicação</label>
                <input type="date" name="data_inicio" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-grupo">
                <label>Meta relacionada</label>
                <select name="meta_id">
                    <option value="0">
                        Sem meta relacionada
                    </option>

                    <?php foreach ($metas as $meta): ?>


                    <option value="<?= (int) $meta['id'] ?>">
                        <?= limpar($meta['titulo']) ?>
                         </option>

                    <?php endforeach; ?>


                </select>
            </div>
            <div class="form-grupo">
                <label>Observação</label>
                <textarea name="observacao" placeholder="Informação opcional sobre esta posição">
</textarea>
            </div>
            <button class="btn btn-primario btn-bloco">
                Adicionar à carteira
            </button>
        </form>
    </div>
</aside>

<?php endif; ?>


<?php endif; ?>


<?php if (!$perfilInvestidor): ?>


<section class="surface-card cp154-invest-onboarding" id="comecar">
    <div class="cp154-onboarding-copy">
        <span class="eyebrow">ANTES DA CARTEIRA</span>
        <h2>Vamos entender como você quer investir</h2>
        <p>O CashPilot usa esta etapa apenas para organizar a experiência e explicar conceitos. Ele
            não recomenda um ativo específico nem executa investimentos.</p>
    </div>
    <div class="cp154-invest-choice" id="investChoice">
        <h3>Você já conhece seu perfil de investidor?</h3>
        <div class="cp154-choice-actions">
            <button type="button" class="btn btn-primario" data-invest-known="1">Sim, eu já sei
            </button>
            <button type="button" class="btn btn-secundario" data-invest-known="0">Não sei meu
                perfil</button>
        </div>
    </div>
    <form method="POST" action="../actions/perfil_investidor.php"
        class="cp15-invest-profile-form cp154-known-profile" id="knownProfileForm" hidden>
        <?= csrfCampo() ?>

        <input type="hidden" name="modo" value="direto">
        <h3>Qual é seu perfil?</h3>
        <label>Perfil de investidor<select name="perfil_direto" required>
                <option value="conservador">Conservador</option>
                <option value="moderado">Moderado</option>
                <option value="arrojado">Arrojado</option>
            </select></label>
        <label>Objetivo principal<select name="objetivo" required>
                <option value="patrimonio">Construir patrimônio</option>
                <option value="reserva">Reserva de emergência</option>
                <option value="casa">Casa</option>
                <option value="viagem">Viagem</option>
                <option value="aposentadoria">Aposentadoria</option>
                <option value="outro">Outro</option>
            </select></label>
        <button class="btn btn-primario" type="submit">Continuar para minha carteira</button>
    </form>
    <form method="POST" action="../actions/perfil_investidor.php"
        class="cp15-invest-profile-form cp154-discovery-form" id="discoveryProfileForm" hidden>
        <?= csrfCampo() ?>

        <input type="hidden" name="modo" value="descobrir">
        <h3>Descubra seu perfil</h3>
        <p>Responda pensando no prazo em que pretende usar o dinheiro e em como você reage a
            oscilações.</p>
        <label>Objetivo<select name="objetivo" required>

                <?php foreach (
                    [
                        'reserva' => 'Reserva de emergência',
                        'viagem' => 'Viagem',
                        'casa' => 'Casa',
                        'aposentadoria' => 'Aposentadoria',
                        'patrimonio' => 'Construir patrimônio',
                        'outro' => 'Outro'
                    ]
                    as $valor => $rotulo
                ): ?>

                <option value="<?= $valor ?>"><?= $rotulo ?></option>

                <?php endforeach; ?>

            </select></label>
        <label>Prazo<select name="prazo" required>
                <option value="curto">Curto prazo — até 2 anos</option>
                <option value="medio" selected>Médio prazo — 2 a 5 anos</option>
                <option value="longo">Longo prazo — acima de 5 anos</option>
            </select></label>
        <label>Como você se sentiria vendo seu investimento cair temporariamente?<select
                name="risco" required>
                <option value="1">Ficaria desconfortável e priorizaria estabilidade</option>
                <option value="2">Aceitaria alguma oscilação por mais potencial</option>
                <option value="3">Aceitaria oscilações maiores pensando no longo prazo</option>
            </select></label>
        <label class="cp15-checkbox">
            <input type="checkbox" name="reserva_emergencia" value="1"> Já possuo uma reserva de
            emergência
        </label>
        <button class="btn btn-primario" type="submit">Descobrir meu perfil</button>
    </form>
    <div class="cp154-invest-basics"><span><strong>Liquidez</strong><small>Facilidade de transformar
                o investimento em
                dinheiro.</small></span><span><strong>Risco</strong><small>Possibilidade de
                oscilação ou perda.</small></span><span><strong>Diversificação</strong><small>Evita
                concentração excessiva.</small></span><span><strong>Prazo</strong><small>Quando você
                pretende usar o dinheiro.</small></span></div>
</section>
<script>
    document.querySelectorAll('[data-invest-known]').forEach((b) => b.addEventListener('click',
        () => {
            document.getElementById('knownProfileForm').hidden = b.dataset.investKnown !==
                '1';
            document.getElementById('discoveryProfileForm').hidden = b.dataset
                .investKnown !== '0';
        }));
</script>

<?php else: ?>


<section class="surface-card cp154-profile-summary">
    <div><span class="eyebrow">SEU PERFIL</span>
        <h2><?= ucfirst(limpar((string) $perfilInvestidor['perfil'])) ?></h2>
        <p>Sua carteira aparece abaixo com base nos investimentos que você cadastrou. O perfil serve
            como contexto educacional e pode ser revisto quando sua realidade mudar.</p>
    </div>
    <a class="btn btn-secundario" href="perfil.php">Revisar dados financeiros</a>
</section>

<?php endif; ?>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>
