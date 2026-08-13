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

$stmt = $pdo->prepare('SELECT COALESCE(c.nome, "Sem categoria") AS nome, COALESCE(SUM(d.valor), 0) AS total FROM despesas d LEFT JOIN categorias c ON c.id = d.categoria_id WHERE d.usuario_id = :uid AND d.data_despesa BETWEEN :i AND :f GROUP BY c.nome ORDER BY total DESC LIMIT 1');
$stmt->execute(['uid' => $usuarioId, 'i' => $inicioMes, 'f' => $fimMes]);
$maiorCategoria = $stmt->fetch();

$stmt = $pdo->prepare('SELECT descricao, SUM(valor) AS total, COUNT(*) AS ocorrencias FROM despesas WHERE usuario_id = :uid AND data_despesa >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY descricao HAVING COUNT(*) >= 2 ORDER BY total DESC LIMIT 1');
$stmt->execute(['uid' => $usuarioId]);
$gastoRecorrente = $stmt->fetch();

$stmt = $pdo->prepare('SELECT COALESCE(c.nome, "Sem categoria") AS nome, COALESCE(SUM(r.valor), 0) AS total FROM receitas r LEFT JOIN categorias c ON c.id = r.categoria_id WHERE r.usuario_id = :uid AND r.data_receita BETWEEN :i AND :f GROUP BY c.nome ORDER BY total DESC LIMIT 1');
$stmt->execute(['uid' => $usuarioId, 'i' => $inicioMes, 'f' => $fimMes]);
$origemRenda = $stmt->fetch();

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
        <div class="rotulo">Maior origem de renda</div>
        <div class="valor positivo" style="font-size:20px;"><?= limpar($origemRenda['nome'] ?? 'Sem dados') ?></div>
        <?php if ($origemRenda): ?><div class="variacao"><?= formatarMoeda((float) $origemRenda['total']) ?> no mês</div><?php endif; ?>
    </div>
    <div class="cartao cartao-metrica">
        <div class="rotulo">Maior categoria de gasto</div>
        <div class="valor negativo" style="font-size:20px;"><?= limpar($maiorCategoria['nome'] ?? 'Sem dados') ?></div>
        <?php if ($maiorCategoria): ?><div class="variacao"><?= formatarMoeda((float) $maiorCategoria['total']) ?> no mês</div><?php endif; ?>
    </div>
    <div class="cartao cartao-metrica">
        <div class="rotulo">Gasto recorrente</div>
        <div class="valor <?= $gastoRecorrente ? 'negativo' : '' ?>" style="font-size:20px;"><?= limpar($gastoRecorrente['descricao'] ?? 'Nenhum identificado') ?></div>
        <?php if ($gastoRecorrente): ?><div class="variacao"><?= (int) $gastoRecorrente['ocorrencias'] ?> ocorrências · <?= formatarMoeda((float) $gastoRecorrente['total']) ?></div><?php endif; ?>
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
    <h3 style="margin-bottom:8px;">Leitura do período</h3>
    <p style="color:var(--cor-texto-suave); font-size:14px;">No mês atual, você recebeu <?= formatarMoeda($receitasMes) ?>, gastou <?= formatarMoeda($despesasMes) ?> e ficou com <?= formatarMoeda($receitasMes - $despesasMes) ?> de resultado.</p>
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
