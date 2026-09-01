<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../includes/google_auth.php';


redirecionarSeLogado();


$erro = $_SESSION['erro_cadastro'] ?? null;

$dados = $_SESSION['dados_cadastro'] ?? [];


unset($_SESSION['erro_cadastro'], $_SESSION['dados_cadastro']);


$googlePending = $_SESSION['google_pending'] ?? null;

$googleClientId = cpGoogleClientId();

if ($googlePending) {

    $dados['nome'] = $googlePending['nome'] ?? ($dados['nome'] ?? '');

    $dados['email'] = $googlePending['email'] ?? ($dados['email'] ?? '');
}?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Criar conta · CashPilot</title>
<link rel="stylesheet" href="../assets/css/style.css?v=13.0">
<link rel="stylesheet" href="../assets/css/components.css?v=13.4">
<link rel="stylesheet" href="../assets/css/theme.css?v=13.4.1">
</head>
<body class="auth-body-transition cp-auth-light">
<div class="auth-tela auth-transition-page auth-cadastro-page">
<main class="auth-transition-card auth-cadastro-state" id="authTransitionCard">
<aside class="auth-transition-brand">
<div class="auth-brand-orbit auth-brand-orbit-1">
</div>
<div class="auth-brand-orbit auth-brand-orbit-2">
</div>
<div class="auth-brand-content">
<img
                    src="../assets/img/logo-cashpilot-v13.png"
                    class="auth-transition-logo"
                    alt="CashPilot"
                >
<span class="auth-brand-kicker">BOM TER VOCÊ AQUI</span>
<h2>Já possui uma conta?</h2>
<p>
                    Seus dados e sua organização financeira continuam exatamente
                    onde você deixou.
                </p>
<a
                    href="login.php"
                    class="auth-brand-action auth-switch-link"
                >
<span>←</span> Voltar para entrar
                </a>
<small>Seu CashPilot acompanha você em cada etapa.</small>
</div>
</aside>
<section class="auth-transition-form auth-transition-form-cadastro">
<div class="auth-form-inner">
<div class="auth-form-brand-mobile">
<img src="../assets/img/logo-cashpilot-escura.png" alt="CashPilot">
</div>
<div class="cadastro-progresso auth-progress-clean">
<span class="ativo" data-step-dot="1">1</span>
<i>
</i>
<span data-step-dot="2">2</span>
</div>
<div class="auth-titulo auth-transition-title">
<span class="auth-kicker">COMECE EM POUCOS PASSOS</span>
<h1>Criar sua conta</h1>
<p>As respostas ajudam o CashPilot a adaptar sua experiência.</p>
</div>

                <?php if ($erro):?>
                    <div class="alerta-mensagem erro">
                        <?= limpar($erro)?>
                    </div>
                <?php endif;?>

                <?php if (!$googlePending):?>
                    <div class="cp-register-social">
                        <?php if ($googleClientId !== ''):?>
                            <div id="g_id_onload" data-client_id="<?= limpar($googleClientId)?>" data-callback="cashPilotGoogleCredentialCadastro" data-auto_prompt="false">
</div>
<div class="g_id_signin" data-type="standard" data-theme="outline" data-size="large" data-text="continue_with" data-shape="rectangular" data-logo_alignment="left" data-width="330">
</div>
                        <?php else:?>
                            <button type="button" class="cp-google-placeholder" onclick="alert('Configure GOOGLE_CLIENT_ID para ativar o cadastro com Google neste computador.')">
<img class="cp-google-icon" src="../assets/img/google-g.svg" alt=""> Continuar com Google</button>
                        <?php endif;?>
                        <div class="auth-social-divider">
<span>ou continue preenchendo</span>
</div>
</div>
                <?php else:?>
                    <div class="cp-google-connected">
<img class="cp-google-icon" src="../assets/img/google-g.svg" alt="">
<div>
<strong>Conta Google confirmada</strong>
<small><?= limpar($googlePending['email'] ?? '')?></small>
</div>
</div>
                <?php endif;?>

                <form
                    action="../actions/cadastro.php"
                    method="POST"
                    autocomplete="off"
                    id="cadastroForm"
                >
                    <?= csrfCampo()?>

                    <div class="cadastro-etapa" data-step="1">
<div class="form-grupo">
<label for="nome">Nome completo</label>
<input
                                type="text"
                                id="nome"
                                name="nome"
                                required
                                value="<?= limpar($dados['nome'] ?? '')?>" <?= $googlePending ? 'readonly' : ''?>
                            >
</div>
<div class="form-grupo">
<label for="username">Nome de usuário</label>
<div class="username-input-wrap">
<span>@</span>
<input
                                    type="text"
                                    id="username"
                                    name="username"
                                    minlength="3"
                                    maxlength="20"
                                    pattern="[A-Za-z0-9._]{3,20}"
                                    required
                                    value="<?= limpar($dados['username'] ?? '')?>"
                                    placeholder="seu.usuario"
                                >
</div>
<small class="username-status" id="usernameStatus">
                                Use 3 a 20 caracteres: letras, números, ponto ou _.
                            </small>
</div>
<div class="form-linha">
<div class="form-grupo">
<label for="email">E-mail</label>
<input
                                    type="email"
                                    id="email"
                                    name="email"
                                    required
                                    value="<?= limpar($dados['email'] ?? '')?>" <?= $googlePending ? 'readonly' : ''?>
                                >
</div>
<div class="form-grupo">
<label for="telefone">Telefone</label>
<input
                                    type="tel"
                                    inputmode="numeric"
                                    maxlength="15"
                                    id="telefone"
                                    name="telefone"
                                    placeholder="(11) 94275-3234"
                                    value="<?= limpar($dados['telefone'] ?? '')?>"
                                >
</div>
</div>
<div class="form-grupo" <?= $googlePending ? 'hidden' : ''?>>
<label for="senha">Senha</label>
<div class="auth-input-wrap auth-input-wrap-sem-icone">
<input
                                    type="password"
                                    id="senha"
                                    name="senha"
                                    <?= $googlePending ? '' : 'required'?>
                                    minlength="6"
                                >
<button
                                    type="button"
                                    class="auth-password-toggle"
                                    id="toggleSenhaCadastro"
                                    aria-label="Mostrar senha"
                                >
<svg class="cp-eye cp-eye-open" viewBox="0 0 24 24" aria-hidden="true">
<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/>
<circle cx="12" cy="12" r="2.6"/>
</svg>
<svg class="cp-eye cp-eye-off" viewBox="0 0 24 24" aria-hidden="true">
<path d="M3 3l18 18"/>
<path d="M10.6 6.2A9.9 9.9 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-2.1 2.8"/>
<path d="M6.2 6.2C3.8 7.8 2.5 12 2.5 12s3.5 6 9.5 6a9.8 9.8 0 0 0 4.1-.9"/>
<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>
</svg>
</button>
</div>
</div>
<div class="form-grupo">
<label for="tipo_perfil">Como você quer usar o CashPilot?</label>
<select id="tipo_perfil" name="tipo_perfil">
<option value="pessoa_fisica">
                                    Organizar minha vida financeira
                                </option>
<option value="mei">
                                    Administrar meu negócio / empreendimento
                                </option>
</select>
</div>
<button
                            type="button"
                            class="btn btn-primario btn-bloco auth-main-button"
                            id="avancarCadastro"
                        >
                            Continuar
                        </button>
</div>
<div class="cadastro-etapa" data-step="2" hidden>
<div id="etapaPessoaFisica">
<span class="eyebrow">PESSOA FÍSICA</span>
<h3>Personalize seu início</h3>
<p class="secao-ajuda cadastro-intro">
                                O CashPilot usa esse objetivo apenas para dar mais
                                contexto ao seu painel e às orientações.
                            </p>
<div class="form-grupo">
<label for="objetivo_pessoal">Seu principal objetivo</label>
<select id="objetivo_pessoal" name="objetivo_pessoal">
<option value="Organizar meus gastos">Organizar meus gastos</option>
<option value="Economizar mais">Economizar mais</option>
<option value="Alcançar uma meta">Alcançar uma meta</option>
<option value="Reduzir dívidas">Reduzir dívidas</option>
<option value="Entender melhor meu dinheiro">Entender melhor meu dinheiro</option>
</select>
</div>
<div class="form-grupo">
<label for="limite_gastos_mensal">
                                    Limite mensal de gastos (opcional)
                                </label>
<input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    id="limite_gastos_mensal"
                                    name="limite_gastos_mensal"
                                    placeholder="Ex.: 2500,00"
                                >
</div>
</div>
<div id="etapaEmpreendedor" hidden>
<span class="eyebrow">ÁREA DO EMPREENDEDOR</span>
<h3>Vamos entender seu negócio</h3>
<p class="secao-ajuda cadastro-intro">
                                Essas respostas ajudam o Dashboard, RadarPilot,
                                Copiloto e Aprender a dar mais destaque ao que
                                faz sentido para o seu negócio.
                            </p>
<div class="form-grupo">
<label for="nome_negocio">Nome do negócio</label>
<input
                                    type="text"
                                    id="nome_negocio"
                                    name="nome_negocio"
                                    placeholder="Ex.: Barbearia Central"
                                >
</div>
<div class="form-grupo">
<label for="nicho">Qual é o segmento principal?</label>
<select id="nicho" name="nicho">
<option value="">Selecione</option>
<option value="Academia / Fitness">Academia / Fitness</option>
<option value="Alimentação / Restaurante">Alimentação / Restaurante</option>
<option value="Artesanato / Produtos personalizados">Artesanato / Produtos personalizados</option>
<option value="Assistência técnica">Assistência técnica</option>
<option value="Barbearia">Barbearia</option>
<option value="Beleza / Estética">Beleza / Estética</option>
<option value="Comércio / Loja física">Comércio / Loja física</option>
<option value="Comércio eletrônico / E-commerce">Comércio eletrônico / E-commerce</option>
<option value="Construção / Reformas">Construção / Reformas</option>
<option value="Consultoria">Consultoria</option>
<option value="Educação / Cursos">Educação / Cursos</option>
<option value="Eventos / Festas">Eventos / Festas</option>
<option value="Fotografia / Audiovisual">Fotografia / Audiovisual</option>
<option value="Manutenção / Reparos">Manutenção / Reparos</option>
<option value="Marketing / Design">Marketing / Design</option>
<option value="Oficina / Automotivo">Oficina / Automotivo</option>
<option value="Pet shop / Serviços para pets">Pet shop / Serviços para pets</option>
<option value="Profissional autônomo">Profissional autônomo</option>
<option value="Saúde / Bem-estar">Saúde / Bem-estar</option>
<option value="Serviços de limpeza">Serviços de limpeza</option>
<option value="Tecnologia / Software">Tecnologia / Software</option>
<option value="Transporte / Logística">Transporte / Logística</option>
<option value="Turismo / Hospedagem">Turismo / Hospedagem</option>
<option value="Outro">Outro</option>
</select>
</div>
<div
                                class="form-grupo cadastro-campo-expandido"
                                id="grupoNichoOutro"
                                hidden
                            >
<label for="nicho_outro">Qual é o seu segmento?</label>
<input
                                    type="text"
                                    id="nicho_outro"
                                    name="nicho_outro"
                                    maxlength="80"
                                    placeholder="Digite o segmento do seu negócio"
                                >
</div>
<div class="form-linha">
<div class="form-grupo">
<label for="oferta">O que o negócio oferece?</label>
<select id="oferta" name="oferta">
<option value="servicos">Principalmente serviços</option>
<option value="produtos">Principalmente produtos</option>
<option value="ambos">Produtos e serviços</option>
</select>
</div>
<div class="form-grupo">
<label for="operacao">Como você atende seus clientes?</label>
<select id="operacao" name="operacao">
<option value="presencial">Presencialmente</option>
<option value="online">Online</option>
<option value="domicilio">Delivery / domicílio</option>
<option value="hibrido">Modelo híbrido</option>
</select>
</div>
</div>
<div class="form-grupo">
<label for="publico_alvo">Quem é o seu público principal?</label>
<select id="publico_alvo" name="publico_alvo">
<option value="Público geral">Público geral</option>
<option value="Empresas / B2B">Empresas / B2B</option>
<option value="Famílias">Famílias</option>
<option value="Crianças e responsáveis">Crianças e responsáveis</option>
<option value="Jovens e adolescentes">Jovens e adolescentes</option>
<option value="Adultos">Adultos</option>
<option value="Principalmente homens">Principalmente homens</option>
<option value="Principalmente mulheres">Principalmente mulheres</option>
<option value="Público de maior poder aquisitivo">Público de maior poder aquisitivo</option>
<option value="Público local / regional">Público local / regional</option>
</select>
</div>
<div class="form-linha">
<div class="form-grupo">
<label for="canal_principal">Principal canal de vendas</label>
<select id="canal_principal" name="canal_principal">
<option>Atendimento presencial</option>
<option>WhatsApp</option>
<option>Instagram / redes sociais</option>
<option>Site / e-commerce</option>
<option>Marketplaces</option>
<option>Indicações</option>
<option>Delivery / aplicativos</option>
</select>
</div>
<div class="form-grupo">
<label for="objetivo_principal">
                                        Qual é a prioridade atual do negócio?
                                    </label>
<select id="objetivo_principal" name="objetivo_principal">
<option>Aumentar vendas e faturamento</option>
<option>Melhorar lucro e margem</option>
<option>Organizar despesas e custos</option>
<option>Melhorar o fluxo de caixa</option>
<option>Controlar estoque</option>
<option>Organizar equipe</option>
<option>Fidelizar e aumentar clientes</option>
<option>Ganhar previsibilidade</option>
<option>Entender melhor meu negócio</option>
</select>
</div>
</div>
<div class="questionario-nota">
                                Você poderá alterar a prioridade depois.
                                O segmento principal permanece bloqueado para
                                manter a personalização coerente.
                            </div>
</div>
<div class="cadastro-acoes">
<button
                                type="button"
                                class="btn btn-secundario"
                                id="voltarCadastro"
                            >
                                Voltar
                            </button>
<button type="submit" class="btn btn-primario">
                                Criar minha conta
                            </button>
</div>
</div>
</form>
<p class="auth-rodape auth-transition-footer">
                    Já tem conta?
                    <a href="login.php" class="auth-switch-link">
                        Entrar
                    </a>
</p>
</div>
</section>
</main>
</div>
<script>
const campoUsername = document.getElementById('username');

const usernameStatus = document.getElementById('usernameStatus');

const telefoneCadastro = document.getElementById('telefone');

const senhaCadastro = document.getElementById('senha');

const toggleSenhaCadastro = document.getElementById('toggleSenhaCadastro');

const tipoPerfil = document.getElementById('tipo_perfil');

const etapa1 = document.querySelector('[data-step="1"]');

const etapa2 = document.querySelector('[data-step="2"]');

const etapaPessoaFisica = document.getElementById('etapaPessoaFisica');

const etapaEmpreendedor = document.getElementById('etapaEmpreendedor');

const dots = document.querySelectorAll('[data-step-dot]');

const nicho = document.getElementById('nicho');

const grupoNichoOutro = document.getElementById('grupoNichoOutro');

const nichoOutro = document.getElementById('nicho_outro');


let timerUsername = null;


function formatarTelefoneBrasil(valor) {

    const numeros = String(valor || '')
        .replace(/\D/g, '')
        .slice(0, 11);


    if (!numeros) return '';

    if (numeros.length <= 2) return `(${numeros}`;

    if (numeros.length <= 6) {

        return `(${numeros.slice(0, 2)}) ${numeros.slice(2)}`;

    }


    if (numeros.length <= 10) {

        return `(${numeros.slice(0, 2)}) ${numeros.slice(2, 6)}-${numeros.slice(6)}`;

    }


    return `(${numeros.slice(0, 2)}) ${numeros.slice(2, 7)}-${numeros.slice(7)}`;

}


function mostrarEtapa(numero) {

    etapa1.hidden = numero !== 1;

    etapa2.hidden = numero !== 2;


    dots.forEach((dot) => {
        dot.classList.toggle(
            'ativo',
            Number(dot.dataset.stepDot) <= numero
        );
    });

}


function adaptarPerfil() {

    const empreendedor = tipoPerfil.value === 'mei';


    etapaPessoaFisica.hidden = empreendedor;

    etapaEmpreendedor.hidden = !empreendedor;


    document.getElementById('nome_negocio').required = empreendedor;

    nicho.required = empreendedor;


    atualizarNichoOutro();

}


function atualizarNichoOutro() {

    const mostrar = tipoPerfil.value === 'mei' && nicho.value === 'Outro';


    grupoNichoOutro.hidden = !mostrar;

    nichoOutro.required = mostrar;


    if (!mostrar) {

        nichoOutro.value = '';

    }

}


campoUsername?.addEventListener('input', () => {
    campoUsername.value = campoUsername.value
        .toLowerCase()
        .replace(/[^a-z0-9._]/g, '')
        .slice(0, 20);

    clearTimeout(timerUsername);

    const username = campoUsername.value;

    usernameStatus.className = 'username-status';

    if (username.length < 3) {
        usernameStatus.textContent = 'Use pelo menos 3 caracteres.';
        return;
    }

    usernameStatus.textContent = 'Verificando disponibilidade...';

    timerUsername = setTimeout(async () => {
        try {
            const resposta = await fetch(
                '../actions/username_disponivel.php?username=' +
                encodeURIComponent(username)
            );

            const dados = await resposta.json();

            usernameStatus.textContent = dados.mensagem || '';
            usernameStatus.className =
                'username-status ' +
                (dados.disponivel ? 'disponivel' : 'indisponivel');
        } catch (erro) {
            usernameStatus.textContent = 'Não foi possível verificar agora.';
        }
    }, 350);
});


telefoneCadastro?.addEventListener('input', () => {
    telefoneCadastro.value = formatarTelefoneBrasil(
        telefoneCadastro.value
    );
});


toggleSenhaCadastro?.addEventListener('click', () => {
    const mostrando = senhaCadastro.type === 'text';

    senhaCadastro.type = mostrando ? 'password' : 'text';
    toggleSenhaCadastro.setAttribute(
        'aria-label',
        mostrando ? 'Mostrar senha' : 'Ocultar senha'
    );
    toggleSenhaCadastro.classList.toggle('ativo', !mostrando);
});


document.getElementById('avancarCadastro').addEventListener('click', () => {
    const obrigatorios = [
        document.getElementById('nome'),
        campoUsername,
        document.getElementById('email'),
        senhaCadastro,
    ];

    if (obrigatorios.some((campo) => !campo.reportValidity())) {
        return;
    }

    adaptarPerfil();
    mostrarEtapa(2);
});


document.getElementById('voltarCadastro').addEventListener('click', () => {
    mostrarEtapa(1);
});


tipoPerfil.addEventListener('change', adaptarPerfil);

nicho.addEventListener('change', atualizarNichoOutro);


adaptarPerfil();


requestAnimationFrame(() => {
    document.body.classList.add('auth-page-ready');
});
</script>
<?php if ($googleClientId !== '' && !$googlePending):?>
<script src="https://accounts.google.com/gsi/client" async defer>

</script>
<script>
function cashPilotGoogleCredentialCadastro(response){

 const form=document.createElement('form');
form.method='POST';
form.action='../actions/google_callback.php';

 const input=document.createElement('input');
input.type='hidden';
input.name='credential';
input.value=response.credential||'';
form.appendChild(input);

    const csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = 'csrf_token';
    csrf.value = <?= json_encode(csrfToken(), JSON_UNESCAPED_SLASHES) ?>;
    form.appendChild(csrf);
document.body.appendChild(form);
form.submit();

}
</script>
<?php endif;?>
<script src="../assets/js/interface.js?v=13.4">

</script>
<script src="../assets/js/ui-controls.js?v=13.4.1">

</script>
</body>
</html>
