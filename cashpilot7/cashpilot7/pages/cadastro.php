<?php
require_once __DIR__ . '/../includes/auth.php';
redirecionarSeLogado();
$erro = $_SESSION['erro_cadastro'] ?? null;
$dados = $_SESSION['dados_cadastro'] ?? [];
unset($_SESSION['erro_cadastro'], $_SESSION['dados_cadastro']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar conta · CashPilot</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-tela auth-onboarding">
    <aside class="auth-painel-marca auth-painel-clean">
        <img src="../assets/img/logo-cashpilot-escura.png" class="auth-logo-transparente" alt="CashPilot">
        <span class="eyebrow">SEU COPILOTO FINANCEIRO</span>
        <h1>Uma experiência diferente para cada momento financeiro.</h1>
        <p>Pessoa física acompanha a vida financeira. Empreendedor acompanha o negócio com indicadores adaptados ao seu contexto.</p>
        <div class="auth-beneficios"><span>✓ Dados organizados</span><span>✓ Alertas RadarPilot</span><span>✓ Copiloto preparado para IA</span></div>
    </aside>

    <section class="auth-caixa auth-caixa-onboarding">
        <div class="cadastro-progresso"><span class="ativo" data-step-dot="1">1</span><i></i><span data-step-dot="2">2</span></div>
        <div class="auth-titulo"><h2>Criar sua conta</h2><p>Leva menos de dois minutos.</p></div>
        <?php if ($erro): ?><div class="alerta-mensagem erro"><?= limpar($erro) ?></div><?php endif; ?>

        <form action="../actions/cadastro.php" method="POST" autocomplete="off" id="cadastroForm">
            <?= csrfCampo() ?>
            <div class="cadastro-etapa" data-step="1">
                <div class="form-grupo"><label for="nome">Nome completo</label><input autocomplete="off" type="text" id="nome" name="nome" required value="<?= limpar($dados['nome'] ?? '') ?>"></div>
                <div class="form-linha"><div class="form-grupo"><label for="email">E-mail</label><input autocomplete="off" type="email" id="email" name="email" required value="<?= limpar($dados['email'] ?? '') ?>"></div><div class="form-grupo"><label for="telefone">Telefone</label><input autocomplete="off" type="tel" id="telefone" name="telefone" placeholder="(11) 99999-9999" value="<?= limpar($dados['telefone'] ?? '') ?>"></div></div>
                <div class="form-grupo"><label for="senha">Senha</label><input autocomplete="off" type="password" id="senha" name="senha" required minlength="6"></div>
                <div class="form-grupo"><label for="tipo_perfil">Como você quer usar o CashPilot?</label><select id="tipo_perfil" name="tipo_perfil"><option value="pessoa_fisica">Organizar minha vida financeira</option><option value="mei">Administrar meu negócio / empreendimento</option></select></div>
                <button type="button" class="btn btn-primario btn-bloco" id="avancarCadastro">Continuar</button>
            </div>

            <div class="cadastro-etapa" data-step="2" hidden>
                <div id="etapaPessoaFisica">
                    <span class="eyebrow">PESSOA FÍSICA</span><h3>Personalize seu início</h3><p class="secao-ajuda cadastro-intro">Essas informações ajudam o CashPilot a tornar o dashboard mais útil. Você poderá editar o limite depois.</p>
                    <div class="form-grupo"><label for="objetivo_pessoal">Seu principal objetivo</label><select id="objetivo_pessoal" name="objetivo_pessoal"><option value="organizar">Organizar meus gastos</option><option value="economizar">Economizar mais</option><option value="meta">Alcançar uma meta</option><option value="dividas">Reduzir dívidas</option><option value="entender">Entender melhor meu dinheiro</option></select></div>
                    <div class="form-grupo"><label for="limite_gastos_mensal">Limite mensal de gastos (opcional)</label><input autocomplete="off" type="number" step="0.01" min="0" id="limite_gastos_mensal" name="limite_gastos_mensal" placeholder="Ex: 2500,00"></div>
                </div>

                <div id="etapaEmpreendedor" hidden>
                    <span class="eyebrow">ÁREA DO EMPREENDEDOR</span><h3>Conte um pouco sobre o negócio</h3><p class="secao-ajuda cadastro-intro">O nicho define o tratamento do dashboard e não poderá ser alterado depois da criação da conta.</p>
                    <div class="form-grupo"><label for="nome_negocio">Nome do negócio</label><input autocomplete="off" type="text" id="nome_negocio" name="nome_negocio" placeholder="Ex: Barbearia Central"></div>
                    <div class="form-grupo"><label for="nicho">Nicho principal</label><select id="nicho" name="nicho"><option value="">Selecione</option><option value="Barbearia">Barbearia</option><option value="Salão de beleza">Salão de beleza</option><option value="Comércio local">Comércio local</option><option value="Loja online">Loja online</option><option value="Alimentação">Alimentação / Restaurante</option><option value="Prestação de serviços">Prestação de serviços</option><option value="Profissional autônomo">Profissional autônomo</option><option value="Outro">Outro</option></select></div>
                    <div class="form-linha"><div class="form-grupo"><label for="oferta">O que você vende?</label><select id="oferta" name="oferta"><option value="servicos">Serviços</option><option value="produtos">Produtos</option><option value="ambos">Produtos e serviços</option></select></div><div class="form-grupo"><label for="operacao">Como atende/vende?</label><select id="operacao" name="operacao"><option value="presencial">Presencialmente</option><option value="online">Online</option><option value="hibrido">Presencial e online</option></select></div></div>
                    <div class="form-grupo"><label for="publico_alvo">Quem é seu público principal?</label><input autocomplete="off" type="text" id="publico_alvo" name="publico_alvo" placeholder="Ex: homens de 18 a 45 anos da região"></div>
                    <div class="form-linha"><div class="form-grupo"><label for="canal_principal">Principal canal de vendas</label><select id="canal_principal" name="canal_principal"><option>Atendimento presencial</option><option>WhatsApp</option><option>Instagram / redes sociais</option><option>Site / e-commerce</option><option>Marketplaces</option><option>Indicações</option><option>Outro</option></select></div><div class="form-grupo"><label for="objetivo_principal">Foco atual do negócio</label><select id="objetivo_principal" name="objetivo_principal"><option>Crescer faturamento</option><option>Reduzir custos</option><option>Melhorar margem</option><option>Organizar o caixa</option><option>Aumentar clientes</option><option>Ganhar previsibilidade</option></select></div></div>
                    <div class="questionario-nota">Funcionários, produtos e serviços poderão ser cadastrados depois no painel <strong>Negócio</strong>.</div>
                </div>
                <div class="cadastro-acoes"><button type="button" class="btn btn-secundario" id="voltarCadastro">Voltar</button><button type="submit" class="btn btn-primario">Criar minha conta</button></div>
            </div>
        </form>
        <p class="auth-rodape">Já tem conta? <a href="login.php">Entrar</a></p>
    </section>
</div>
<script>
const tipo=document.getElementById('tipo_perfil');
const e1=document.querySelector('[data-step="1"]'),e2=document.querySelector('[data-step="2"]');
const pf=document.getElementById('etapaPessoaFisica'),emp=document.getElementById('etapaEmpreendedor');
const dots=document.querySelectorAll('[data-step-dot]');
function mostrarEtapa(n){e1.hidden=n!==1;e2.hidden=n!==2;dots.forEach(d=>d.classList.toggle('ativo',Number(d.dataset.stepDot)<=n));}
function adaptar(){const mei=tipo.value==='mei';pf.hidden=mei;emp.hidden=!mei;document.getElementById('nicho').required=mei;document.getElementById('nome_negocio').required=mei;}
document.getElementById('avancarCadastro').addEventListener('click',()=>{const obrig=[document.getElementById('nome'),document.getElementById('email'),document.getElementById('senha')];if(obrig.some(i=>!i.reportValidity()))return;adaptar();mostrarEtapa(2);});
document.getElementById('voltarCadastro').addEventListener('click',()=>mostrarEtapa(1));tipo.addEventListener('change',adaptar);adaptar();
</script>
</body></html>
