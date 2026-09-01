<?php

require_once __DIR__ . '/includes/auth.php';


$usuarioLogado = !empty($_SESSION['usuario_id']);

$destinoPrincipal = $usuarioLogado
    ? 'pages/dashboard.php'
    : 'pages/cadastro.php';


$textoPrincipal = $usuarioLogado
    ? 'Abrir meu painel'
    : 'Criar minha conta';?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta
        name="description"
        content="CashPilot: organização financeira para pessoas físicas e pequenos empreendedores, com RadarPilot, CashScore e Copiloto."
    >
<title>CashPilot · Clareza para suas finanças</title>
<link rel="stylesheet" href="assets/css/landing.css?v=13.0">
</head>
<body class="landing-body">
<header class="landing-header" id="landingHeader">
<a class="landing-brand" href="#inicio" aria-label="CashPilot">
<img
            src="assets/img/logo-cashpilot-v13.png"
            alt="CashPilot"
        >
</a>
<button
        type="button"
        class="landing-menu-button"
        id="landingMenuButton"
        aria-label="Abrir menu"
        aria-expanded="false"
        aria-controls="landingMenu"
    >
<span>
</span>
<span>
</span>
<span>
</span>
</button>
</header>
<div class="landing-menu-overlay" id="landingMenuOverlay">
</div>
<aside
    class="landing-menu"
    id="landingMenu"
    aria-hidden="true"
>
<div class="landing-menu-head">
<img
            src="assets/img/logo-cashpilot-v13-dark.png"
            alt="CashPilot"
        >
<button
            type="button"
            class="landing-menu-close"
            id="landingMenuClose"
            aria-label="Fechar menu"
        >
            ×
        </button>
</div>
<nav>
<a href="#inicio">Início</a>
<a href="#diferenciais">Por que CashPilot?</a>
<a href="#perfis">Pessoa Física e Empreendedor</a>
<a href="#copiloto">Copiloto</a>
<a href="#radar">RadarPilot e CashScore</a>
<a href="#aprender">Aprender</a>
<a href="#como-funciona">Como funciona</a>
</nav>
<div class="landing-menu-actions">
        <?php if ($usuarioLogado):?>
            <a
                class="landing-btn landing-btn-primary"
                href="pages/dashboard.php"
            >
                Abrir meu painel
            </a>
        <?php else:?>
            <a
                class="landing-btn landing-btn-primary"
                href="pages/cadastro.php"
            >
                Criar conta
            </a>
<a
                class="landing-btn landing-btn-ghost"
                href="pages/login.php"
            >
                Entrar
            </a>
        <?php endif;?>
    </div>
</aside>
<main>
<section class="landing-hero" id="inicio">
<div class="landing-hero-glow landing-hero-glow-one">
</div>
<div class="landing-hero-glow landing-hero-glow-two">
</div>
<div class="landing-shell landing-hero-content">
<div class="landing-hero-copy landing-reveal">
<div class="landing-hero-mark">
<img
                        src="assets/img/logo-cashpilot-v13.png"
                        alt="CashPilot"
                    >
</div>
<span class="landing-eyebrow">
                    GESTÃO FINANCEIRA COM MAIS CONTEXTO
                </span>
<h1>
                    Entenda seus números.<br>
<span>Planeje os próximos passos.</span>
</h1>
<p>
                    O CashPilot organiza sua vida financeira ou seu negócio
                    e transforma números em informações mais fáceis de entender.
                </p>
<div class="landing-hero-actions">
<a
                        class="landing-btn landing-btn-primary landing-btn-large"
                        href="<?= limpar($destinoPrincipal)?>"
                    >
                        <?= limpar($textoPrincipal)?>
                        <span>→</span>
</a>

                    <?php if (!$usuarioLogado):?>
                        <a
                            class="landing-login-link"
                            href="pages/login.php"
                        >
                            Já possui uma conta?
                            <strong>Entrar</strong>
</a>
                    <?php endif;?>
                </div>
</div>
<div
                class="landing-product-preview landing-reveal"
                aria-label="Prévia do dashboard do CashPilot"
            >
<div class="landing-preview-frame">
<div class="landing-preview-sidebar">
<img
                            src="assets/img/logo-cashpilot-simbolo-escuro.png"
                            alt=""
                        >
<span class="active">
</span>
<span>
</span>
<span>
</span>
<span>
</span>
<span>
</span>
</div>
<div class="landing-preview-main">
<div class="landing-preview-top">
<div>
<small>VISÃO GERAL</small>
<strong>Olá, bem-vindo ao CashPilot.</strong>
</div>
<span class="landing-preview-avatar">CP</span>
</div>
<div class="landing-preview-kpis">
<article>
<small>Saldo atual</small>
<strong>R$ 3.240,00</strong>
<em>+8,4% no mês</em>
</article>
<article>
<small>Receitas</small>
<strong>R$ 5.480,00</strong>
<em>Agosto</em>
</article>
<article>
<small>Despesas</small>
<strong>R$ 2.240,00</strong>
<em>Agosto</em>
</article>
</div>
<div class="landing-preview-grid">
<article class="landing-preview-chart">
<div class="landing-preview-card-head">
<span>Evolução financeira</span>
<small>6M</small>
</div>
<svg viewBox="0 0 520 160" aria-hidden="true">
<defs>
<linearGradient
                                            id="cashpilotChartGradient"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
<stop
                                                offset="0%"
                                                stop-color="#315e59"
                                                stop-opacity=".22"
                                            />
<stop
                                                offset="100%"
                                                stop-color="#315e59"
                                                stop-opacity="0"
                                            />
</linearGradient>
</defs>
<path
                                        class="area"
                                        d="M0 132 C60 110, 90 122, 135 88 S220 102, 260 68 S345 70, 390 42 S470 52, 520 20 L520 160 L0 160 Z"
                                    />
<path
                                        class="line"
                                        d="M0 132 C60 110, 90 122, 135 88 S220 102, 260 68 S345 70, 390 42 S470 52, 520 20"
                                    />
</svg>
</article>
<article class="landing-preview-radar">
<div class="landing-preview-card-head">
<span>RadarPilot</span>
<small>Agora</small>
</div>
<div class="landing-radar-note positive">
<i>
</i>
<div>
<strong>Boa evolução</strong>
<small>Despesas menores neste mês.</small>
</div>
</div>
<div class="landing-radar-note warning">
<i>
</i>
<div>
<strong>Atenção</strong>
<small>Alimentação cresceu 12%.</small>
</div>
</div>
</article>
</div>
</div>
</div>
<div class="landing-preview-caption">
<span>
</span>
                    Interface inspirada no painel real do CashPilot
                </div>
</div>
</div>
<a class="landing-scroll-hint" href="#diferenciais">
            Conheça o CashPilot
            <span>↓</span>
</a>
</section>
<section
        class="landing-section landing-section-light"
        id="diferenciais"
    >
<div class="landing-shell">
<div class="landing-section-heading landing-reveal">
<span class="landing-eyebrow landing-eyebrow-dark">
                    POR QUE CASHPILOT?
                </span>
<h2>
                    Um sistema que ajuda você a entender o que seus números realmente dizem.
                </h2>
<p>
                    Muitos sistemas mostram quanto entrou e quanto saiu.
                    O CashPilot foi pensado para ajudar você a entender
                    o que esses números significam e o que merece atenção.
                </p>
</div>
<div class="landing-difference-grid landing-difference-grid-v13">
<article class="landing-difference-card landing-reveal">
<span class="landing-card-number">01</span>
<div class="landing-card-icon">✦</div>
<h3>Copiloto que entende o contexto</h3>
<p>Converse sobre seus próprios dados. O contexto muda entre finanças pessoais e negócio e também acompanha a área do sistema em que você está.</p>
</article>
<article class="landing-difference-card landing-reveal">
<span class="landing-card-number">02</span>
<div class="landing-card-icon">◎</div>
<h3>RadarPilot + CashScore</h3>
<p>Identifique mudanças, riscos e evoluções sem depender apenas de tabelas. O CashScore resume a saúde financeira e mostra o que influencia a leitura.</p>
</article>
<article class="landing-difference-card landing-reveal">
<span class="landing-card-number">03</span>
<div class="landing-card-icon">▦</div>
<h3>Experiências realmente diferentes</h3>
<p>Pessoa Física recebe orçamento, metas e planejamento. Empreendedor acompanha vendas, margem, estoque, equipe, fornecedores e custos.</p>
</article>
<article class="landing-difference-card landing-reveal">
<span class="landing-card-number">04</span>
<div class="landing-card-icon">↗</div>
<h3>Da informação para a decisão</h3>
<p>Importe ou registre movimentações, compare períodos, simule decisões e use relatórios e análises para entender o próximo passo com mais clareza.</p>
</article>
</div>
</div>
</section>
<section class="landing-section landing-profiles" id="perfis">
<div class="landing-shell">
<div class="landing-section-heading landing-reveal">
<span class="landing-eyebrow">
                    UM SISTEMA, DUAS REALIDADES
                </span>
<h2>
                    O CashPilot muda de acordo com quem usa.
                </h2>
<p>
                    A experiência não é apenas renomeada: as ferramentas,
                    indicadores e análises acompanham o perfil escolhido.
                </p>
</div>
<div class="landing-profile-switcher landing-reveal">
<button
                    type="button"
                    class="landing-profile-tab active"
                    data-profile="pf"
                >
                    Pessoa Física
                </button>
<button
                    type="button"
                    class="landing-profile-tab"
                    data-profile="empreendedor"
                >
                    Empreendedor
                </button>
</div>
<div class="landing-profile-stage">
<article
                    class="landing-profile-panel active"
                    data-profile-panel="pf"
                >
<div class="landing-profile-copy">
<span class="landing-mini-label">
                            PARA SUA VIDA FINANCEIRA
                        </span>
<h3>
                            Entenda seu dinheiro antes de decidir o próximo passo.
                        </h3>
<p>
                            A área Pessoa Física reúne organização,
                            planejamento e acompanhamento para transformar
                            movimentações do dia a dia em uma visão mais clara.
                        </p>
<div class="landing-feature-list">
<span>Receitas e despesas</span>
<span>Orçamentos por categoria</span>
<span>Metas e reserva de emergência</span>
<span>Financiamentos e decisões de compra</span>
<span>CashScore e RadarPilot</span>
<span>Copiloto financeiro</span>
</div>
</div>
<div class="landing-system-card">
<div class="landing-system-card-head">
<span>Saúde Financeira</span>
<small>Pessoa Física</small>
</div>
<div class="landing-score-row">
<div class="landing-score-ring">
<span>78</span>
<small>/100</small>
</div>
<div>
<strong>Boa organização</strong>
<p>
                                    Seu orçamento está equilibrado e sua
                                    reserva evoluiu neste mês.
                                </p>
</div>
</div>
<div class="landing-mini-progress">
<div>
<span>Reserva de emergência</span>
<strong>62%</strong>
</div>
<i>
<b style="width:62%">
</b>
</i>
</div>
<div class="landing-mini-progress">
<div>
<span>Meta principal</span>
<strong>74%</strong>
</div>
<i>
<b style="width:74%">
</b>
</i>
</div>
</div>
</article>
<article
                    class="landing-profile-panel"
                    data-profile-panel="empreendedor"
                    hidden
                >
<div class="landing-profile-copy">
<span class="landing-mini-label">
                            PARA SEU NEGÓCIO
                        </span>
<h3>
                            Controle a operação sem perder de vista o resultado.
                        </h3>
<p>
                            O perfil Empreendedor conecta o financeiro
                            à rotina do negócio e adapta destaques conforme
                            segmento, público e prioridade atual.
                        </p>
<div class="landing-feature-list">
<span>Vendas e ticket médio</span>
<span>Produtos e serviços</span>
<span>Estoque</span>
<span>Funcionários e fornecedores</span>
<span>Custos, margem e previsão de caixa</span>
<span>Copiloto contextualizado ao negócio</span>
</div>
</div>
<div class="landing-system-card landing-business-card">
<div class="landing-system-card-head">
<span>Desempenho do negócio</span>
<small>Empreendedor</small>
</div>
<div class="landing-business-metrics">
<div>
<small>Faturamento</small>
<strong>R$ 18.420</strong>
</div>
<div>
<small>Margem</small>
<strong>38,4%</strong>
</div>
<div>
<small>Ticket médio</small>
<strong>R$ 184</strong>
</div>
</div>
<div class="landing-business-focus">
<span>FOCO ATUAL</span>
<strong>Melhorar lucro e margem</strong>
<p>
                                O painel prioriza custos, margem e
                                rentabilidade dos itens.
                            </p>
</div>
</div>
</article>
</div>
</div>
</section>
<section class="landing-section landing-copilot" id="copiloto">
<div class="landing-shell landing-copilot-grid">
<div class="landing-copilot-copy landing-reveal">
<span class="landing-eyebrow">
                    ✦ COPILOTO
                </span>
<h2>
                    Seus dados podem explicar mais do que parecem.
                </h2>
<p>
                    O Copiloto é a camada de inteligência do CashPilot. Ele recebe o contexto financeiro disponível, entende se você usa o perfil Pessoa Física ou Empreendedor e pode continuar uma análise com base no que já foi perguntado. A proposta é explicar seus próprios números em linguagem simples — não responder de forma genérica.
                </p>
<div class="landing-copilot-points">
<span>Conversa com memória de contexto</span>
<span>Respostas ligadas aos dados do usuário</span>
<span>Contexto diferente para PF e Empreendedor</span>
<span>Integração com a API da Groq</span>
</div>
</div>
<div class="landing-ai-window landing-reveal">
<div class="landing-ai-head">
<div class="landing-ai-brand">
<span>✦</span>
<div>
<strong>Copiloto</strong>
<small>CashPilot</small>
</div>
</div>
<span class="landing-ai-status">
<i>
</i>
                        Pronto
                    </span>
</div>
<div class="landing-ai-messages">
<div class="landing-ai-user">
                        Onde estou gastando mais este mês?
                    </div>
<div class="landing-ai-assistant">
<span class="landing-ai-avatar">✦</span>
<div>
<strong>Copiloto</strong>
<p>
                                Alimentação foi sua maior categoria de
                                despesas neste mês, com crescimento em
                                relação ao período anterior.
                            </p>
</div>
</div>
<div class="landing-ai-user">
                        E comparando com julho?
                    </div>
<div class="landing-ai-assistant">
<span class="landing-ai-avatar">✦</span>
<div>
<strong>Copiloto</strong>
<p>
                                O aumento está concentrado principalmente
                                em alimentação e lazer. Posso detalhar
                                qual categoria mais influenciou a mudança.
                            </p>
</div>
</div>
</div>
<div class="landing-ai-suggestions">
<span>Qual categoria mais cresceu?</span>
<span>O que devo priorizar?</span>
</div>
<div class="landing-ai-composer">
<span>Pergunte sobre suas finanças...</span>
<button type="button" aria-label="Enviar demonstração">
                        ↑
                    </button>
</div>
</div>
</div>
</section>
<section class="landing-section landing-radar-section" id="radar">
<div class="landing-shell">
<div class="landing-section-heading landing-reveal">
<span class="landing-eyebrow landing-eyebrow-dark">
                    RADARPILOT + CASHSCORE
                </span>
<h2>
                    Informações importantes não deveriam passar despercebidas.
                </h2>
<p>
                    O RadarPilot observa os dados registrados e destaca mudanças, melhorias e pontos de atenção que podem passar despercebidos. O CashScore reúne diferentes sinais da organização financeira em uma leitura de 0 a 100 e explica os fatores que influenciam essa pontuação.
                </p>
</div>
<div class="landing-radar-layout">
<article class="landing-radar-board landing-reveal">
<div class="landing-radar-board-head">
<div>
<small>RADARPILOT</small>
<strong>O que merece atenção</strong>
</div>
<span>Agosto</span>
</div>
<div class="landing-radar-board-items">
<div class="landing-radar-board-item good">
<i>
</i>
<div>
<strong>Resultado positivo</strong>
<p>
                                    Suas receitas continuam acima das despesas.
                                </p>
</div>
<span>Positivo</span>
</div>
<div class="landing-radar-board-item attention">
<i>
</i>
<div>
<strong>Categoria em crescimento</strong>
<p>
                                    Alimentação aumentou em relação ao mês anterior.
                                </p>
</div>
<span>Atenção</span>
</div>
<div class="landing-radar-board-item neutral">
<i>
</i>
<div>
<strong>Meta no ritmo esperado</strong>
<p>
                                    O progresso atual está próximo do necessário.
                                </p>
</div>
<span>Acompanhar</span>
</div>
</div>
</article>
<article class="landing-score-board landing-reveal">
<span class="landing-mini-label">
                        CASHSCORE
                    </span>
<div class="landing-score-large">
<svg viewBox="0 0 160 160" aria-hidden="true">
<circle
                                cx="80"
                                cy="80"
                                r="65"
                                class="track"
                            />
<circle
                                cx="80"
                                cy="80"
                                r="65"
                                class="value"
                            />
</svg>
<div>
<strong>78</strong>
<span>/100</span>
</div>
</div>
<h3>
                        Uma leitura rápida da sua organização financeira.
                    </h3>
<p>
                        O número não trabalha sozinho: o CashPilot mostra
                        os fatores que influenciam a pontuação para que ela
                        continue compreensível.
                    </p>
</article>
</div>
</div>
</section>
<section class="landing-section landing-how landing-how-v13" id="como-funciona">
<div class="landing-shell">
<div class="landing-section-heading landing-reveal">
<span class="landing-eyebrow">COMO FUNCIONA</span>
<h2>Um fluxo financeiro que acompanha você do início à decisão.</h2>
<p>O CashPilot conecta cada etapa. O que você registra alimenta o painel, as análises e o Copiloto — sem transformar o sistema em uma sequência de telas isoladas.</p>
</div>
<div class="landing-flow-v13">
<article class="landing-flow-card landing-reveal">
<b>01</b>
<span>PERFIL</span>
<h3>Configure</h3>
<p>Defina se o uso é pessoal ou empreendedor para o sistema apresentar ferramentas adequadas à sua realidade.</p>
</article>
<article class="landing-flow-card landing-reveal">
<b>02</b>
<span>DADOS</span>
<h3>Organize</h3>
<p>Registre ou importe movimentações e mantenha categorias, metas e informações do negócio em ordem.</p>
</article>
<article class="landing-flow-card landing-reveal">
<b>03</b>
<span>PAINEL</span>
<h3>Acompanhe</h3>
<p>Visualize saldo, evolução, compromissos e indicadores em uma leitura centralizada do momento atual.</p>
</article>
<article class="landing-flow-card landing-reveal">
<b>04</b>
<span>ANÁLISE</span>
<h3>Entenda</h3>
<p>RadarPilot, CashScore, relatórios e Copiloto ajudam a revelar mudanças e pontos que merecem atenção.</p>
</article>
<article class="landing-flow-card landing-reveal">
<b>05</b>
<span>PRÓXIMO PASSO</span>
<h3>Planeje</h3>
<p>Use metas, orçamentos, simulações e planos de ação para transformar a análise em planejamento.</p>
</article>
</div>
</div>
</section>
<section class="landing-section landing-learning" id="aprender">
<div class="landing-shell landing-learning-grid">
<div class="landing-learning-copy landing-reveal">
<span class="landing-eyebrow landing-eyebrow-dark">
                    APRENDER
                </span>
<h2>
                    Conhecimento também faz parte do controle.
                </h2>
<p>
                    A área Aprender reúne vídeos selecionados pela própria equipe para complementar o uso do sistema. Educação financeira ajuda a compreender orçamento, crédito e planejamento; para empreendedores, o estudo de custos, margem e gestão melhora a qualidade das decisões do negócio.
                </p>
<div class="landing-learning-audiences">
<div>
<strong>Pessoa Física</strong>
<span>Orçamento</span>
<span>Planejamento</span>
<span>Crédito</span>
<span>Educação financeira</span>
</div>
<div>
<strong>Empreendedor</strong>
<span>Precificação</span>
<span>Custos</span>
<span>Margem</span>
<span>Gestão financeira</span>
</div>
</div>
</div>
<div class="landing-learning-cards landing-reveal">
<article>
<div class="landing-video-cover cover-one">
<span>▶</span>
</div>
<small>PLANEJAMENTO</small>
<strong>Organize seu dinheiro com mais clareza</strong>
<p>
                        Conteúdos selecionados para complementar
                        o uso das ferramentas do CashPilot.
                    </p>
</article>
<article>
<div class="landing-video-cover cover-two">
<span>▶</span>
</div>
<small>NEGÓCIOS</small>
<strong>Entenda custos, margem e precificação</strong>
<p>
                        Conteúdo voltado ao momento e ao perfil
                        do pequeno empreendedor.
                    </p>
</article>
</div>
</div>
</section>
</main>
<footer class="landing-footer">
<div class="landing-shell">
<div class="landing-footer-top">
<div class="landing-footer-brand">
<img
                    src="assets/img/logo-cashpilot-v13.png"
                    alt="CashPilot"
                >
<p>
                    Organização e inteligência para transformar
                    seus dados financeiros em informações mais claras.
                </p>
</div>
<div class="landing-footer-links">
<div>
<strong>CashPilot</strong>
<a href="#diferenciais">Por que CashPilot?</a>
<a href="#perfis">Perfis</a>
<a href="#copiloto">Copiloto</a>
<a href="#aprender">Aprender</a>
</div>
<div>
<strong>Acesso</strong>

                    <?php if ($usuarioLogado):?>
                        <a href="pages/dashboard.php">Meu painel</a>
                    <?php else:?>
                        <a href="pages/login.php">Entrar</a>
<a href="pages/cadastro.php">Criar conta</a>
                    <?php endif;?>

                    <span>Contato: Syem Tech</span>
</div>
</div>
</div>
<div class="landing-footer-bottom">
<div>
<strong>Desenvolvido pela Syem Tech</strong>
<span>
                    Matheus Nunes · Samuel Dias · Yago Souza · Emilly Lays
                </span>
</div>
<span>© 2026 CashPilot</span>
</div>
</div>
</footer>
<script src="assets/js/landing.js?v=13.0">

</script>
</body>
</html>
