<?php
require_once __DIR__ . "/includes/auth.php";

$usuarioLogado = !empty($_SESSION["usuario_id"]);
$destinoPrincipal = $usuarioLogado
    ? "pages/dashboard.php"
    : "pages/cadastro.php";
$textoPrincipal = $usuarioLogado ? "Abrir meu painel" : "Criar minha conta";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Organize suas finanças pessoais e do pequeno negócio com o CashPilot. Planejamento, RadarPilot e Copiloto para entender seu dinheiro.">
    <meta name="theme-color" content="#122e2b">
    <title>CashPilot | Seu dinheiro, com mais direção</title>
    <link rel="icon" href="assets/icons/favicon.ico">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <link rel="stylesheet" href="assets/css/landing16_7.css?v=16.7.0.2">
    <script src="assets/js/landing16_7.js?v=16.7.0.2" defer></script>
</head>
<body>
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <header class="site-header">
        <div class="shell header-inner">
            <a class="brand" href="#inicio" aria-label="CashPilot, início">
                <img src="assets/img/logo-cashpilot-v13.png" alt="CashPilot" width="128" height="44">
            </a>
        </div>
    </header>

    <main id="conteudo">
        <section class="hero dark" id="inicio" aria-labelledby="hero-title">
            <div class="hero-orbit" aria-hidden="true"></div>
            <div class="shell hero-layout">
            <div class="hero-copy">
                <p class="eyebrow"><span class="status-dot" aria-hidden="true"></span> SEU COPILOTO FINANCEIRO
                </p>
                <h1 id="hero-title">Seu dinheiro.<br><span>Mais direção.</span></h1>
                <p class="hero-description">Da sua rotina ao seu pequeno negócio: organize o presente, entenda
                    seus números e planeje o próximo passo.</p>
                <div class="actions">
                    <a class="button mint" href="<?= limpar($destinoPrincipal) ?>"><?= limpar($textoPrincipal) ?> <span
                            aria-hidden="true">↗</span></a>
                    <a class="text-link" href="#organizar">Conhecer o CashPilot <span
                            aria-hidden="true">↓</span></a>
                </div>
                <?php if (!$usuarioLogado): ?>
                <p class="login-prompt">Já tem conta? <a href="pages/login.php">Entrar</a></p>
                <?php endif; ?>
                <p class="hero-note">Finanças pessoais e MEI, com uma visão mais clara.</p>
            </div>
            <figure class="hero-product reveal">
                <div class="window-bar"><span><i></i><i></i><i></i></span><span>CashPilot / Visão
                        financeira</span><span class="window-tag">O SEU PONTO DE PARTIDA</span></div>
<div class="product-screen dashboard-frame">
                    <img src="assets/img/landing/dashboard.png"
                        alt="Dashboard real do CashPilot com saldo, receitas, despesas, gráfico de evolução e RadarPilot."
                        width="1908" height="989" fetchpriority="high">
                </div>
                <figcaption>Tela real do CashPilot · Dados fictícios de demonstração
                </figcaption>
            </figure>
            </div>
        </section>

        <section class="section paper" id="organizar">
            <div class="shell">
                <div class="section-heading reveal">
                    <p class="eyebrow">SEU MÊS, À VISTA</p>
                    <h2>O que entrou, o que saiu<br>e o que ficou.</h2>
                    <p>Receitas, despesas e contas deixam de ser informações soltas. Acompanhe o que entrou, o
                        que saiu e como o mês está evoluindo.</p>
                </div>
                <figure class="finance-story reveal">
                    <div class="finance-annotation">
                        <span>ENTRADAS E SAÍDAS</span>
                        <p>Compare receitas e despesas no mesmo período.</p>
                    </div>
                    <div class="summary-image">
                        <img src="assets/img/landing/dashboard.png" width="1908" height="989"
                            alt="Recorte real do resumo do mês com receitas, despesas e resultado." loading="lazy">
                    </div>
                    <figcaption>Resumo real do sistema · Dados fictícios de demonstração</figcaption>
                    <div class="finance-bottom">
                        <p><strong>Um lançamento de cada vez.</strong> Registre manualmente ou importe seu CSV.</p>
                        <p><strong>O mês inteiro em perspectiva.</strong> Consulte o histórico e acompanhe a evolução.</p>
                    </div>
                </figure>
            </div>
        </section>

        <section class="section sage" id="planejar">
            <div class="shell split">
                <div class="section-heading reveal">
                    <p class="eyebrow">ANTES DE GASTAR, PLANEJE</p>
                    <h2>Quanto cabe em cada<br>parte do seu mês?</h2>
                    <p>Defina limites por categoria, organize compromissos e acompanhe o planejado e o
                        realizado. Dê espaço às suas metas dentro da sua realidade.</p>
                    <div class="planning-note">
                        <strong>Do limite à realidade.</strong>
                        <p>Defina quanto pretende gastar em cada categoria e confira o que já aconteceu.
                            Seus compromissos e suas metas entram nessa mesma conversa.</p>
                    </div>
                    <a class="inline-link" href="<?= limpar($destinoPrincipal) ?>">Começar a organizar <span
                            aria-hidden="true">↗</span></a>
                </div>
                <figure class="product-figure reveal">
                    <div class="figure-label"><span>PLANEJADO × REALIZADO</span><span
                            aria-hidden="true">↗</span></div>
<div class="product-screen cropped-screen">
                        <img src="assets/img/landing/planejamento.png"
                            alt="Planejamento mensal real, com campos de receita esperada, gastos fixos e limites por categoria."
                            width="1902" height="990" loading="lazy">
                    </div>
                    <figcaption>Tela real · Planejamento de demonstração parcialmente preenchido</figcaption>
                </figure>
            </div>
        </section>

        <section class="section dark intelligence" id="radar">
            <div class="shell">
                <div class="section-heading reveal">
                    <p class="eyebrow">PERCEBER É O PRIMEIRO PASSO</p>
                    <h2>Seu gasto mudou.<br><span class="mint-text">Entenda o que aconteceu.</span>
                    </h2>
                    <p>RadarPilot e Copiloto trabalham com perspectivas diferentes sobre suas finanças: um
                        destaca mudanças; o outro ajuda a explorá-las.</p>
                </div>
                <div class="intelligence-grid">
                    <article class="intelligence-card reveal">
                        <div class="feature-top"><span class="eyebrow">RADARPILOT</span><span
                                class="radar-symbol" aria-hidden="true">◎</span></div>
                        <h3>O que mudou no seu mês?</h3>
                        <p>Encontre alertas sobre a evolução dos gastos e a concentração das despesas. Veja o
                            impacto e investigue os dados.</p>
<div class="product-screen cropped-screen radar-screen">
                            <img src="assets/img/landing/radar.png"
                                alt="RadarPilot com alertas de despesas e concentração por categoria usando dados fictícios."
                                width="1902" height="990" loading="lazy">
                        </div>
                    </article>
                    <article class="intelligence-card reveal" id="copiloto">
                        <div class="feature-top"><span class="eyebrow">COPILOTO</span><svg class="spark"
                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                                aria-hidden="true">
                                <path d="m9 3 1.8 5.2L16 10l-5.2 1.8L9 17l-1.8-5.2L2 10l5.2-1.8L9 3Z" />
                                <path d="m18 14 1.1 3.1L22 18l-2.9 1L18 22l-1-3-3-1 3-.9L18 14Z" />
                            </svg></div>
                        <h3>Pergunte sobre seus números.</h3>
                        <p>Converse sobre gastos, orçamento e metas com o contexto registrado no CashPilot. Um
                            apoio para compreender suas finanças.</p>
<div class="product-screen copilot-screen">
                            <img src="assets/img/landing/copiloto.png"
                                alt="Tela inicial do Copiloto com sugestões de perguntas sobre gastos, receitas e despesas."
                                width="1919" height="990" loading="lazy">
                        </div>
                    </article>
                </div>
                <p class="fine-print">Capturas reais com dados de teste. O Copiloto pode cometer erros;
                    confira informações importantes. O CashPilot não substitui orientação profissional.</p>
            </div>
        </section>

        <section class="section paper" id="negocio">
            <div class="shell split business">
                <figure class="product-figure reveal">
                    <div class="figure-label"><span>VISÃO DO NEGÓCIO</span><span aria-hidden="true">↗</span>
                    </div>
<div class="product-screen cropped-screen">
                        <img src="assets/img/landing/negocio.png"
                            alt="Visão de um negócio fictício no CashPilot com vendas, margem bruta e ferramentas de simulação."
                            width="1904" height="990" loading="lazy">
                    </div>
                    <figcaption>Padaria Roma · Negócio fictício utilizado nos testes</figcaption>
                </figure>
                <div class="section-heading reveal">
                    <p class="eyebrow">PARA QUEM EMPREENDE</p>
                    <h2>Seu negócio tem ritmo.<br>Acompanhe de perto.</h2>
                    <p>Reúna vendas, produtos, estoque e custos em uma visão do seu pequeno negócio. Entenda
                        os resultados e use simulações para apoiar o planejamento.</p>
                    <div class="business-tags"><span>Vendas</span><span>Produtos e estoque</span><span>Custos
                            e equipe</span><span>Desempenho</span></div>
                    <a class="inline-link" href="<?= limpar($destinoPrincipal) ?>">Organizar meu negócio <span
                            aria-hidden="true">↗</span></a>
                </div>
            </div>
        </section>

        <section class="section mobile-section sage">
            <div class="shell mobile-layout">
                <div class="section-heading reveal">
                    <p class="eyebrow">NO SEU TEMPO. NA SUA TELA.</p>
                    <h2>Uma pausa na rotina.<br>Uma olhada nas finanças.</h2>
                    <p>Acesse pelo navegador do celular ou do computador. Do resumo do mês ao planejamento,
                        leve a organização para onde sua rotina acontece.</p>
                    <div class="steps">
                        <div><span>1</span>
                            <p><strong>Crie sua conta</strong>Escolha o perfil para sua rotina pessoal ou seu
                                negócio.</p>
                        </div>
                        <div><span>2</span>
                            <p><strong>Adicione suas movimentações</strong>Registre manualmente ou importe um
                                arquivo CSV.</p>
                        </div>
                        <div><span>3</span>
                            <p><strong>Acompanhe e planeje</strong>Explore seu painel e defina os próximos
                                passos.</p>
                        </div>
                    </div>
                </div>
                <figure class="mobile-product reveal">
                    <div class="phone"><img src="assets/img/landing/mobile.png"
                            alt="Dashboard real do CashPilot adaptado para celular, com navegação inferior."
                            width="387" height="844" loading="lazy"></div>
                    <figcaption>CashPilot no navegador do celular · Dados de teste</figcaption>
                </figure>
            </div>
        </section>

        <section class="section paper" id="duvidas">
            <div class="shell faq-layout">
                <div class="section-heading reveal">
                    <p class="eyebrow">ANTES DO PRIMEIRO PASSO</p>
                    <h2>Vamos esclarecer?</h2>
                    <p>Algumas respostas para conhecer melhor o CashPilot.</p>
                </div>
                <div class="faq">
                    <details>
                        <summary>Posso usar para minhas finanças pessoais?</summary>
                        <p>Sim. O perfil pessoal reúne contas, receitas, despesas, metas, orçamentos e
                            planejamento para acompanhar sua organização financeira.</p>
                    </details>
                    <details>
                        <summary>O que muda para MEI e pequenos negócios?</summary>
                        <p>O perfil de negócio inclui ferramentas para vendas, produtos, estoque,
                            fornecedores, equipe e custos, além da visão financeira. O sistema apoia a gestão
                            e não substitui o contador.</p>
                    </details>
                    <details>
                        <summary>Como coloco minhas movimentações no sistema?</summary>
                        <p>Você pode cadastrar receitas e despesas manualmente ou importar um CSV. A
                            importação apresenta os dados para revisão antes de salvar.</p>
                    </details>
                    <details>
                        <summary>Já posso conectar uma conta bancária real?</summary>
                        <p>A integração com a Pluggy está em fase de validação. O acesso a instituições reais
                            depende da liberação do provedor e da homologação. Enquanto isso, use o cadastro
                            manual e a importação CSV.</p>
                    </details>
                    <details>
                        <summary>Preciso instalar um aplicativo?</summary>
                        <p>Não. O CashPilot é uma plataforma web, acessível pelo navegador do computador,
                            tablet ou celular.</p>
                    </details>
                    <details>
                        <summary>O Copiloto toma decisões por mim?</summary>
                        <p>Não. Ele ajuda a interpretar informações registradas no sistema. Você continua
                            responsável pelas decisões e deve conferir as respostas, pois a inteligência
                            artificial pode cometer erros.</p>
                    </details>
                </div>
            </div>
        </section>

        <section class="closing dark">
            <div class="shell closing-layout reveal">
                <div class="closing-copy">
                    <p class="eyebrow">COMECE PELO QUE VOCÊ JÁ TEM</p>
                    <h2>Seu próximo mês não precisa<br>começar do zero.</h2>
                    <p>Uma conta, um lançamento ou um extrato CSV. Traga suas movimentações para o
                        CashPilot e transforme o histórico em um ponto de partida para planejar.</p>
                    <a class="button mint" href="<?= limpar($destinoPrincipal) ?>">
                        <?= limpar($textoPrincipal) ?> <span aria-hidden="true">↗</span>
                    </a>
                    <?php if (!$usuarioLogado): ?>
                    <p class="login-prompt">Já tem conta? <a href="pages/login.php">Entrar</a></p>
                    <?php endif; ?>
                </div>
                <div class="closing-route" aria-label="Organização financeira no CashPilot">
                    <span class="route-label">SEU DINHEIRO, COM CONTEXTO</span>
                    <p>Registre.<span>O que faz parte da sua rotina.</span></p>
                    <p>Entenda.<span>Como seu mês está evoluindo.</span></p>
                    <p>Planeje.<span>O próximo passo que cabe na sua vida.</span></p>
                </div>
            </div>
        </section>
    </main>
    <footer class="site-footer dark">
        <div class="shell footer-top">
            <a class="brand" href="#inicio" aria-label="Voltar ao início"><img
                    src="assets/img/logo-cashpilot-v13.png" alt="CashPilot" width="128" height="44"
                    loading="lazy"></a>
            <p>Um copiloto para suas finanças.<br>Um projeto desenvolvido para aprender e construir.</p>
            <nav aria-label="Links do rodapé"><a href="#organizar">Conheça o sistema</a><a
                    href="pages/login.php">Entrar</a><a href="#duvidas">Dúvidas frequentes</a></nav>
        </div>
        <div class="shell footer-bottom"><span>ETEC Jorge Street · Técnico em Desenvolvimento de Sistemas ·
                TCC 2026</span><span>CashPilot</span></div>
    </footer>
</body>
</html>
