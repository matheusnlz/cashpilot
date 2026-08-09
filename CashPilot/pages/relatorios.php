<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$tituloPagina = 'Relatórios';
$pdo = conectar();
$usuarioId = usuarioLogadoId();

function executarPython(string $script, array $args): ?array
{
    $pastaScript = escapeshellarg(__DIR__ . '/../python');
    $comando = 'cd ' . $pastaScript . ' && python3 ' . escapeshellarg($script);
    foreach ($args as $arg) {
        $comando .= ' ' . escapeshellarg((string) $arg);
    }
    $comando .= ' 2>/dev/null';
    $saida = @shell_exec($comando);
    if ($saida === null) return null;
    $dados = json_decode($saida, true);
    return is_array($dados) ? $dados : null;
}

$resposta = executarPython('analise.py', [$usuarioId, 6]);
$evolucaoMensal = $resposta['evolucao_mensal'] ?? [];
$gastosPorCategoria = $resposta['gastos_por_categoria'] ?? [];

$inicioMes = date('Y-m-01');
$fimMes = date('Y-m-t');

$stmt = $pdo->prepare('SELECT COALESCE(SUM(valor),0) FROM receitas WHERE usuario_id = :uid AND data_receita BETWEEN :i AND :f');
$stmt->execute(['uid' => $usuarioId, 'i' => $inicioMes, 'f' => $fimMes]);
$receitasMes = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COALESCE(SUM(valor),0) FROM despesas WHERE usuario_id = :uid AND data_despesa BETWEEN :i AND :f');
$stmt->execute(['uid' => $usuarioId, 'i' => $inicioMes, 'f' => $fimMes]);
$despesasMes = (float) $stmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="topo-pagina">
    <div>
        <h1>Relatórios</h1>
        <p>Visão organizada da sua evolução financeira.</p>
    </div>
</div>

<div class="grade-resumo">
    <div class="cartao cartao-metrica">
        <div class="rotulo">Receitas do mês</div>
        <div class="valor positivo"><?= formatarMoeda($receitasMes) ?></div>
    </div>
    <div class="cartao cartao-metrica">
        <div class="rotulo">Despesas do mês</div>
        <div class="valor negativo"><?= formatarMoeda($despesasMes) ?></div>
    </div>
    <div class="cartao cartao-metrica">
        <div class="rotulo">Saldo do mês</div>
        <div class="valor <?= ($receitasMes - $despesasMes) >= 0 ? 'positivo' : 'negativo' ?>"><?= formatarMoeda($receitasMes - $despesasMes) ?></div>
    </div>
</div>

<div class="grade-dupla">
    <div class="cartao">
        <h3 style="margin-bottom:16px;">Evolução mensal (6 meses)</h3>
        <div class="container-grafico">
            <canvas id="graficoEvolucaoRelatorio"></canvas>
        </div>
    </div>

    <div class="cartao">
        <h3 style="margin-bottom:16px;">Despesas por categoria (mês atual)</h3>
        <?php if (empty($gastosPorCategoria)): ?>
            <p class="texto-vazio">Nenhuma despesa registrada neste mês.</p>
        <?php else: ?>
            <div class="container-grafico" style="height:240px;">
                <canvas id="graficoCategorias"></canvas>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="cartao" style="margin-top:20px;">
    <h3 style="margin-bottom:16px;">Detalhamento por categoria</h3>
    <?php if (empty($gastosPorCategoria)): ?>
        <p class="texto-vazio">Nenhum dado disponível ainda.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Categoria</th><th>Total gasto</th><th>% do total</th></tr></thead>
            <tbody>
            <?php foreach ($gastosPorCategoria as $g):
                $percentual = $despesasMes > 0 ? ($g['total'] / $despesasMes) * 100 : 0;
            ?>
                <tr>
                    <td><?= limpar($g['categoria']) ?></td>
                    <td><?= formatarMoeda((float) $g['total']) ?></td>
                    <td><?= number_format($percentual, 1) ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script src="../assets/js/dashboard.js"></script>
<script src="../assets/js/relatorios.js"></script>
<script>
    const dadosEvolucao = <?= json_encode($evolucaoMensal, JSON_UNESCAPED_UNICODE) ?>;
    const dadosCategorias = <?= json_encode($gastosPorCategoria, JSON_UNESCAPED_UNICODE) ?>;
    document.getElementById('graficoEvolucaoRelatorio') && inicializarGraficoEvolucao(dadosEvolucao, 'graficoEvolucaoRelatorio');
    if (document.getElementById('graficoCategorias')) inicializarGraficoCategorias(dadosCategorias);
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
