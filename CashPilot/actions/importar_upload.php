<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/csv_helper.php';
exigirLogin();

$pdo = conectar();
$usuarioId = usuarioLogadoId();

function voltarComErro(string $mensagem): void
{
    $_SESSION['erro_importacao'] = $mensagem;
    header('Location: ../pages/importar.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['arquivo_csv'])) {
    voltarComErro('Envie um arquivo CSV para continuar.');
}

// ------------------------------------------------------------
// 1. Resolve a conta (existente ou nova)
// ------------------------------------------------------------
$contaId = $_POST['conta_id'] ?? '';

if ($contaId === 'nova') {
    $nomeNovaConta = trim($_POST['nome_nova_conta'] ?? '');
    if ($nomeNovaConta === '') {
        voltarComErro('Informe um nome para a nova conta.');
    }
    $stmt = $pdo->prepare('INSERT INTO contas (usuario_id, nome, tipo) VALUES (:uid, :nome, "corrente")');
    $stmt->execute(['uid' => $usuarioId, 'nome' => $nomeNovaConta]);
    $contaId = (int) $pdo->lastInsertId();
} else {
    $contaId = (int) $contaId;
    $stmt = $pdo->prepare('SELECT id FROM contas WHERE id = :id AND usuario_id = :uid');
    $stmt->execute(['id' => $contaId, 'uid' => $usuarioId]);
    if (!$stmt->fetch()) {
        voltarComErro('Conta inválida.');
    }
}

// ------------------------------------------------------------
// 2. Valida e faz o parsing do CSV
// ------------------------------------------------------------
$erroValidacao = validarArquivoCsv($_FILES['arquivo_csv']);
if ($erroValidacao !== null) {
    voltarComErro($erroValidacao);
}

$hashArquivo = hash_file('sha256', $_FILES['arquivo_csv']['tmp_name']);

// Verifica se este mesmo arquivo já foi importado por este usuário
$stmt = $pdo->prepare('SELECT id, data_importacao FROM importacoes WHERE usuario_id = :uid AND hash_arquivo = :hash ORDER BY data_importacao DESC LIMIT 1');
$stmt->execute(['uid' => $usuarioId, 'hash' => $hashArquivo]);
$importacaoAnterior = $stmt->fetch();

if ($importacaoAnterior && empty($_POST['ignorar_duplicidade'])) {
    voltarComErro(
        'Este arquivo já foi importado em ' . date('d/m/Y', strtotime($importacaoAnterior['data_importacao'])) . '. '
        . 'Se quiser importar mesmo assim, envie o arquivo novamente confirmando a duplicidade.'
    );
}

$resultado = parsearCsvExtrato($_FILES['arquivo_csv']['tmp_name']);
if ($resultado['erro'] !== null) {
    voltarComErro($resultado['erro']);
}

$linhas = $resultado['linhas'];

// ------------------------------------------------------------
// 3. Marca possíveis duplicatas em relação ao que já existe no banco
// ------------------------------------------------------------
$stmtDupReceita = $pdo->prepare(
    'SELECT COUNT(*) FROM receitas WHERE usuario_id = :uid AND descricao = :descricao AND valor = :valor AND data_receita = :data'
);
$stmtDupDespesa = $pdo->prepare(
    'SELECT COUNT(*) FROM despesas WHERE usuario_id = :uid AND descricao = :descricao AND valor = :valor AND data_despesa = :data'
);

foreach ($linhas as &$linha) {
    $valorAbsoluto = abs($linha['valor']);
    if ($linha['tipo'] === 'receita') {
        $stmtDupReceita->execute(['uid' => $usuarioId, 'descricao' => $linha['descricao'], 'valor' => $valorAbsoluto, 'data' => $linha['data']]);
        $linha['possivel_duplicata'] = (bool) $stmtDupReceita->fetchColumn();
    } else {
        $stmtDupDespesa->execute(['uid' => $usuarioId, 'descricao' => $linha['descricao'], 'valor' => $valorAbsoluto, 'data' => $linha['data']]);
        $linha['possivel_duplicata'] = (bool) $stmtDupDespesa->fetchColumn();
    }
}
unset($linha);

// ------------------------------------------------------------
// 4. Chama o módulo Python de classificação automática
// ------------------------------------------------------------
function classificarComPython(int $usuarioId, array $linhas): array
{
    $entrada = json_encode(array_map(fn($l) => [
        'descricao' => $l['descricao'],
        'valor' => $l['tipo'] === 'despesa' ? -abs($l['valor']) : abs($l['valor']),
        'data' => $l['data'],
    ], $linhas), JSON_UNESCAPED_UNICODE);

    $pastaScript = __DIR__ . '/../python';
    $descritores = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $processo = proc_open('python3 classificar.py ' . escapeshellarg((string) $usuarioId), $descritores, $pipes, $pastaScript);

    if (!is_resource($processo)) {
        return [];
    }

    fwrite($pipes[0], $entrada);
    fclose($pipes[0]);
    $saida = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($processo);

    $dados = json_decode($saida, true);
    return is_array($dados) ? $dados : [];
}

$classificacoes = classificarComPython($usuarioId, $linhas);

// Combina o resultado da classificação (se disponível) com as linhas originais,
// preservando a flag de duplicata. Se o Python falhar, o sistema continua
// funcionando com uma classificação básica por sinal do valor.
if (count($classificacoes) === count($linhas)) {
    foreach ($linhas as $i => &$linha) {
        $linha['tipo'] = $classificacoes[$i]['tipo'] ?? $linha['tipo'];
        $linha['categoria_id'] = $classificacoes[$i]['categoria_id'] ?? null;
        $linha['categoria_nome'] = $classificacoes[$i]['categoria_nome'] ?? 'Outros';
        $linha['confianca'] = $classificacoes[$i]['confianca'] ?? 'baixa';
    }
    unset($linha);
} else {
    foreach ($linhas as &$linha) {
        $linha['categoria_id'] = null;
        $linha['categoria_nome'] = 'Outros';
        $linha['confianca'] = 'baixa';
    }
    unset($linha);
}

// ------------------------------------------------------------
// 5. Guarda a pré-visualização na sessão para a etapa de revisão
// ------------------------------------------------------------
$_SESSION['importacao_preview'] = [
    'conta_id' => $contaId,
    'nome_arquivo' => $_FILES['arquivo_csv']['name'],
    'hash_arquivo' => $hashArquivo,
    'quantidade_linhas' => count($linhas) + ($resultado['ignoradas'] ?? 0),
    'quantidade_ignoradas' => $resultado['ignoradas'] ?? 0,
    'linhas' => $linhas,
];

header('Location: ../pages/importar_revisao.php');
exit;
