<?php
require_once __DIR__ . '/../includes/auth.php';
$tituloPagina = 'Aviso de privacidade';
require_once __DIR__ . '/../includes/header.php';
$contato = (string) getenv('CASHPILOT_PRIVACY_EMAIL');
?>
<main class="surface-card" style="max-width:850px;margin:2rem auto;padding:1.5rem">
    <h1>Privacidade e uso do CashPilot</h1>
    <p>Versão do aviso: 17. Projeto acadêmico do curso de Desenvolvimento de Sistemas
        da ETEC Jorge Street. Este aviso descreve o comportamento implementado;
        não é certificação de conformidade jurídica.</p>
    <h2>Dados e finalidades</h2>
    <p>Usamos os dados de cadastro para autenticação e comunicação de segurança.
        Movimentações, contas, metas e informações do negócio servem para calcular
        saldos, relatórios e planejamento. Não realizamos pagamentos ou transferências.</p>
    <h2>Integrações</h2>
    <ul>
        <li>Google: autenticação opcional e identificação da conta Google.</li>
        <li>Brevo: envio de códigos e mensagens de segurança ao e-mail informado.</li>
        <li>Groq: somente mediante sua autorização, pergunta e resumo financeiro para a IA externa.</li>
        <li>Pluggy: conexão escolhida pelo usuário e obtenção de contas e transações.
            Bancos reais dependem da liberação e homologação do ambiente.</li>
    </ul>
    <p>Provedores externos possuem suas próprias regras de tratamento e podem operar
        fora do Brasil. Antes de abrir o cadastro público, o responsável precisa
        validar contratos, bases legais e transferências internacionais.</p>
    <h2>Seus controles</h2>
    <p>Na página Privacidade você pode baixar os dados vinculados à conta em JSON,
        consultar lançamentos em CSV e excluir a conta. A exportação não inclui hashes de senha,
        códigos de recuperação, credenciais ou registros técnicos internos.
        Solicitações de correção ou esclarecimento devem ser encaminhadas ao responsável.
        O aceite para usar a IA é solicitado dentro da página do Copiloto, antes da primeira conversa.</p>
    <h2>Retenção</h2>
    <p>Dados financeiros permanecem enquanto a conta estiver ativa. A exclusão da conta
        remove os registros relacionados no banco principal. Identificadores técnicos
        de operações permanecem durante a existência da conta para impedir duplicações.
        Contadores de uso expirados são removidos pela rotina de manutenção; não contêm IP ou e-mail em texto puro.</p>
    <p>Conversas inativas há mais de 90 dias são removidas quando o responsável executa
        a rotina documentada de manutenção. Antes disso, o usuário pode excluí-las manualmente.
        O prazo de backups e logs da hospedagem ainda precisa ser definido antes do uso público;
        o CashPilot não promete exclusão imediata de cópias que não controla.</p>
    <h2>Responsável e contato</h2>
    <?php if (filter_var($contato, FILTER_VALIDATE_EMAIL)): ?>
        <p>Envie solicitações para <a href="mailto:<?= limpar($contato) ?>"><?= limpar($contato) ?></a>.</p>
    <?php else: ?>
        <p>O canal oficial ainda não foi configurado. Neste estágio de testes, procure
            a equipe responsável pelo projeto. O cadastro público deve aguardar a definição do canal.</p>
    <?php endif; ?>
    <h2>Limites do serviço</h2>
    <p>O CashPilot auxilia a organização financeira. Não é banco, instituição de pagamento,
        contador nem consultor financeiro. Simulações e respostas da IA podem conter erros;
        confira os dados antes de tomar decisões. A disponibilidade de integrações depende dos provedores.</p>
    <p><a href="<?= usuarioLogadoId() ? 'privacidade.php' : 'login.php' ?>">Voltar ao sistema</a></p>
</main>
    </body>
</html>
