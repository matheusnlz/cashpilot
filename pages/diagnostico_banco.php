<?php
require_once __DIR__ . '/../includes/ambiente.php';
if (!cpPerfAtivo()) {
    http_response_code(404);
    exit('Diagnóstico desativado.');
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();
exigirAdminCashPilot(conectar());
header('Cache-Control: private, no-store, max-age=0');

/** Mede somente a execução e leitura de consultas mínimas, sem usar tabelas financeiras. */
function cpTesteConsultasMinimas(PDO $pdo): array
{
    $linhas = [];
    for ($i = 1; $i <= 3; $i++) {
        $inicio = hrtime(true);
        $stmt = $pdo->query('SELECT 1');
        $valor = $stmt->fetchColumn();
        $stmt->closeCursor();
        if ((int) $valor !== 1) {
            throw new RuntimeException('Resposta inesperada.');
        }
        $linhas[] = ['etapa' => 'SELECT 1 direto — teste ' . $i, 'ms' => (hrtime(true) - $inicio) / 1000000];
    }
    $inicio = hrtime(true);
    $stmt = $pdo->prepare('SELECT :valor');
    $linhas[] = ['etapa' => 'Preparação nativa de SELECT :valor', 'ms' => (hrtime(true) - $inicio) / 1000000];
    for ($i = 1; $i <= 3; $i++) {
        $inicio = hrtime(true);
        $stmt->execute(['valor' => 1]);
        $valor = $stmt->fetchColumn();
        $stmt->closeCursor();
        if ((int) $valor !== 1) {
            throw new RuntimeException('Resposta inesperada.');
        }
        $linhas[] = ['etapa' => 'Execução da mesma preparação — teste ' . $i, 'ms' => (hrtime(true) - $inicio) / 1000000];
    }
    return $linhas;
}

$token = csrfToken();
$resultados = [];
$falhou = false;
$regiao = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) (getenv('RAILWAY_REPLICA_REGION') ?: 'não informada'));
$host = cashpilotEnvPrimeiro(['DB_HOST', 'MYSQLHOST'], 'localhost');
$tipoHost = str_ends_with($host, '.railway.internal') ? 'Rede privada do Railway' : 'Outro endereço';
$metodo = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
if (!in_array($metodo, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Método não permitido.');
}
if ($metodo === 'POST') {
    validarCsrf();
    // Grava a sessão e libera sua trava antes dos testes de rede.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    try {
        $inicio = hrtime(true);
        $registros = @dns_get_record($host, DNS_A | DNS_AAAA);
        $enderecos = [];
        foreach (is_array($registros) ? $registros : [] as $registro) {
            $ip = $registro['ipv6'] ?? $registro['ip'] ?? '';
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $enderecos[] = $ip;
            }
        }
        $resultados[] = [
            'etapa' => 'Resolução DNS do endereço do banco',
            'ms' => (hrtime(true) - $inicio) / 1000000,
            'nota' => $enderecos ? 'Endereço resolvido; IP omitido.' : 'Sem IP neste teste; PDO será testado normalmente.'
        ];
        if ($enderecos) {
            $ip = $enderecos[0];
            $porta = (int) cashpilotEnvPrimeiro(['DB_PORT', 'MYSQLPORT'], '3306');
            $destino = 'tcp://' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip) . ':' . $porta;
            for ($i = 1; $i <= 3; $i++) {
                $inicio = hrtime(true);
                $codigo = 0;
                $mensagem = '';
                $socket = @stream_socket_client($destino, $codigo, $mensagem, 3, STREAM_CLIENT_CONNECT);
                $ms = (hrtime(true) - $inicio) / 1000000;
                if ($socket !== false) {
                    fclose($socket);
                }
                $resultados[] = [
                    'etapa' => 'Conexão TCP direta — teste ' . $i,
                    'ms' => $ms,
                    'nota' => $socket !== false ? 'Conectou, sem autenticação MySQL.' : 'Não conectou dentro do limite; detalhes privados omitidos.'
                ];
            }
        }
        $porta = (int) cashpilotEnvPrimeiro(['DB_PORT', 'MYSQLPORT'], '3306');
        $dsn = 'mysql:host=' . $host . ';port=' . $porta . ';dbname=' . cashpilotEnvPrimeiro(['DB_NAME', 'MYSQLDATABASE'], 'cashpilot') . ';charset=utf8mb4';
        $inicio = hrtime(true);
        $testePdo = new PDO($dsn,
            cashpilotEnvPrimeiro(['DB_USER', 'MYSQLUSER'], 'root'),
            cashpilotEnvPrimeiro(['DB_PASS', 'MYSQLPASSWORD'], ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5
            ]
        );
        $resultados[] = ['etapa' => 'Nova conexão PDO, incluindo autenticação', 'ms' => (hrtime(true) - $inicio) / 1000000];
        $resultados = array_merge($resultados, cpTesteConsultasMinimas($testePdo));
        $testePdo = null;
    } catch (Throwable $erro) {
        $falhou = true;
        // Não registrar mensagens que possam conter host, usuário ou DSN.
        cpPerfLog('teste_interno falha=1');
    }
    foreach ($resultados as $linha) {
        cpPerfLog(sprintf('teste_interno etapa=%s tempo=%.3f ms', $linha['etapa'], $linha['ms']));
    }
}
function cpTesteHtml(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CashPilot — teste interno do banco</title>
    <style>
        body{font:16px/1.55 system-ui,sans-serif;color:#243143;background:#f4f6f9;margin:0;padding:24px}
        main{max-width:920px;margin:auto;background:#fff;border:1px solid #dce2ea;border-radius:12px;padding:28px}
        h1{font-size:25px;margin:0 0 16px}p{margin:12px 0}button{font:inherit;color:#fff;background:#2446a2;border:0;border-radius:8px;padding:11px 18px;cursor:pointer}
        table{width:100%;border-collapse:collapse;margin:22px 0}th,td{text-align:left;padding:12px;border-bottom:1px solid #dce2ea;vertical-align:top}th{background:#edf2f8}td:nth-child(2){white-space:nowrap}
        .nota{color:#526176;font-size:14px}.erro{color:#9c2525}@media(max-width:600px){body{padding:10px}main{padding:16px}table{font-size:13px}th,td{padding:8px}}
    </style>
</head>
<body><main>
    <h1>Teste interno de comunicação com o banco</h1>
    <p>Executado dentro do serviço CashPilot. Acesso restrito à conta administradora.</p>
    <p><strong>Região informada pelo processo:</strong> <?= cpTesteHtml($regiao) ?><br>
       <strong>Endereço configurado:</strong> <?= cpTesteHtml($tipoHost) ?></p>
    <p>O teste abre conexões e executa consultas mínimas, sem ler ou alterar dados financeiros.</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= cpTesteHtml($token) ?>">
        <button type="submit">Executar teste interno</button>
    </form>
    <?php if ($falhou): ?>
        <p class="erro">Uma etapa não pôde ser concluída. Envie os resultados disponíveis e informe esta mensagem.</p>
    <?php endif; ?>
    <?php if ($resultados): ?>
        <table><thead><tr><th>Etapa</th><th>Tempo em ms</th><th>Observação</th></tr></thead><tbody>
        <?php foreach ($resultados as $linha): ?>
            <tr><td><?= cpTesteHtml($linha['etapa']) ?></td><td><?= number_format($linha['ms'], 3, ',', '.') ?></td><td><?= cpTesteHtml($linha['nota'] ?? '') ?></td></tr>
        <?php endforeach; ?>
        </tbody></table>
        <p>Copie a tabela e envie para a análise.</p>
    <?php endif; ?>
    <p class="nota">TCP mede a abertura da conexão pela rede. PDO inclui autenticação e negociação. Os SELECTs incluem comunicação e execução. Estes tempos não isolam exatamente o processamento interno do MySQL e não medem a internet do seu computador.</p>
    <p class="nota">As consultas desta conexão de teste têm medições próprias. O campo “banco” do resumo geral desta página não inclui essa conexão nova.</p>
    <p><a href="admin.php">Voltar à administração</a></p>
</main></body></html>
