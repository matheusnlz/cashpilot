<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$tituloPagina = 'Dashboard';
$pdo = conectar();
$usuarioId = usuarioLogadoId();

$inicioMes = date('Y-m-01');
$fimMes    = date('Y-m-t');
$inicioMesAnterior = date('Y-m-01', strtotime('first day of last month'));
$fimMesAnterior    = date('Y-m-t', strtotime('last day of last month'));

function totalPeriodo(PDO $pdo, string $tabela, string $campoData, int $usuarioId, string $inicio, string $fim): float
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(valor),0) AS total FROM {$tabela} WHERE usuario_id = :uid AND {$campoData} BETWEEN :inicio AND :fim");
    $stmt->execute(['uid' => $usuarioId, 'inicio' => $inicio, 'fim' => $fim]);
    return (float) $stmt->fetchColumn();
}

$receitasMes = totalPeriodo($pdo, 'receitas', 'data_receita', $usuarioId, $inicioMes, $fimMes);
$despesasMes = totalPeriodo($pdo, 'despesas', 'data_despesa', $usuarioId, $inicioMes, $fimMes);
$receitasMesAnterior = totalPeriodo($pdo, 'receitas', 'data_receita', $usuarioId, $inicioMesAnterior, $fimMesAnterior);
$despesasMesAnterior = totalPeriodo($pdo, 'despesas', 'data_despesa', $usuarioId, $inicioMesAnterior, $fimMesAnterior);

$saldoMes = $receitasMes - $despesasMes;
$taxaEconomia = $receitasMes > 0 ? round(($saldoMes / $receitasMes) * 100, 1) : null;

$stmtSaldoGeral = $pdo->prepare(
    'SELECT
        (SELECT COALESCE(SUM(valor),0) FROM receitas WHERE usuario_id = :uid1) -
        (SELECT COALESCE(SUM(valor),0) FROM despesas WHERE usuario_id = :uid2) AS saldo_geral'
);
$stmtSaldoGeral->execute(['uid1' => $usuarioId, 'uid2' => $usuarioId]);
$saldoGeral = (float) $stmtSaldoGeral->fetchColumn();

$variacaoDespesas = $despesasMesAnterior > 0
    ? round((($despesasMes - $despesasMesAnterior) / $despesasMesAnterior) * 100, 1)
    : null;

// Últimas movimentações (receitas + despesas combinadas)
$stmtMovimentacoes = $pdo->prepare(
    "(SELECT 'receita' AS tipo, descricao, valor, data_receita AS data FROM receitas WHERE usuario_id = :uid1)
     UNION ALL
     (SELECT 'despesa' AS tipo, descricao, valor, data_despesa AS data FROM despesas WHERE usuario_id = :uid2)
     ORDER BY data DESC LIMIT 8"
);
$stmtMovimentacoes->execute(['uid1' => $usuarioId, 'uid2' => $usuarioId]);
$movimentacoes = $stmtMovimentacoes->fetchAll();

// Metas em andamento
$stmtMetas = $pdo->prepare('SELECT titulo, valor_meta, valor_atual FROM metas WHERE usuario_id = :uid AND concluida = 0 ORDER BY prazo IS NULL, prazo ASC LIMIT 3');
$stmtMetas->execute(['uid' => $usuarioId]);
$metasAndamento = $stmtMetas->fetchAll();

// ---------------------------------------------------------------
// Chama o módulo Python de insights e de análise (gráfico)
// ---------------------------------------------------------------
function executarPython(string $script, array $args): ?array
{
    $pastaScript = escapeshellarg(__DIR__ . '/../python');
    $interpretador = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
    $comando = 'cd ' . $pastaScript . ' && ' . $interpretador . ' ' . escapeshellarg($script);
    foreach ($args as $arg) {
        $comando .= ' ' . escapeshellarg((string) $arg);
    }
    $comando .= ' 2>/dev/null';

    $saida = @shell_exec($comando);
    if ($saida === null) {
        return null;
    }
    $dados = json_decode($saida, true);
    return is_array($dados) ? $dados : null;
}

$respostaInsights = executarPython('insights.py', [$usuarioId]);
$insights = $respostaInsights['insights'] ?? [];

$respostaAnalise = executarPython('analise.py', [$usuarioId, 6]);
$evolucaoMensal = $respostaAnalise['evolucao_mensal'] ?? [];

// Mantém o gráfico funcional mesmo quando o Python não estiver disponível no servidor.
if (empty($evolucaoMensal)) {
    for ($i = 5; $i >= 0; $i--) {
        $referencia = new DateTime("first day of -{$i} month");
        $inicio = $referencia->format('Y-m-01');
        $fim = $referencia->format('Y-m-t');
        $evolucaoMensal[] = [
            'mes' => $referencia->format('Y-m'),
            'receitas' => totalPeriodo($pdo, 'receitas', 'data_receita', $usuarioId, $inicio, $fim),
            'despesas' => totalPeriodo($pdo, 'despesas', 'data_despesa', $usuarioId, $inicio, $fim),
        ];
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="topo-pagina">
    <div>
        <h1>Olá, <?= limpar(explode(' ', usuarioLogadoNome())[0]) ?> 👋</h1>
        <p>Aqui está o resumo da sua situação financeira.</p>
    </div>
</div>

<div class="grade-resumo">
    <div class="cartao cartao-metrica">
        <div class="rotulo">Saldo geral</div>
        <div class="valor <?= $saldoGeral >= 0 ? 'positivo' : 'negativo' ?>"><?= formatarMoeda($saldoGeral) ?></div>
    </div>
    <div class="cartao cartao-metrica">
        <div class="rotulo">Receitas do mês</div>
        <div class="valor positivo"><?= formatarMoeda($receitasMes) ?></div>
    </div>
    <div class="cartao cartao-metrica">
        <div class="rotulo">Despesas do mês</div>
        <div class="valor negativo"><?= formatarMoeda($despesasMes) ?></div>
        <?php if ($variacaoDespesas !== null): ?>
            <div class="variacao"><?= $variacaoDespesas >= 0 ? '↑' : '↓' ?> <?= abs($variacaoDespesas) ?>% vs. mês anterior</div>
        <?php endif; ?>
    </div>
    <div class="cartao cartao-metrica">
        <div class="rotulo">Resultado do mês</div>
        <div class="valor <?= $saldoMes >= 0 ? 'positivo' : 'negativo' ?>"><?= formatarMoeda($saldoMes) ?></div>
    </div>
    <div class="cartao cartao-metrica">
        <div class="rotulo">Taxa de economia</div>
        <div class="valor <?= $taxaEconomia !== null && $taxaEconomia >= 0 ? 'positivo' : 'negativo' ?>"><?= $taxaEconomia === null ? '—' : $taxaEconomia . '%' ?></div>
        <?php if ($taxaEconomia !== null): ?><div class="variacao"><?= $taxaEconomia >= 0 ? 'da renda ficou disponível' : 'acima das receitas' ?></div><?php endif; ?>
    </div>
</div>

<div class="grade-dupla">
    <div class="cartao">
        <h3 style="margin-bottom:16px;">Receitas x Despesas — últimos 6 meses</h3>
        <div class="container-grafico">
            <canvas id="graficoEvolucao"></canvas>
        </div>
    </div>

    <div class="cartao">
        <h3 style="margin-bottom:4px;">Insights</h3>
        <p style="font-size:12.5px; color:var(--cor-texto-suave);">Gerados automaticamente a partir dos seus dados</p>
        <div class="lista-insights">
            <?php if (empty($insights)): ?>
                <p class="texto-vazio">Cadastre movimentações para receber insights.</p>
            <?php else: ?>
                <?php foreach ($insights as $item): ?>
                    <div class="insight <?= ($item['tipo'] ?? 'info') === 'alerta' ? 'alerta' : '' ?>">
                        <?= limpar($item['mensagem']) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grade-dupla" style="margin-top:20px;">
    <div class="cartao">
        <h3 style="margin-bottom:16px;">Últimas movimentações</h3>
        <?php if (empty($movimentacoes)): ?>
            <p class="texto-vazio">Nenhuma movimentação registrada ainda.</p>
        <?php else: ?>
            <table>
                <tbody>
                <?php foreach ($movimentacoes as $mov): ?>
                    <tr>
                        <td><?= limpar($mov['descricao']) ?></td>
                        <td><?= date('d/m', strtotime($mov['data'])) ?></td>
                        <td style="text-align:right; color: <?= $mov['tipo'] === 'receita' ? 'var(--cor-sucesso)' : 'var(--cor-erro)' ?>; font-weight:600;">
                            <?= $mov['tipo'] === 'receita' ? '+' : '-' ?> <?= formatarMoeda((float) $mov['valor']) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="cartao">
        <h3 style="margin-bottom:16px;">Metas em andamento</h3>
        <?php if (empty($metasAndamento)): ?>
            <p class="texto-vazio">Nenhuma meta em andamento.</p>
        <?php else: ?>
            <?php foreach ($metasAndamento as $m):
                $percentual = $m['valor_meta'] > 0 ? min(100, ($m['valor_atual'] / $m['valor_meta']) * 100) : 0;
            ?>
                <div style="margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; font-size:13.5px; margin-bottom:6px;">
                        <span><?= limpar($m['titulo']) ?></span>
                        <span style="color:var(--cor-texto-suave);"><?= number_format($percentual, 0) ?>%</span>
                    </div>
                    <div class="barra-progresso"><div class="preenchido" style="width: <?= $percentual ?>%;"></div></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="../assets/js/dashboard.js"></script>
<script>
    const dadosEvolucaoMensal = <?= json_encode($evolucaoMensal, JSON_UNESCAPED_UNICODE) ?>;
    inicializarGraficoEvolucao(dadosEvolucaoMensal);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
