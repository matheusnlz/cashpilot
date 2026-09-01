<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/negocio_financeiro.php';
require_once __DIR__ . '/../includes/personalizacao.php';

exigirLogin();

if (usuarioLogadoTipo() !== 'mei') {
    header('Location: dashboard.php');
    exit;
}

$tituloPagina = 'Visão do Negócio';
$pdo = conectar();
$usuarioId = (int) usuarioLogadoId();

cpSincronizarCustosRecorrentesMes(
    $pdo,
    $usuarioId
);

$stmt = $pdo->prepare(
    'SELECT nome, nicho
     FROM usuarios
     WHERE id = :uid'
);

$stmt->execute([
    'uid' => $usuarioId,
]);

$usuario = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$perfil = cpPerfilNegocio(
    $pdo,
    $usuarioId
);

$compromissos = cpCompromissosMensais(
    $pdo,
    $usuarioId
);

$foco = cpFocoEmpreendedor(
    array_merge(
        $usuario,
        $perfil
    )
);

$contagens = [
    'itens' => 0,
    'funcionarios' => 0,
    'fornecedores' => 0,
    'custos' => 0,
];

$tabelas = [
    'produtos_servicos' => 'itens',
    'funcionarios' => 'funcionarios',
    'fornecedores' => 'fornecedores',
    'custos_negocio' => 'custos',
];

foreach ($tabelas as $tabela => $chave) {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM {$tabela}
             WHERE usuario_id = :uid
               AND ativo = 1"
        );

        $stmt->execute([
            'uid' => $usuarioId,
        ]);

        $contagens[$chave] = (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $contagens[$chave] = 0;
    }
}

$operacoes = [
    'presencial' => 'Presencial',
    'online' => 'Online',
    'domicilio' => 'Delivery / domicílio',
    'hibrido' => 'Híbrido',
];

$ofertas = [
    'produtos' => 'Produtos',
    'servicos' => 'Serviços',
    'ambos' => 'Produtos e serviços',
];

$publicos = [
    'Público geral',
    'Empresas / B2B',
    'Famílias',
    'Crianças e responsáveis',
    'Jovens e adolescentes',
    'Adultos',
    'Principalmente homens',
    'Principalmente mulheres',
    'Público de maior poder aquisitivo',
    'Público local / regional',
];

$objetivos = [
    'Aumentar vendas e faturamento',
    'Melhorar lucro e margem',
    'Organizar despesas e custos',
    'Melhorar o fluxo de caixa',
    'Controlar estoque',
    'Organizar equipe',
    'Fidelizar e aumentar clientes',
    'Ganhar previsibilidade',
    'Entender melhor meu negócio',
];

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">
            ESTRUTURA DO NEGÓCIO
        </span>

        <h1>
            <?= limpar(
                $perfil['nome_negocio'] ?? 'Visão do Negócio'
            ) ?>
        </h1>

        <p>
            <?= limpar($usuario['nicho'] ?? 'Empreendimento') ?>
            ·
            <?= limpar(
                $operacoes[$perfil['operacao'] ?? 'presencial']
                ?? 'Presencial'
            ) ?>
            ·
            <?= limpar(
                $perfil['publico_alvo']
                ?? 'Público ainda não informado'
            ) ?>
        </p>
    </div>

    <button
        class="btn btn-secundario"
        data-drawer-open="drawerNegocio"
    >
        Editar dados do negócio
    </button>
</div>

<section class="surface-card business-focus-card">
    <div>
        <span class="eyebrow">
            COMO O CASHPILOT ESTÁ PRIORIZANDO SEU PAINEL
        </span>

        <h2>
            <?= limpar($foco['titulo']) ?>
        </h2>

        <p>
            <?= limpar($foco['descricao']) ?>
        </p>
    </div>

    <button
        class="text-button"
        data-drawer-open="drawerNegocio"
    >
        Alterar prioridade
    </button>
</section>

<section class="summary-strip">
    <div>
        <small>Funcionários</small>
        <strong>
            <?= $contagens['funcionarios'] ?>
        </strong>
    </div>

    <div>
        <small>Produtos/serviços</small>
        <strong>
            <?= $contagens['itens'] ?>
        </strong>
    </div>

    <div>
        <small>Fornecedores</small>
        <strong>
            <?= $contagens['fornecedores'] ?>
        </strong>
    </div>

    <div>
        <small>Compromissos recorrentes</small>
        <strong>
            <?= formatarMoeda(
                $compromissos['total']
            ) ?>
        </strong>
    </div>
</section>

<div class="business-hub-clean">
    <a href="vendas.php" class="business-hub-card">
        <span><?= cpIcon('sale') ?></span>
        <div>
            <strong>Vendas</strong>
            <p>
                Registre vendas com vários itens e acompanhe margem.
            </p>
        </div>
        <b>→</b>
    </a>

    <a href="produtos_servicos.php" class="business-hub-card">
        <span><?= cpIcon('box') ?></span>
        <div>
            <strong>Produtos e Serviços</strong>
            <p>
                Catálogo, fornecedor, preço, custo e estoque.
            </p>
        </div>
        <b>→</b>
    </a>

    <a href="funcionarios.php" class="business-hub-card">
        <span><?= cpIcon('users') ?></span>
        <div>
            <strong>Funcionários</strong>
            <p>
                Equipe, salários e custo mensal da operação.
            </p>
        </div>
        <b>→</b>
    </a>

    <a href="fornecedores.php" class="business-hub-card">
        <span><?= cpIcon('truck') ?></span>
        <div>
            <strong>Fornecedores</strong>
            <p>
                Frequência de pagamentos e produtos vinculados.
            </p>
        </div>
        <b>→</b>
    </a>

    <a href="custos.php" class="business-hub-card">
        <span><?= cpIcon('receipt') ?></span>
        <div>
            <strong>Custos</strong>
            <p>
                Despesas operacionais separadas da equipe e fornecedores.
            </p>
        </div>
        <b>→</b>
    </a>

    <a href="relatorios.php" class="business-hub-card">
        <span><?= cpIcon('chart') ?></span>
        <div>
            <strong>Relatórios</strong>
            <p>
                Aprofunde faturamento, despesas e desempenho.
            </p>
        </div>
        <b>→</b>
    </a>
</div>

<section class="surface-card section-block business-profile-summary">
    <div class="section-title">
        <div>
            <span class="eyebrow">
                COMO O CASHPILOT ENTENDE SEU NEGÓCIO
            </span>

            <h2>
                Contexto usado pelo Dashboard, RadarPilot, Copiloto e Aprender
            </h2>
        </div>

        <button
            class="text-button"
            data-drawer-open="drawerNegocio"
        >
            Editar
        </button>
    </div>

    <div class="profile-facts">
        <span>
            <small>Nicho</small>
            <strong>
                <?= limpar(
                    $usuario['nicho'] ?? 'Não informado'
                ) ?>
            </strong>
        </span>

        <span>
            <small>Operação</small>
            <strong>
                <?= limpar(
                    $operacoes[$perfil['operacao'] ?? 'presencial']
                    ?? 'Presencial'
                ) ?>
            </strong>
        </span>

        <span>
            <small>Oferta</small>
            <strong>
                <?= limpar(
                    $ofertas[$perfil['oferta'] ?? 'servicos']
                    ?? 'Serviços'
                ) ?>
            </strong>
        </span>

        <span>
            <small>Público</small>
            <strong>
                <?= limpar(
                    $perfil['publico_alvo'] ?? 'Não informado'
                ) ?>
            </strong>
        </span>

        <span>
            <small>Objetivo</small>
            <strong>
                <?= limpar(
                    $perfil['objetivo_principal'] ?? 'Não informado'
                ) ?>
            </strong>
        </span>
    </div>
</section>

<aside class="cp-drawer" id="drawerNegocio">
    <div class="drawer-head">
        <div>
            <span class="eyebrow">
                PERFIL DO NEGÓCIO
            </span>

            <h2>
                Informações do negócio
            </h2>

            <p>
                O nicho definido no cadastro permanece bloqueado.
                A prioridade pode mudar conforme o momento da empresa.
            </p>
        </div>

        <button
            type="button"
            class="drawer-close"
            data-drawer-close
        >
            ×
        </button>
    </div>

    <div class="drawer-body">
        <form
            action="../actions/negocio.php"
            method="POST"
        >
            <?= csrfCampo() ?>

            <input
                type="hidden"
                name="acao"
                value="perfil"
            >

            <div class="form-grupo">
                <label>Nome do negócio</label>
                <input
                    name="nome_negocio"
                    value="<?= limpar(
                        $perfil['nome_negocio'] ?? ''
                    ) ?>"
                >
            </div>

            <div class="form-grupo">
                <label>Nicho</label>
                <input
                    value="<?= limpar(
                        $usuario['nicho'] ?? ''
                    ) ?>"
                    disabled
                >
            </div>

            <div class="form-linha">
                <div class="form-grupo">
                    <label>Operação</label>

                    <select name="operacao">
                        <?php foreach ($operacoes as $valor => $rotulo): ?>
                            <option
                                value="<?= limpar($valor) ?>"
                                <?= ($perfil['operacao'] ?? 'presencial') === $valor
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= limpar($rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grupo">
                    <label>Oferta</label>

                    <select name="oferta">
                        <?php foreach ($ofertas as $valor => $rotulo): ?>
                            <option
                                value="<?= limpar($valor) ?>"
                                <?= ($perfil['oferta'] ?? 'servicos') === $valor
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= limpar($rotulo) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-grupo">
                <label>Público principal</label>

                <select name="publico_alvo">
                    <?php foreach ($publicos as $publico): ?>
                        <option
                            value="<?= limpar($publico) ?>"
                            <?= ($perfil['publico_alvo'] ?? 'Público geral') === $publico
                                ? 'selected'
                                : '' ?>
                        >
                            <?= limpar($publico) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grupo">
                <label>Canal principal</label>
                <input
                    name="canal_principal"
                    value="<?= limpar(
                        $perfil['canal_principal'] ?? ''
                    ) ?>"
                >
            </div>

            <div class="form-grupo">
                <label>
                    Prioridade atual do negócio
                </label>

                <select name="objetivo_principal">
                    <?php foreach ($objetivos as $objetivo): ?>
                        <option
                            value="<?= limpar($objetivo) ?>"
                            <?= ($perfil['objetivo_principal'] ?? 'Entender melhor meu negócio') === $objetivo
                                ? 'selected'
                                : '' ?>
                        >
                            <?= limpar($objetivo) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <small class="secao-ajuda">
                    Essa escolha influencia os destaques do Dashboard,
                    o contexto do Copiloto e as recomendações do Aprender.
                </small>
            </div>

            <button class="btn btn-primario btn-bloco">
                Salvar informações
            </button>
        </form>
    </div>
</aside>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
