<?php
/** Controles persistentes da versão 17. Sem dependências adicionais. */

function cp17Configuracao(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/limites17_config.php';
    }
    return $config;
}

function cp17Log(string $evento, Throwable $erro): void
{
    // Nunca incluir mensagem de provedor, SQL, pergunta ou credencial no log.
    error_log('CashPilot17/' . $evento . ': ' . get_class($erro) . ' code=' . $erro->getCode());
}

function cp17ChaveSeguranca(): string
{
    $chave = (string) getenv('CASHPILOT_SECURITY_KEY');
    if (
        strlen($chave) >= 32 &&
        count(array_unique(str_split($chave))) >= 12 &&
        !preg_match('/troque|cole.?sua|exemplo|placeholder/i', $chave)
    ) {
        return $chave;
    }
    if (cashpilotEhProducao()) {
        throw new RuntimeException('Configuração de segurança de produção incompleta.');
    }
    // Apenas desenvolvimento. Não usar em produção.
    return 'cashpilot-local-rate-limit-v149';
}

/** Uma cota é [escopo sem dados públicos, máximo, segundos]. */
function cp17ConsumirLimites(PDO $pdo, array $cotas): int
{
    if ($pdo->inTransaction()) {
        throw new LogicException('Consuma a cota antes da transação financeira.');
    }
    $agora = time();
    $linhas = [];
    foreach ($cotas as [$escopo, $maximo, $janela]) {
        $janela = max(1, (int) $janela);
        $inicio = intdiv($agora, $janela) * $janela;
        $chave = hash_hmac('sha256', $escopo . ':' . $inicio, cp17ChaveSeguranca());
        $linhas[$chave] = [(int) $maximo, $inicio + $janela];
    }
    ksort($linhas); // Ordem fixa evita deadlocks entre cotas compartilhadas.
    $pdo->beginTransaction();
    try {
        foreach ($linhas as $chave => [$maximo, $fim]) {
            $s = $pdo->prepare(
                'INSERT INTO cp_limites (chave, quantidade, expira_em) VALUES (?, 0, ?)
                 ON DUPLICATE KEY UPDATE chave = VALUES(chave)'
            );
            $s->execute([$chave, $fim]);
            $s = $pdo->prepare('SELECT quantidade FROM cp_limites WHERE chave = ? FOR UPDATE');
            $s->execute([$chave]);
            if ((int) $s->fetchColumn() >= $maximo) {
                $pdo->rollBack();
                return max(1, $fim - $agora);
            }
        }
        $s = $pdo->prepare('UPDATE cp_limites SET quantidade = quantidade + 1 WHERE chave = ?');
        foreach (array_keys($linhas) as $chave) {
            $s->execute([$chave]);
        }
        $pdo->commit();
        // Limpeza limitada e sem informações de identificação em texto puro.
        try {
            if (random_int(1, 100) === 1) {
                $s = $pdo->prepare('DELETE FROM cp_limites WHERE expira_em < ? LIMIT 500');
                $s->execute([$agora - 86400]);
            }
        } catch (Throwable $erroLimpeza) {
            cp17Log('LimpezaCotas', $erroLimpeza);
        }
        return 0;
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

function cp17Interromper(int $status, string $mensagem, int $espera = 0): never
{
    http_response_code($status);
    header('Cache-Control: no-store');
    if ($espera > 0) {
        header('Retry-After: ' . $espera);
    }
    $arquivo = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $json =
        in_array(
            $arquivo,
            [
                'chatbot.php',
                'pluggy_connect_token.php',
                'pluggy_sync.php',
                'pluggy_item_save.php',
                'pluggy_eventos_processar.php'
            ],
            true
        ) || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            [
                'ok' => false,
                'erro' => $mensagem,
                'message' => $mensagem,
                'resposta' => $mensagem,
                'modo' => 'erro',
                'retry_after' => $espera
            ],
            JSON_UNESCAPED_UNICODE
        );
    } else {
        header('Content-Type: text/html; charset=utf-8');
        $tema = ($_SESSION['tema_preferido'] ?? '') === 'dark' ? 'dark' : 'light';
        echo '<!doctype html><html lang="pt-BR" data-theme="' .
            $tema .
            '"><meta charset="utf-8">' .
            '<meta name="viewport" content="width=device-width,initial-scale=1">' .
            '<title>CashPilot</title><link rel="stylesheet" href="../assets/css/style.css">' .
            '<link rel="stylesheet" href="../assets/css/theme.css">' .
            '<main class="surface-card" style="max-width:40rem;margin:12vh auto;padding:1.5rem;font-family:system-ui">' .
            '<h1>CashPilot</h1><p>' .
            htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') .
            '</p><p>Volte à página anterior e tente novamente quando indicado.</p></main></html>';
    }
    exit();
}

function cp17LimitarAcao(string $acao): void
{
    $regras = cp17Configuracao()['acoes'];
    if (!isset($regras[$acao])) {
        return;
    }
    [$curto, $segundos, $diario, $dia] = $regras[$acao];
    $uid = (int) ($_SESSION['usuario_id'] ?? 0);
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'local'); // Não confiar em X-Forwarded-For.
    $cotas = [
        [$acao . ':ip:' . $ip, $curto * 3, $segundos],
        [$acao . ':ip:dia:' . $ip, $diario * 3, $dia]
    ];
    $sujeito = $uid > 0 ? 'u:' . $uid : 'ip:' . $ip;
    $cotas[] = [$acao . ':' . $sujeito, $curto, $segundos];
    $cotas[] = [$acao . ':dia:' . $sujeito, $diario, $dia];
    try {
        require_once __DIR__ . '/../database/conexao.php';
        $espera = cp17ConsumirLimites(conectar(), $cotas);
        if ($espera > 0) {
            cp17Interromper(
                429,
                'Limite temporário atingido. Aguarde ' .
                    (int) ceil($espera / 60) .
                    ' minuto(s) antes de tentar novamente.',
                $espera
            );
        }
    } catch (Throwable $erro) {
        cp17Log('Limites', $erro);
        cp17Interromper(
            503,
            'Este recurso está temporariamente indisponível. Peça ao responsável para conferir a atualização do sistema.'
        );
    }
}

function cp17TokenOperacao(): string
{
    $token = bin2hex(random_bytes(24));
    $assinatura = hash_hmac('sha256', $token, csrfToken());
    return $token . '.' . $assinatura;
}

function cp17ValidarOperacao(string $token): bool
{
    $partes = explode('.', $token);
    return count($partes) === 2 &&
        preg_match('/^[a-f0-9]{48}$/D', $partes[0]) === 1 &&
        hash_equals(hash_hmac('sha256', $partes[0], csrfToken()), $partes[1]);
}

/** Deve executar DENTRO da mesma transação que salva os dados financeiros. */
function cp17ReservarOperacao(PDO $pdo, int $uid, string $contexto, string $identificador): bool
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Idempotência exige transação.');
    }
    try {
        $s = $pdo->prepare(
            'INSERT INTO cp_operacoes (usuario_id, chave, contexto) VALUES (?, ?, ?)'
        );
        $s->execute([$uid, hash('sha256', $contexto . ':' . $identificador), $contexto]);
        return true;
    } catch (PDOException $erro) {
        if ((int) ($erro->errorInfo[1] ?? 0) === 1062) {
            return false;
        }
        throw $erro;
    }
}

function cp17IniciarFormulario(PDO $pdo, int $uid, string $contexto): bool
{
    $token = $_POST['operacao_token'] ?? '';
    if (!is_string($token) || !cp17ValidarOperacao($token)) {
        throw new InvalidArgumentException('Atualize a página antes de salvar esta operação.');
    }
    $pdo->beginTransaction();
    if (!cp17ReservarOperacao($pdo, $uid, $contexto, $token)) {
        $pdo->rollBack();
        return false;
    }
    return true;
}

function cp17PermiteIA(PDO $pdo, int $uid): bool
{
    $s = $pdo->prepare(
        "SELECT ia_externa
         FROM cp_privacidade
         WHERE usuario_id = ?
           AND versao_aviso = 'aceite_copiloto'"
    );
    $s->execute([$uid]);
    return (int) $s->fetchColumn() === 1;
}

/** Circuit breaker: pausa persistente após 429/5xx, sem sleep em PHP. */
function cp17EsperaProvedor(PDO $pdo, string $provedor): int
{
    $s = $pdo->prepare('SELECT expira_em FROM cp_limites WHERE chave = ?');
    $s->execute([hash_hmac('sha256', 'pausa:' . $provedor, cp17ChaveSeguranca())]);
    return max(0, (int) $s->fetchColumn() - time());
}

function cp17PausarProvedor(PDO $pdo, string $provedor, int $segundos): void
{
    $s = $pdo->prepare(
        'INSERT INTO cp_limites (chave, quantidade, expira_em) VALUES (?, 0, ?)
         ON DUPLICATE KEY UPDATE expira_em = GREATEST(expira_em, VALUES(expira_em))'
    );
    $s->execute([
        hash_hmac('sha256', 'pausa:' . $provedor, cp17ChaveSeguranca()),
        time() + max(5, min(900, $segundos))
    ]);
}
