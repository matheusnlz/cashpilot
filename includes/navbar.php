<?php

require_once __DIR__ . '/icons.php';

$cpNavPaginaAtual = basename($_SERVER['PHP_SELF']);

if (in_array(
    $cpNavPaginaAtual,
    ['aula.php', 'aprender_gerenciar.php'],
    true
)) {
    $cpNavPaginaAtual = 'aprender.php';
}

$cpNavEmpreendedor = usuarioLogadoTipo() === 'mei';
$cpNavAvatar = $_SESSION['usuario_avatar'] ?? '';
$cpNavNomeUsuario = usuarioLogadoNome();
$cpNavIniciais = '';

foreach (preg_split('/\s+/', trim($cpNavNomeUsuario)) as $parteNome) {
    if ($parteNome !== '') {
        $cpNavIniciais .= mb_strtoupper(
            mb_substr($parteNome, 0, 1)
        );
    }

    if (mb_strlen($cpNavIniciais) >= 2) {
        break;
    }
}

$cpNavGruposEmpreendedor = [
    'VISÃO GERAL' => [
        ['dashboard.php', 'Dashboard', 'grid'],
        ['visao_financeira.php', 'Visão Financeira', 'radar'],
    ],
    'FINANCEIRO' => [
        ['receitas.php', 'Receitas', 'trending-up'],
        ['despesas.php', 'Despesas', 'trending-down'],
        ['transacoes.php', 'Transações', 'arrow-left-right'],
    ],
    'NEGÓCIO' => [
        ['negocio.php', 'Visão do Negócio', 'briefcase'],
        ['desempenho.php', 'Desempenho', 'chart'],
        ['projecao_caixa.php', 'Projeção de Caixa', 'forecast'],
        ['vendas.php', 'Vendas', 'sale'],
        ['produtos_servicos.php', 'Produtos e Serviços', 'box'],
        ['funcionarios.php', 'Funcionários', 'users'],
        ['fornecedores.php', 'Fornecedores', 'truck'],
        ['custos.php', 'Custos', 'receipt'],
    ],
    'ANÁLISES' => [
        ['relatorios.php', 'Relatórios', 'chart'],
        ['radar.php', 'RadarPilot', 'radar'],
    ],
    'INTELIGÊNCIA' => [
        ['copiloto.php', 'Copiloto', 'spark'],
        ['planos_acao.php', 'Planos de Ação', 'target'],
    ],
    'CONHECIMENTO' => [
        ['aprender.php', 'Aprender', 'graduation'],
    ],
];

$cpNavGruposPessoaFisica = [
    'VISÃO GERAL' => [
        ['dashboard.php', 'Dashboard', 'grid'],
        ['visao_financeira.php', 'Visão Financeira', 'radar'],
    ],
    'FINANÇAS' => [
        ['receitas.php', 'Receitas', 'trending-up'],
        ['despesas.php', 'Despesas', 'trending-down'],
        ['transacoes.php', 'Transações', 'arrow-left-right'],
        ['orcamentos.php', 'Orçamentos', 'budget'],
        ['recorrencias.php', 'Recorrências', 'repeat'],
    ],
    'PLANEJAMENTO' => [
        ['saude_financeira.php', 'Saúde Financeira', 'radar'],
        ['metas.php', 'Metas', 'target'],
        ['financiamentos.php', 'Financiamentos', 'finance'],
        ['investimentos.php', 'Investimentos', 'invest'],
        ['posso_comprar.php', 'Posso comprar?', 'cart'],
        ['planejamento.php', 'Planejamento mensal', 'calendar'],
    ],
    'ANÁLISES' => [
        ['relatorios.php', 'Relatórios', 'chart'],
        ['radar.php', 'RadarPilot', 'radar'],
    ],
    'INTELIGÊNCIA' => [
        ['copiloto.php', 'Copiloto', 'spark'],
        ['planos_acao.php', 'Planos de Ação', 'target'],
    ],
    'CONHECIMENTO' => [
        ['aprender.php', 'Aprender', 'graduation'],
    ],
];

$cpNavGrupos = $cpNavEmpreendedor
    ? $cpNavGruposEmpreendedor
    : $cpNavGruposPessoaFisica;

$cpNavGrupoAtual = '';

foreach ($cpNavGrupos as $tituloGrupo => $linksGrupo) {
    foreach ($linksGrupo as $linkGrupo) {
        if (($linkGrupo[0] ?? '') === $cpNavPaginaAtual) {
            $cpNavGrupoAtual = $tituloGrupo;
            break 2;
        }
    }
}
?>

<div class="mobile-topbar">
    <button
        type="button"
        id="mobileMenuOpen"
        class="mobile-menu-btn"
        aria-label="Abrir menu"
    >
        <span></span>
        <span></span>
        <span></span>
    </button>

    <a href="dashboard.php" class="mobile-brand">
        <img
            src="../assets/img/logo-cashpilot-escura.png"
            alt="CashPilot"
        >
    </a>

    <a href="perfil.php" class="mobile-avatar">
        <?php if ($cpNavAvatar): ?>
            <img
                src="<?= $cpNavAvatar === '__db_avatar__'
                    ? '../actions/avatar.php'
                    : '../' . limpar($cpNavAvatar) ?>"
                alt="Perfil"
            >
        <?php else: ?>
            <?= limpar($cpNavIniciais ?: 'CP') ?>
        <?php endif; ?>
    </a>
</div>

<nav class="cp-mobile-navigation" aria-label="Navegação principal mobile">
    <div class="cp-mobile-groups" id="cpMobileGroups">
        <?php foreach ($cpNavGrupos as $tituloGrupo => $linksGrupo): ?>
            <?php $grupoId = 'cp-mobile-' . substr(md5($tituloGrupo), 0, 8); ?>
            <button
                type="button"
                class="cp-mobile-group <?= $cpNavGrupoAtual === $tituloGrupo ? 'ativo' : '' ?>"
                data-mobile-group="<?= limpar($grupoId) ?>"
                aria-expanded="false"
            >
                <?= limpar(ucwords(mb_strtolower($tituloGrupo))) ?>
            </button>
        <?php endforeach; ?>
    </div>
</nav>

<div class="cp-mobile-subnav-layer" id="cpMobileSubnavLayer" hidden>
    <button class="cp-mobile-subnav-backdrop" type="button" aria-label="Fechar navegação"></button>

    <?php foreach ($cpNavGrupos as $tituloGrupo => $linksGrupo): ?>
        <?php $grupoId = 'cp-mobile-' . substr(md5($tituloGrupo), 0, 8); ?>
        <section class="cp-mobile-subnav" data-mobile-panel="<?= limpar($grupoId) ?>" hidden>
            <header>
                <div>
                    <small>ÁREA</small>
                    <strong><?= limpar(ucwords(mb_strtolower($tituloGrupo))) ?></strong>
                </div>
                <button type="button" class="cp-mobile-subnav-close" aria-label="Fechar">×</button>
            </header>

            <div class="cp-mobile-subnav-links">
                <?php foreach ($linksGrupo as $link): ?>
                    <?php
                    $arquivo = $link[0] ?? '';
                    $label = $link[1] ?? '';
                    $icone = $link[2] ?? 'grid';
                    ?>
                    <a
                        href="<?= limpar($arquivo) ?>"
                        class="<?= $cpNavPaginaAtual === $arquivo ? 'ativo' : '' ?>"
                    >
                        <span><?= cpIcon($icone) ?></span>
                        <span><?= limpar($label) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="layout">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-topo">
        <a
            href="dashboard.php"
            class="logo-wrap"
            aria-label="CashPilot"
        >
            <img
                src="../assets/img/logo-cashpilot-v13-dark.png"
                class="brand-logo-full brand-logo-light"
                alt="CashPilot"
            >

            <img
                src="../assets/img/logo-cashpilot-v13.png"
                class="brand-logo-full brand-logo-dark"
                alt="CashPilot"
            >

            <img
                src="../assets/img/logo-cashpilot-simbolo-escuro.png"
                class="brand-logo-mini brand-logo-mini-light"
                alt="CashPilot"
            >

            <img
                src="../assets/img/logo-cashpilot-simbolo.png"
                class="brand-logo-mini brand-logo-mini-dark"
                alt="CashPilot"
            >
        </a>

        <button
            type="button"
            class="sidebar-toggle"
            id="sidebarToggle"
            aria-label="Recolher menu"
        >
            <span></span>
        </button>

        <button
            type="button"
            class="sidebar-mobile-close"
            id="mobileMenuClose"
            aria-label="Fechar menu"
        >
            ×
        </button>
    </div>

    <nav class="sidebar-menu sidebar-menu-v10">
        <?php foreach ($cpNavGrupos as $tituloGrupo => $linksGrupo): ?>
            <div class="menu-grupo">
                <div class="menu-grupo-titulo">
                    <?= limpar($tituloGrupo) ?>
                </div>

                <?php foreach ($linksGrupo as $link): ?>
                    <?php
                    $arquivo = $link[0] ?? '';
                    $label = $link[1] ?? '';
                    $icone = $link[2] ?? 'grid';
                    ?>

                    <a
                        href="<?= limpar($arquivo) ?>"
                        class="menu-item <?= $cpNavPaginaAtual === $arquivo ? 'ativo' : '' ?>"
                        title="<?= limpar($label) ?>"
                    >
                        <span class="menu-icone">
                            <?= cpIcon($icone) ?>
                        </span>

                        <span class="menu-label">
                            <?= limpar($label) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-rodape sidebar-rodape-v101">
        <button
            type="button"
            class="menu-item sidebar-theme-toggle airplane-window-toggle"
            id="themeToggle"
            title="Alternar aparência"
            aria-label="Alternar entre modo claro e escuro"
        >
            <span class="airplane-window" aria-hidden="true">
                <i class="airplane-window-sky">
                    <b></b>
                    <em></em>
                </i>

                <i class="airplane-window-shade"></i>
            </span>

            <span class="theme-toggle-tooltip" role="tooltip">
                Alternar aparência
            </span>
        </button>

        <a
            href="perfil.php"
            class="menu-item perfil-menu <?= $cpNavPaginaAtual === 'perfil.php' ? 'ativo' : '' ?>"
            title="Meu perfil"
        >
            <?php if ($cpNavAvatar): ?>
                <img
                    class="avatar avatar-mini"
                    src="<?= $cpNavAvatar === '__db_avatar__'
                    ? '../actions/avatar.php'
                    : '../' . limpar($cpNavAvatar) ?>"
                    alt=""
                >
            <?php else: ?>
                <span class="avatar avatar-mini avatar-iniciais">
                    <?= limpar($cpNavIniciais ?: 'CP') ?>
                </span>
            <?php endif; ?>

            <span class="menu-label sidebar-user-name">
                <?= limpar($cpNavNomeUsuario) ?>
            </span>
        </a>
    </div>
</aside>

<main class="conteudo">
<?php if (isset($_SESSION['email_verificado']) && !(int) $_SESSION['email_verificado']): ?>
    <div class="cp-email-banner"><span><strong>Confirme seu e-mail</strong> para proteger melhor sua conta.</span><a href="verificar_email.php">Confirmar agora</a></div>
<?php endif; ?>
<div class="conteudo-inner">

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('sidebar');
    const toggleSidebar = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');
    const abrirMobile = document.getElementById('mobileMenuOpen');
    const fecharMobile = document.getElementById('mobileMenuClose');
    const themeToggle = document.getElementById('themeToggle');

    if (
        localStorage.getItem('cashpilot_sidebar_recolhida') === '1'
    ) {
        sidebar?.classList.add('recolhida');
    }

    toggleSidebar?.addEventListener('click', () => {
        sidebar.classList.toggle('recolhida');

        localStorage.setItem(
            'cashpilot_sidebar_recolhida',
            sidebar.classList.contains('recolhida') ? '1' : '0'
        );
    });

    themeToggle?.addEventListener('click', async () => {
        const atual =
            document.documentElement.getAttribute('data-theme') === 'dark'
                ? 'dark'
                : 'light';

        const proximo = atual === 'dark' ? 'light' : 'dark';

        document.documentElement.setAttribute(
            'data-theme',
            proximo
        );

        localStorage.setItem('cashpilot_tema', proximo);

        document.dispatchEvent(
            new CustomEvent('cashpilot:themechange', {
                detail: {
                    theme: proximo,
                },
            })
        );

        try {
            const form = new FormData();

            form.append('csrf_token', <?= json_encode(csrfToken()) ?>);
            form.append('acao', 'tema');
            form.append('tema', proximo);

            await fetch('../actions/preferencias.php', {
                method: 'POST',
                body: form,
            });
        } catch (erro) {
            console.warn(
                'Não foi possível salvar a preferência de tema.',
                erro
            );
        }
    });

    const abrirMenu = () => {
        sidebar?.classList.add('mobile-open');
        overlay?.classList.add('ativo');
        document.body.classList.add('menu-aberto');
    };

    const fecharMenu = () => {
        sidebar?.classList.remove('mobile-open');
        overlay?.classList.remove('ativo');
        document.body.classList.remove('menu-aberto');
    };

    abrirMobile?.addEventListener('click', abrirMenu);
    fecharMobile?.addEventListener('click', fecharMenu);
    overlay?.addEventListener('click', fecharMenu);
});
</script>
