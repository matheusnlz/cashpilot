<?php
/** Diagnostico opcional, sem SQL, parametros, credenciais ou dados pessoais nos logs. */
function cpPerfAtivo(): bool
{
    return isset($GLOBALS['cashpilotPerfEstado']);
}

function cpPerfIniciar(): void
{
    if (cpPerfAtivo()) {
        return;
    }
    if (!in_array(strtolower(trim((string) getenv('CASHPILOT_PERFORMANCE_LOG'))), ['1', 'true', 'yes', 'on'], true)) {
        return;
    }
    $agora = hrtime(true);
    $rota = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? 'cli.php'));
    $rota = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $rota);
    $GLOBALS['cashpilotPerfEstado'] = [
        'id' => uniqid('req_', false), 'rota' => $rota,
        'inicio' => $agora, 'etapa_inicio' => $agora, 'etapa' => 'inicio',
        'db_ms' => 0.0, 'conexao_ms' => 0.0, 'sessao_ms' => 0.0,
        'consultas' => 0, 'preparacoes' => 0, 'transacoes' => 0, 'falhas_db' => 0
    ];
    cpPerfLog('inicio');
    register_shutdown_function(function (): void {
        cpPerfRegistrarEtapa();
        $s = $GLOBALS['cashpilotPerfEstado'];
        // REQUEST_TIME_FLOAT inclui o tempo anterior ao carregamento do helper.
        $total = isset($_SERVER['REQUEST_TIME_FLOAT'])
            ? max(0, (microtime(true) - (float) $_SERVER['REQUEST_TIME_FLOAT']) * 1000)
            : (hrtime(true) - $s['inicio']) / 1000000;
        $erro = error_get_last();
        $fatal = $erro !== null && in_array($erro['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
        cpPerfLog(sprintf(
            'resumo total=%.1f ms banco=%.1f ms conexao=%.1f ms sessao=%.1f ms restante=%.1f ms consultas=%d preparacoes=%d transacoes=%d falhas_db=%d http=%d fatal=%d',
            $total, $s['db_ms'], $s['conexao_ms'], $s['sessao_ms'],
            max(0, $total - $s['db_ms'] - $s['sessao_ms']),
            $s['consultas'], $s['preparacoes'], $s['transacoes'], $s['falhas_db'], http_response_code() ?: 200, $fatal ? 1 : 0
        ));
    });
}

function cpPerfLog(string $texto): void
{
    if (cpPerfAtivo()) {
        $s = $GLOBALS['cashpilotPerfEstado'];
        error_log('CashPilot/Desempenho [' . $s['id'] . '] pagina=' . $s['rota'] . ' ' . $texto);
    }
}

function cpPerfRegistrarEtapa(): void
{
    if (cpPerfAtivo()) {
        $s = $GLOBALS['cashpilotPerfEstado'];
        cpPerfLog(sprintf('etapa=%s tempo=%.1f ms', $s['etapa'], (hrtime(true) - $s['etapa_inicio']) / 1000000));
    }
}

function cpPerfEtapa(string $etapa): void
{
    if (cpPerfAtivo()) {
        cpPerfRegistrarEtapa();
        $GLOBALS['cashpilotPerfEstado']['etapa'] = $etapa;
        $GLOBALS['cashpilotPerfEstado']['etapa_inicio'] = hrtime(true);
    }
}

function cpPerfOrigem(): string
{
    $raiz = str_replace('\\', '/', dirname(__DIR__)) . '/';
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 12) as $frame) {
        $file = str_replace('\\', '/', (string) ($frame['file'] ?? ''));
        if ($file === str_replace('\\', '/', __FILE__) || $file === $raiz . 'database/conexao.php') {
            continue;
        }
        if (str_starts_with($file, $raiz)) {
            return substr($file, strlen($raiz)) . ':' . (int) ($frame['line'] ?? 0);
        }
    }
    return 'database/conexao.php';
}

function cpPerfMedir(string $operacao, callable $executar, string $sql = ''): mixed
{
    if (!cpPerfAtivo()) {
        return $executar();
    }
    $inicio = hrtime(true);
    $ok = false;
    try {
        $resultado = $executar();
        $ok = $resultado !== false;
        return $resultado;
    } finally {
        $ms = (hrtime(true) - $inicio) / 1000000;
        $s = &$GLOBALS['cashpilotPerfEstado'];
        if ($operacao === 'sessao') {
            $s['sessao_ms'] += $ms;
        } else {
            $s['db_ms'] += $ms;
            if ($operacao === 'conexao') {
                $s['conexao_ms'] += $ms;
            } elseif ($operacao === 'prepare') {
                $s['preparacoes']++;
            } elseif (in_array($operacao, ['begin', 'commit', 'rollback'], true)) {
                $s['transacoes']++;
            } else {
                $s['consultas']++;
            }
            if (!$ok) {
                $s['falhas_db']++;
            }
        }
        cpPerfLog(sprintf(
            'operacao=%s tempo=%.1f ms ok=%d origem=%s sql_id=%s',
            $operacao, $ms, $ok ? 1 : 0, cpPerfOrigem(),
            $sql === '' ? '-' : substr(hash('sha256', $sql), 0, 12)
        ));
    }
}

class CashPilotPerfStatement extends PDOStatement
{
    protected function __construct() {}

    public function execute(?array $params = null): bool
    {
        return cpPerfMedir('execute', fn() => parent::execute($params), $this->queryString);
    }
}

class CashPilotPerfPDO extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return cpPerfMedir('prepare', fn() => parent::prepare($query, $options), $query);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return cpPerfMedir('query', fn() => $fetchMode === null
            ? parent::query($query)
            : parent::query($query, $fetchMode, ...$fetchModeArgs), $query);
    }

    public function exec(string $statement): int|false
    {
        return cpPerfMedir('exec', fn() => parent::exec($statement), $statement);
    }

    public function beginTransaction(): bool
    {
        return cpPerfMedir('begin', fn() => parent::beginTransaction());
    }

    public function commit(): bool
    {
        return cpPerfMedir('commit', fn() => parent::commit());
    }

    public function rollBack(): bool
    {
        return cpPerfMedir('rollback', fn() => parent::rollBack());
    }
}
    