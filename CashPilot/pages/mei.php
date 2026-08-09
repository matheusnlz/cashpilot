<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$tituloPagina = 'Área MEI';
$pdo = conectar();
$usuarioId = usuarioLogadoId();

$inicioMes = date('Y-m-01');
$fimMes = date('Y-m-t');
$inicioAno = date('Y-01-01');
$fimAno = date('Y-12-31');

function totalPeriodoMei(PDO $pdo, string $tabela, string $campoData, int $usuarioId, string $inicio, string $fim): float
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM {$tabela} WHERE usuario_id = :uid AND {$campoData} BETWEEN :i AND :f");
    $stmt->execute(['uid' => $usuarioId, 'i' => $inicio, 'f' => $fim]);
    return (float) $stmt->fetchColumn();
}

$entradasMes = totalPeriodoMei($pdo, 'receitas', 'data_receita', $usuarioId, $inicioMes, $fimMes);
$saidasMes   = totalPeriodoMei($pdo, 'despesas', 'data_despesa', $usuarioId, $inicioMes, $fimMes);
$entradasAno = totalPeriodoMei($pdo, 'receitas', 'data_receita', $usuarioId, $inicioAno, $fimAno);
$saidasAno   = totalPeriodoMei($pdo, 'despesas', 'data_despesa', $usuarioId, $inicioAno, $fimAno);

$fluxoCaixaMes = $entradasMes - $saidasMes;

$stmt = $pdo->prepare(
    "SELECT COALESCE(c.nome, 'Sem categoria') AS categoria, SUM(d.valor) AS total
     FROM despesas d LEFT JOIN categorias c ON c.id = d.categoria_id
     WHERE d.usuario_id = :uid AND d.data_despesa BETWEEN :i AND :f
     GROUP BY categoria ORDER BY total DESC LIMIT 5"
);
$stmt->execute(['uid' => $usuarioId, 'i' => $inicioMes, 'f' => $fimMes]);
$principaisCustos = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<div class="topo-pagina">
    <div>
        <h1>Área MEI</h1>
        <p>Visão do desempenho financeiro do seu negócio. O CashPilot é uma ferramenta de apoio à gestão — para questões fiscais e contábeis, consulte um contador.</p>
    </div>
</div>

<div class="grade-resumo">
    <div class="cartao cartao-metrica">
        <div class="rotulo">Entradas do mês</div>
        <div class="valor positivo"><?= formatarMoeda($entradasMes) ?></div>
    </div>
    <div class="cartao cartao-metrica">
        <div class="rotulo">Saídas do mês</div>
        <div class="valor negativo"><?= formatarMoeda($saidasMes) ?></div>
    </div>
    <div class="cartao cartao-metrica">
        <div class="rotulo">Fluxo de caixa do mês</div>
        <div class="valor <?= $fluxoCaixaMes >= 0 ? 'positivo' : 'negativo' ?>"><?= formatarMoeda($fluxoCaixaMes) ?></div>
    </div>
    <div class="cartao cartao-metrica">
        <div class="rotulo">Resultado acumulado no ano</div>
        <div class="valor <?= ($entradasAno - $saidasAno) >= 0 ? 'positivo' : 'negativo' ?>"><?= formatarMoeda($entradasAno - $saidasAno) ?></div>
    </div>
</div>

<div class="cartao">
    <h3 style="margin-bottom:16px;">Principais custos do negócio este mês</h3>
    <?php if (empty($principaisCustos)): ?>
        <p class="texto-vazio">Nenhum custo registrado neste mês.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Categoria</th><th>Total</th><th>% das saídas</th></tr></thead>
            <tbody>
            <?php foreach ($principaisCustos as $c):
                $percentual = $saidasMes > 0 ? ($c['total'] / $saidasMes) * 100 : 0;
            ?>
                <tr>
                    <td><?= limpar($c['categoria']) ?></td>
                    <td><?= formatarMoeda((float) $c['total']) ?></td>
                    <td><?= number_format($percentual, 1) ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
