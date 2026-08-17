<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/ia.php';
require_once __DIR__ . '/../includes/negocio_financeiro.php';
exigirLogin();
validarCsrf();
header('Content-Type: application/json; charset=utf-8');

$pergunta = trim($_POST['mensagem'] ?? '');
$usuarioId = usuarioLogadoId();
if ($pergunta === '') {
    echo json_encode(['resposta' => 'Escreva uma pergunta para o Copiloto.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (mb_strlen($pergunta) > 800) {
    echo json_encode(['resposta' => 'Para eu analisar melhor, envie uma pergunta mais curta.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = conectar();
$stmt = $pdo->prepare('SELECT nome, tipo_perfil, nicho, telefone FROM usuarios WHERE id = :uid');
$stmt->execute(['uid' => $usuarioId]);
$usuario = $stmt->fetch();
if (($usuario['tipo_perfil'] ?? '') === 'mei') cpSincronizarCustosRecorrentesMes($pdo,$usuarioId);

function somaChat(PDO $pdo, string $tabela, string $campo, int $uid, string $inicio, string $fim): float {
    $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM {$tabela} WHERE usuario_id=:uid AND {$campo} BETWEEN :i AND :f");
    $s->execute(['uid'=>$uid,'i'=>$inicio,'f'=>$fim]);
    return (float)$s->fetchColumn();
}
function resumoCategoriasChat(PDO $pdo, int $uid, string $inicio, string $fim): array {
    $s = $pdo->prepare("SELECT COALESCE(c.nome,'Outros') categoria, SUM(d.valor) total FROM despesas d LEFT JOIN categorias c ON c.id=d.categoria_id WHERE d.usuario_id=:uid AND d.data_despesa BETWEEN :i AND :f GROUP BY categoria ORDER BY total DESC LIMIT 5");
    $s->execute(['uid'=>$uid,'i'=>$inicio,'f'=>$fim]);
    return $s->fetchAll();
}

$inicio = date('Y-m-01');
$fim = date('Y-m-t');
$inicioAnterior = date('Y-m-01', strtotime('first day of last month'));
$fimAnterior = date('Y-m-t', strtotime('last day of last month'));
$receitas = somaChat($pdo,'receitas','data_receita',$usuarioId,$inicio,$fim);
$despesas = somaChat($pdo,'despesas','data_despesa',$usuarioId,$inicio,$fim);
$receitasAnterior = somaChat($pdo,'receitas','data_receita',$usuarioId,$inicioAnterior,$fimAnterior);
$despesasAnterior = somaChat($pdo,'despesas','data_despesa',$usuarioId,$inicioAnterior,$fimAnterior);
$saldo = $receitas - $despesas;
$categorias = resumoCategoriasChat($pdo,$usuarioId,$inicio,$fim);
$metas = [];
if (($usuario['tipo_perfil'] ?? 'pessoa_fisica') === 'pessoa_fisica') {
    $stmt = $pdo->prepare('SELECT titulo, valor_meta, valor_atual, prazo FROM metas WHERE usuario_id=:uid AND concluida=0 ORDER BY prazo IS NULL,prazo ASC LIMIT 5');
    $stmt->execute(['uid'=>$usuarioId]);
    $metas = $stmt->fetchAll();
}
$negocio = []; $produtos = []; $funcionarios = []; $fornecedores=[]; $custosNegocio=[]; $resumoVendas=[]; $compromissos=[];
if (($usuario['tipo_perfil'] ?? '') === 'mei') {
    cpSincronizarCustosRecorrentesMes($pdo,$usuarioId); $resumoVendas=cpResumoVendas($pdo,$usuarioId,$inicio,$fim); $compromissos=cpCompromissosMensais($pdo,$usuarioId);
    try {
        $stmt=$pdo->prepare('SELECT nome_negocio,oferta,operacao,publico_alvo,canal_principal,objetivo_principal FROM perfil_negocio WHERE usuario_id=:uid');$stmt->execute(['uid'=>$usuarioId]);$negocio=$stmt->fetch()?:[];
        $stmt=$pdo->prepare('SELECT nome,tipo,preco_venda,custo_unitario FROM produtos_servicos WHERE usuario_id=:uid AND ativo=1 ORDER BY nome LIMIT 20');$stmt->execute(['uid'=>$usuarioId]);$produtos=$stmt->fetchAll();
        $stmt=$pdo->prepare('SELECT nome,cargo,salario_base,outros_custos,dia_pagamento FROM funcionarios WHERE usuario_id=:uid AND ativo=1 ORDER BY nome LIMIT 20');$stmt->execute(['uid'=>$usuarioId]);$funcionarios=$stmt->fetchAll();
        $stmt=$pdo->prepare('SELECT nome,descricao,valor_padrao,recorrente,dia_vencimento FROM fornecedores WHERE usuario_id=:uid AND ativo=1 ORDER BY nome LIMIT 20');$stmt->execute(['uid'=>$usuarioId]);$fornecedores=$stmt->fetchAll();
        $stmt=$pdo->prepare('SELECT descricao,valor,recorrente,dia_vencimento FROM custos_negocio WHERE usuario_id=:uid AND ativo=1 ORDER BY descricao LIMIT 20');$stmt->execute(['uid'=>$usuarioId]);$custosNegocio=$stmt->fetchAll();
    } catch (Throwable $e) {}
}

$contexto = [
    'perfil' => $usuario['tipo_perfil'] ?? 'pessoa_fisica',
    'nicho' => $usuario['nicho'] ?? null,
    'mes_atual' => [
        'receitas' => $receitas,
        'despesas' => $despesas,
        'resultado' => $saldo,
        'categorias_despesa' => $categorias,
    ],
    'mes_anterior' => [
        'receitas' => $receitasAnterior,
        'despesas' => $despesasAnterior,
        'resultado' => $receitasAnterior - $despesasAnterior,
    ],
    'metas_ativas' => $metas,
    'negocio' => $negocio,
    'produtos_servicos' => $produtos,
    'funcionarios' => $funcionarios,
    'fornecedores' => $fornecedores,
    'custos_negocio' => $custosNegocio,
    'resumo_vendas' => $resumoVendas,
    'compromissos_recorrentes' => $compromissos,
];

function chamarCopilotoIA(string $pergunta, array $contexto): ?string
{
    $tipo = ($contexto['perfil'] ?? 'pessoa_fisica') === 'mei'
        ? 'empreendedor/MEI'
        : 'pessoa física';

    $nicho = $contexto['nicho'] ?: 'não informado';

    $instrucoes = <<<TXT
Você é o Copiloto CashPilot, um assistente de apoio à gestão financeira.
Responda sempre em português do Brasil, com linguagem clara, objetiva e prática.
Perfil do usuário: {$tipo}. Nicho do negócio: {$nicho}.

REGRAS IMPORTANTES:
- Use somente os dados fornecidos pelo CashPilot para fazer afirmações numéricas sobre o usuário.
- Nunca invente valores, transações, metas, produtos, funcionários ou resultados.
- Diferencie claramente fato observado, estimativa e sugestão.
- Quando houver um problema, explique: (1) o que aconteceu; (2) por que merece atenção; (3) uma ou mais ações possíveis.
- Para pessoa física, priorize orçamento, gastos, economia, metas e organização financeira.
- Para empreendedor/MEI, priorize faturamento, despesas, resultado, margem, caixa, custos, equipe, produtos/serviços e o contexto do nicho.
- Não se apresente como contador, advogado, consultor de investimentos ou substituto desses profissionais.
- Não prometa resultados e não tome decisões pelo usuário.
- Em decisões sensíveis, como contratar ou demitir funcionários, mostre apenas impactos financeiros e ressalte fatores humanos, legais e operacionais.
- Prefira respostas curtas, normalmente entre 2 e 6 parágrafos, salvo se o usuário pedir detalhes.
TXT;

    $contextoJson = json_encode(
        $contexto,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    );

    if ($contextoJson === false) {
        return null;
    }

    $mensagens = [
        [
            'role' => 'system',
            'content' => $instrucoes,
        ],
        [
            'role' => 'user',
            'content' => "CONTEXTO FINANCEIRO DO CASHPILOT:\n{$contextoJson}\n\nPERGUNTA DO USUÁRIO:\n{$pergunta}",
        ],
    ];

    return enviarParaIA($mensagens);
}

function respostaLocal(string $pergunta, array $contexto): string {
    $p = mb_strtolower($pergunta);
    $r = (float)$contexto['mes_atual']['receitas'];
    $d = (float)$contexto['mes_atual']['despesas'];
    $saldo = (float)$contexto['mes_atual']['resultado'];
    $cats = $contexto['mes_atual']['categorias_despesa'];
    $maior = $cats[0] ?? null;
    if (str_contains($p,'explicar') || str_contains($p,'meu mês') || str_contains($p,'meu mes')) {
        $extra = $maior ? ' A maior categoria de despesa é '.$maior['categoria'].' ('.formatarMoeda((float)$maior['total']).').' : '';
        return 'Neste mês você registrou '.formatarMoeda($r).' em receitas e '.formatarMoeda($d).' em despesas. O resultado é '.formatarMoeda($saldo).'.'.$extra.' '.($saldo>=0?'O fluxo está positivo.':'O resultado está negativo; vale revisar os gastos antes de assumir novos compromissos.');
    }
    if (str_contains($p,'gast') || str_contains($p,'despesa')) return $maior ? 'Sua maior categoria de gasto no mês é '.$maior['categoria'].', com '.formatarMoeda((float)$maior['total']).'. No total, suas despesas somam '.formatarMoeda($d).'.' : 'Suas despesas do mês somam '.formatarMoeda($d).'. Ainda não há categorias suficientes para apontar a maior concentração.';
    if (str_contains($p,'caixa') || str_contains($p,'previs')) return 'Seu resultado do mês é '.formatarMoeda($saldo).'. Para o negócio, use os compromissos recorrentes cadastrados como base concreta: equipe, fornecedores recorrentes e custos fixos. O CashPilot evita projetar um saldo futuro sem dados suficientes.';
    if (str_contains($p,'meta') && !empty($contexto['metas_ativas'])) { $m=$contexto['metas_ativas'][0]; $falta=max(0,(float)$m['valor_meta']-(float)$m['valor_atual']); return 'Na meta “'.$m['titulo'].'”, faltam '.formatarMoeda($falta).' para atingir o objetivo de '.formatarMoeda((float)$m['valor_meta']).'.'; }
    if (str_contains($p,'vender') || str_contains($p,'crescer')) { $n=$contexto['nicho'] ?: 'seu negócio'; return 'Para '.$n.', eu começaria comparando margem, custos e as fontes de receita que mais contribuem para o resultado. No mês atual, seu resultado registrado é '.formatarMoeda($saldo).'.'; }
    if (($contexto['perfil'] ?? '') === 'mei') return 'Posso analisar o caixa e a estrutura do seu negócio. Tente: “explique meu negócio”, “onde estão meus maiores custos?”, “como está meu caixa?”, “qual impacto da equipe?” ou “como posso crescer?”.';
    return 'Posso analisar seus dados do CashPilot. Tente: “explique meu mês”, “onde estou gastando mais?”, “como está meu caixa?”, “como está minha meta?” ou “como posso economizar?”.';
}

$resposta = chamarCopilotoIA($pergunta, $contexto);
$modo = $resposta ? 'groq' : 'local';
if (!$resposta) $resposta = respostaLocal($pergunta,$contexto);
echo json_encode(['resposta'=>$resposta,'modo'=>$modo],JSON_UNESCAPED_UNICODE);
