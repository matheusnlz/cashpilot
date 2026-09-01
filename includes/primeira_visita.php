<?php

if (!isset($pdo)) {
    require_once __DIR__ . '/../database/conexao.php';
    $pdo = conectar();
}

$paginaAtualAjuda = basename($_SERVER['PHP_SELF']);
$usuarioIdAjuda = (int) usuarioLogadoId();

$ajudas = [
    'dashboard.php' => [
        'chave' => 'pagina_dashboard',
        'titulo' => 'Seu ponto de partida',
        'texto' => 'O Dashboard resume o que está acontecendo agora. Use os cards para entender o cenário e abra os detalhes quando precisar investigar.',
    ],
    'transacoes.php' => [
        'chave' => 'pagina_transacoes',
        'titulo' => 'Sua linha do tempo financeira',
        'texto' => 'Use a lista para consultar movimentações e o calendário para enxergar quando entradas e saídas acontecem.',
    ],
    'orcamentos.php' => [
        'chave' => 'pagina_orcamentos',
        'titulo' => 'Planeje antes de gastar',
        'texto' => 'Defina limites por categoria. O RadarPilot e o Copiloto usam esses limites para identificar quando um orçamento está pressionado.',
    ],
    'metas.php' => [
        'chave' => 'pagina_metas',
        'titulo' => 'Transforme objetivos em acompanhamento',
        'texto' => 'Cada meta mostra progresso, ritmo e prazo. O Copiloto pode ajudar a calcular quanto guardar e analisar se o ritmo está adequado.',
    ],
    'financiamentos.php' => [
        'chave' => 'pagina_financiamentos',
        'titulo' => 'Simule antes de decidir',
        'texto' => 'A simulação ajuda a visualizar parcela, juros e custo total. Ela não substitui a proposta oficial de uma instituição financeira.',
    ],
    'saude_financeira.php' => [
        'chave' => 'pagina_saude_financeira',
        'titulo' => 'Entenda seu CashScore',
        'texto' => 'O CashScore resume alguns sinais da sua organização financeira. Veja os fatores da nota em vez de usar apenas o número isolado.',
    ],
    'radar.php' => [
        'chave' => 'pagina_radar',
        'titulo' => 'O RadarPilot mostra o que merece atenção',
        'texto' => 'Alertas vermelhos, amarelos e verdes são gerados a partir dos seus dados. Abra o Copiloto quando quiser transformar um alerta em explicação ou ação.',
    ],
    'copiloto.php' => [
        'chave' => 'pagina_copiloto',
        'titulo' => 'Converse com seus dados',
        'texto' => 'O Copiloto usa informações registradas no CashPilot como contexto. Quando faltar dado, ele deve informar a limitação em vez de inventar.',
    ],
    'negocio.php' => [
        'chave' => 'pagina_negocio',
        'titulo' => 'A estrutura do seu negócio',
        'texto' => 'Aqui você organiza o perfil empresarial e acessa os módulos de vendas, produtos, equipe, fornecedores e custos.',
    ],
    'vendas.php' => [
        'chave' => 'pagina_vendas',
        'titulo' => 'Registre vendas completas',
        'texto' => 'Uma venda pode conter vários produtos ou serviços. Produtos com estoque controlado são baixados automaticamente.',
    ],
    'desempenho.php' => [
        'chave' => 'pagina_desempenho',
        'titulo' => 'Acompanhe desempenho, não só movimentações',
        'texto' => 'Use esta área para analisar ticket médio, margem, produtos, fornecedores, estrutura de custos e projeção de caixa.',
    ],
];

$ajudaAtual = $ajudas[$paginaAtualAjuda] ?? null;
$mostrarAjudaAtual = false;

if ($ajudaAtual) {
    try {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM usuario_apresentacoes
             WHERE usuario_id = :uid
               AND chave = :chave
             LIMIT 1'
        );

        $stmt->execute([
            'uid' => $usuarioIdAjuda,
            'chave' => $ajudaAtual['chave'],
        ]);

        $mostrarAjudaAtual = !$stmt->fetchColumn();
    } catch (Throwable $e) {
        $mostrarAjudaAtual = false;
    }
}
?>

<?php if ($mostrarAjudaAtual): ?>
    <section
        class="first-visit-tip"
        id="firstVisitTip"
        data-chave="<?= limpar($ajudaAtual['chave']) ?>"
    >
        <div class="first-visit-icon">✦</div>

        <div class="first-visit-copy">
            <span class="eyebrow">PRIMEIRA VEZ AQUI?</span>
            <h2><?= limpar($ajudaAtual['titulo']) ?></h2>
            <p><?= limpar($ajudaAtual['texto']) ?></p>
        </div>

        <button
            type="button"
            class="btn btn-secundario"
            id="firstVisitOk"
        >
            Entendi
        </button>
    </section>

    <script>
document.addEventListener('DOMContentLoaded', () => {
        const card = document.getElementById('firstVisitTip');
        const botao = document.getElementById('firstVisitOk');

        botao?.addEventListener('click', async () => {
            const form = new FormData();

            form.append(
                'csrf_token',
                <?= json_encode(csrfToken()) ?>
            );
            form.append('acao', 'marcar_apresentacao');
            form.append('chave', card.dataset.chave);

            card.classList.add('saindo');

            try {
                await fetch('../actions/preferencias.php', {
                    method: 'POST',
                    body: form,
                });
            } catch (erro) {
                console.warn(
                    'Não foi possível salvar a apresentação.',
                    erro
                );
            }

            setTimeout(() => card.remove(), 180);
        });
    });
</script>
<?php endif; ?>
