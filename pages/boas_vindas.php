<?php

require_once __DIR__ . '/../includes/auth.php';

exigirLogin();

$empreendedor = usuarioLogadoTipo() === 'mei';
$primeiroNome = explode(' ', trim(usuarioLogadoNome()))[0] ?? 'usuário';
?>
<!DOCTYPE html>
<html lang="pt-br" data-theme="<?= limpar($_SESSION['tema_preferido'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem-vindo · CashPilot</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=11.4.1">
<link rel="stylesheet" href="../assets/css/components.css?v=13.4">
<link rel="stylesheet" href="../assets/css/theme.css?v=13.4.1">
</head>
<body class="welcome-body">
<main class="welcome-shell">
    <section class="welcome-card">
        <div class="welcome-brand">
            <img src="../assets/img/logo-cashpilot-escura.png" alt="CashPilot">
        </div>

        <div class="welcome-progress">
            <span class="ativo" data-welcome-dot="0"></span>
            <span data-welcome-dot="1"></span>
            <span data-welcome-dot="2"></span>
            <span data-welcome-dot="3"></span>
            <span data-welcome-dot="4"></span>
        </div>

        <div class="welcome-slide ativo" data-welcome-slide="0">
            <span class="eyebrow">BEM-VINDO AO CASHPILOT</span>
            <h1>Olá, <?= limpar($primeiroNome) ?>.</h1>
            <p>
                O CashPilot transforma seus registros financeiros em uma visão
                mais clara do que está acontecendo e do que merece atenção.
            </p>
        </div>

        <div class="welcome-slide" data-welcome-slide="1">
            <span class="eyebrow">1 · DASHBOARD</span>
            <h1>Comece pela visão geral.</h1>
            <p>
                Seu Dashboard resume os principais indicadores.
                <?= $empreendedor
                    ? 'No perfil Empreendedor, os destaques também consideram o segmento e a prioridade do negócio.'
                    : 'No perfil Pessoa Física, ele conecta gastos, orçamento, metas e saúde financeira.' ?>
            </p>
        </div>

        <div class="welcome-slide" data-welcome-slide="2">
            <span class="eyebrow">2 · MOVIMENTAÇÕES</span>
            <h1>Registre ou importe seus dados.</h1>
            <p>
                Receitas e despesas alimentam todo o restante do sistema.
                Quanto melhor os dados estiverem classificados, melhores serão
                o RadarPilot, os relatórios e o Copiloto.
            </p>
        </div>

        <div class="welcome-slide" data-welcome-slide="3">
            <span class="eyebrow">3 · RADARPILOT</span>
            <h1>Veja o que merece atenção.</h1>
            <p>
                O RadarPilot identifica mudanças, riscos e pontos positivos
                usando os dados registrados no CashPilot.
            </p>
        </div>

        <div class="welcome-slide" data-welcome-slide="4">
            <span class="eyebrow">4 · COPILOTO</span>
            <h1>Transforme dados em explicações.</h1>
            <p>
                Pergunte sobre sua situação financeira ou sobre o negócio.
                O Copiloto usa os dados disponíveis no CashPilot como contexto
                e informa quando falta informação.
            </p>
        </div>

        <div class="welcome-actions">
            <button
                type="button"
                class="btn btn-secundario"
                id="welcomePular"
            >
                Pular apresentação
            </button>

            <div>
                <button
                    type="button"
                    class="btn btn-secundario"
                    id="welcomeVoltar"
                    hidden
                >
                    Voltar
                </button>

                <button
                    type="button"
                    class="btn btn-primario"
                    id="welcomeAvancar"
                >
                    Continuar
                </button>
            </div>
        </div>

        <form
            action="../actions/preferencias.php"
            method="POST"
            id="welcomeFinalizar"
            hidden
        >
            <?= csrfCampo() ?>
            <input type="hidden" name="acao" value="concluir_onboarding">
        </form>
    </section>
</main>

<script>
const slides = [...document.querySelectorAll('[data-welcome-slide]')];

const dots = [...document.querySelectorAll('[data-welcome-dot]')];

const voltar = document.getElementById('welcomeVoltar');

const avancar = document.getElementById('welcomeAvancar');

const pular = document.getElementById('welcomePular');

const finalizar = document.getElementById('welcomeFinalizar');


let atual = 0;


function renderizar() {

    slides.forEach((slide, indice) => {
        slide.classList.toggle('ativo', indice === atual);
    });


    dots.forEach((dot, indice) => {
        dot.classList.toggle('ativo', indice <= atual);
    });


    voltar.hidden = atual === 0;

    avancar.textContent =
        atual === slides.length - 1
            ? 'Entrar no CashPilot'
            : 'Continuar';

}


function concluir() {

    finalizar.submit();

}


avancar.addEventListener('click', () => {
    if (atual === slides.length - 1) {
        concluir();
        return;
    }

    atual += 1;
    renderizar();
});


voltar.addEventListener('click', () => {
    atual = Math.max(0, atual - 1);
    renderizar();
});


pular.addEventListener('click', concluir);


renderizar();
</script>
<script src="../assets/js/interface.js?v=13.4">

</script>
<script src="../assets/js/ui-controls.js?v=13.4.1">

</script>
</body>
</html>
