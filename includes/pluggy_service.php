<?php
require_once __DIR__ . '/ambiente.php';

/**
 * Carrega a configuração local simplificada da Pluggy.
 *
 * O arquivo pluggy_config.php é destinado apenas ao XAMPP e permanece
 * ignorado pelo Git. Variáveis definidas pelo servidor continuam tendo
 * prioridade, permitindo usar o Railway sem alterar este código.
 */
function pluggyCarregarConfiguracaoLocal(): void
{
    $arquivo = __DIR__ . '/pluggy_config.php';
    if (!is_file($arquivo) || !is_readable($arquivo)) {
        return;
    }

    $configuracao = require $arquivo;
    if (!is_array($configuracao)) {
        error_log('CashPilot/Pluggy: pluggy_config.php deve retornar um array.');
        return;
    }

    $mapeamento = [
        'client_id' => 'PLUGGY_CLIENT_ID',
        'client_secret' => 'PLUGGY_CLIENT_SECRET',
        'include_sandbox' => 'PLUGGY_INCLUDE_SANDBOX',
        'public_url' => 'CASHPILOT_PUBLIC_URL',
        'webhook_token' => 'PLUGGY_WEBHOOK_TOKEN'
    ];

    foreach ($mapeamento as $chaveLocal => $variavelAmbiente) {
        if (!array_key_exists($chaveLocal, $configuracao)) {
            continue;
        }

        cashpilotDefinirAmbiente($variavelAmbiente, $configuracao[$chaveLocal]);
    }
}

pluggyCarregarConfiguracaoLocal();

class PluggyApiException extends RuntimeException
{
    private ?string $requestId;
    private ?int $retryAfter;

    public function __construct(
        string $message,
        int $code = 0,
        ?string $requestId = null,
        ?int $retryAfter = null
    ) {
        parent::__construct($message, $code);
        $this->requestId = $requestId;
        $this->retryAfter = $retryAfter;
    }

    public function requestId(): ?string
    {
        return $this->requestId;
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}

/**
 * Integração server-side com a Pluggy, introduzida no CashPilot 16.2.
 *
 * As credenciais podem ser lidas do ambiente do servidor, do arquivo privado
 * externo ou do pluggy_config.php local. Nenhuma credencial é enviada ao
 * navegador.
 */

function pluggyEnv(string $nome, string $padrao = ''): string
{
    $valor = getenv($nome);
    if (is_string($valor) && trim($valor) !== '') {
        return trim($valor);
    }

    return $padrao;
}

function pluggyConfigurado(): bool
{
    foreach (['PLUGGY_CLIENT_ID', 'PLUGGY_CLIENT_SECRET'] as $nome) {
        $valor = pluggyEnv($nome);
        if ($valor === '' || str_starts_with(strtoupper($valor), 'COLE_')) {
            return false;
        }
    }

    return true;
}

function pluggyDiagnosticoSeguro(): array
{
    return [
        'configurado' => pluggyConfigurado(),
        'client_id' => pluggyEnv('PLUGGY_CLIENT_ID') !== '',
        'client_secret' => pluggyEnv('PLUGGY_CLIENT_SECRET') !== '',
        'curl' => function_exists('curl_init'),
        'sandbox' => pluggySandboxAtivo()
    ];
}

function pluggyMensagemPublica(Throwable $erro): string
{
    $codigo = (int) $erro->getCode();

    if (!pluggyConfigurado()) {
        return 'A conexão bancária ainda não foi configurada neste ambiente.';
    }

    if ($codigo === 401 || $codigo === 403) {
        return 'Não foi possível autenticar a integração bancária. Verifique a configuração.';
    }

    if ($codigo === 429) {
        $espera = $erro instanceof PluggyApiException ? $erro->retryAfter() : null;
        if ($espera !== null && $espera > 0) {
            return 'A instituição limitou novas atualizações. Tente novamente em aproximadamente ' .
                $espera .
                ' segundo(s).';
        }

        return 'A instituição limitou novas atualizações. Aguarde antes de tentar novamente.';
    }

    if ($codigo >= 500) {
        return 'O serviço de conexão bancária está temporariamente indisponível.';
    }

    return 'Não foi possível concluir a operação bancária. Tente novamente.';
}

function pluggySandboxAtivo(): bool
{
    $valor = strtolower(pluggyEnv('PLUGGY_INCLUDE_SANDBOX', 'true'));

    return !in_array($valor, ['0', 'false', 'no', 'off'], true);
}

function pluggyBaseUrl(): string
{
    return rtrim(pluggyEnv('PLUGGY_API_URL', 'https://api.pluggy.ai'), '/');
}

function pluggyRequest(
    string $metodo,
    string $caminho,
    $payload = null,
    ?string $apiKey = null
): array {
    $url =
        str_starts_with($caminho, 'http://') || str_starts_with($caminho, 'https://')
            ? $caminho
            : pluggyBaseUrl() . '/' . ltrim($caminho, '/');

    // A API Key nunca deve acompanhar uma URL externa devolvida em paginação.
    $destino = parse_url($url);
    $base = parse_url(pluggyBaseUrl());
    if (
        ($destino['scheme'] ?? '') !== 'https' ||
        ($destino['host'] ?? '') !== ($base['host'] ?? '') ||
        isset($destino['user']) ||
        isset($destino['pass']) ||
        ($destino['port'] ?? 443) !== ($base['port'] ?? 443)
    ) {
        throw new RuntimeException('Destino inválido para a integração bancária.');
    }

    $headers = ['Accept: application/json'];
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    if ($apiKey !== null && $apiKey !== '') {
        $headers[] = 'X-API-KEY: ' . $apiKey;
    }

    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensão cURL do PHP é necessária para usar o Open Finance.');
    }
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/../database/conexao.php';
    $pdoPausa = conectar();
    $espera = cp17EsperaProvedor($pdoPausa, 'pluggy');
    if ($espera > 0) {
        throw new PluggyApiException('Integração em espera temporária.', 429, null, $espera);
    }

    $cabecalhosResposta = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CUSTOMREQUEST => strtoupper($metodo),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_USERAGENT => 'CashPilot/17',
        CURLOPT_HEADERFUNCTION => static function ($curl, string $linha) use (
            &$cabecalhosResposta
        ): int {
            $tamanho = strlen($linha);
            $separador = strpos($linha, ':');
            if ($separador === false) {
                return $tamanho;
            }

            $nome = strtolower(trim(substr($linha, 0, $separador)));
            $valor = trim(substr($linha, $separador + 1));
            if ($nome !== '') {
                $cabecalhosResposta[$nome] = $valor;
            }

            return $tamanho;
        }
    ]);

    if ($payload !== null) {
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    $body = curl_exec($ch);
    $erroCurl = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Falha de comunicação com a Pluggy: ' . $erroCurl);
    }

    $dados = json_decode((string) $body, true);
    if (!is_array($dados)) {
        $dados = [];
    }

    if ($status < 200 || $status >= 300) {
        $mensagem =
            (string) ($dados['message'] ??
                ($dados['error'] ?? 'A Pluggy retornou um erro inesperado.'));
        $requestId = trim((string) ($cabecalhosResposta['x-request-id'] ?? '')) ?: null;
        $retryAfterRaw = trim((string) ($cabecalhosResposta['retry-after'] ?? ''));
        $retryAfter = ctype_digit($retryAfterRaw) ? (int) $retryAfterRaw : null;
        if ($status === 429 || $status >= 500) {
            cp17PausarProvedor($pdoPausa, 'pluggy', $retryAfter ?? ($status === 429 ? 120 : 30));
        }

        throw new PluggyApiException($mensagem, $status, $requestId, $retryAfter);
    }

    return $dados;
}

function pluggyApiKey(bool $forcarNova = false): string
{
    if (!pluggyConfigurado()) {
        throw new RuntimeException('Pluggy ainda não foi configurada neste ambiente.');
    }

    if (!$forcarNova && session_status() === PHP_SESSION_ACTIVE) {
        $token = (string) ($_SESSION['_pluggy_api_key'] ?? '');
        $expira = (int) ($_SESSION['_pluggy_api_key_expira'] ?? 0);
        if ($token !== '' && $expira > time() + 120) {
            return $token;
        }
    }

    $resposta = pluggyRequest('POST', '/auth', [
        'clientId' => pluggyEnv('PLUGGY_CLIENT_ID'),
        'clientSecret' => pluggyEnv('PLUGGY_CLIENT_SECRET')
    ]);

    $token = (string) ($resposta['apiKey'] ?? ($resposta['accessToken'] ?? ''));
    if ($token === '') {
        throw new RuntimeException('A Pluggy não retornou uma API Key válida.');
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['_pluggy_api_key'] = $token;
        $_SESSION['_pluggy_api_key_expira'] = time() + 6900;
    }

    return $token;
}

function pluggyWebhookUrl(): ?string
{
    $base = rtrim(pluggyEnv('CASHPILOT_PUBLIC_URL'), '/');
    if ($base === '' || !str_starts_with($base, 'https://')) {
        return null;
    }

    return $base . '/actions/pluggy_webhook.php';
}

function pluggyCriarConnectToken(int $usuarioId, ?string $itemId = null): array
{
    $options = [
        'clientUserId' => 'cashpilot-user-' . $usuarioId,
        'avoidDuplicates' => true
    ];

    // O webhook da 16.5 deve ser cadastrado na Pluggy com o cabeçalho secreto.
    // Não usamos webhookUrl por Item porque esse formato não envia o cabeçalho de autenticação.

    $payload = ['options' => $options];
    if ($itemId !== null && $itemId !== '') {
        $payload['itemId'] = $itemId;
    }

    return pluggyRequest('POST', '/connect_token', $payload, pluggyApiKey());
}

function pluggyObterItem(string $itemId): array
{
    return pluggyRequest('GET', '/items/' . rawurlencode($itemId), null, pluggyApiKey());
}

function pluggyListarContas(string $itemId): array
{
    $resposta = pluggyRequest(
        'GET',
        '/accounts?itemId=' . rawurlencode($itemId),
        null,
        pluggyApiKey()
    );

    return is_array($resposta['results'] ?? null) ? $resposta['results'] : [];
}

function pluggyListarTransacoes(string $accountId, ?string $dateFrom = null): array
{
    $query = ['accountId' => $accountId];
    if ($dateFrom !== null && $dateFrom !== '') {
        $query['dateFrom'] = $dateFrom;
    }

    $caminho = '/v2/transactions?' . http_build_query($query);
    $todas = [];
    $paginas = 0;
    $visitadas = [];

    while ($caminho !== '' && $paginas < 20) {
        if (isset($visitadas[$caminho])) {
            throw new RuntimeException(
                'A paginação bancária retornou uma página repetida. Tente sincronizar novamente.'
            );
        }

        $visitadas[$caminho] = true;
        $resposta = pluggyRequest('GET', $caminho, null, pluggyApiKey());
        foreach ($resposta['results'] ?? [] as $transacao) {
            if (is_array($transacao)) {
                $todas[] = $transacao;
            }
        }

        $next = (string) ($resposta['next'] ?? '');
        if ($next === '') {
            break;
        }

        if (str_starts_with($next, '?')) {
            $caminho = '/v2/transactions' . $next;
        } elseif (str_starts_with($next, '/') || str_starts_with($next, 'https://')) {
            $caminho = $next;
        } else {
            throw new RuntimeException('A integração retornou uma paginação inválida.');
        }

        $paginas++;
    }

    if ($paginas >= 20) {
        throw new RuntimeException(
            'O histórico excedeu o limite de páginas desta sincronização. ' .
                'Nenhuma conclusão de histórico completo foi registrada.'
        );
    }

    return $todas;
}

function pluggyAtualizarItem(string $itemId): array
{
    return pluggyRequest('PATCH', '/items/' . rawurlencode($itemId), (object) [], pluggyApiKey());
}

function pluggyExcluirItem(string $itemId): void
{
    pluggyRequest('DELETE', '/items/' . rawurlencode($itemId), null, pluggyApiKey());
}

function pluggyEstadoApresentacao(array $item): array
{
    $status = strtoupper(trim((string) ($item['status'] ?? '')));
    $execucao = strtoupper(trim((string) ($item['execution_status'] ?? '')));
    $estado = $execucao !== '' ? $execucao : $status;

    if ($estado === 'DELETED') {
        return [
            'classe' => 'error',
            'rotulo' => 'Desconectada',
            'descricao' => 'A instituição não está mais conectada.',
            'requer_acao' => true
        ];
    }

    if (
        str_contains($estado, 'WAITING') ||
        str_contains($estado, 'LOGIN') ||
        str_contains($estado, 'USER_ACTION') ||
        str_contains($estado, 'NEEDS_ACTION')
    ) {
        return [
            'classe' => 'warning',
            'rotulo' => 'Ação necessária',
            'descricao' => 'Abra a atualização da conexão para renovar a autorização.',
            'requer_acao' => true
        ];
    }

    if (str_contains($estado, 'ERROR') || str_contains($estado, 'FAIL')) {
        return [
            'classe' => 'error',
            'rotulo' => 'Falha na atualização',
            'descricao' => 'A instituição não concluiu a atualização. Tente reconectar.',
            'requer_acao' => true
        ];
    }

    if (str_contains($estado, 'PARTIAL')) {
        return [
            'classe' => 'warning',
            'rotulo' => 'Atualização parcial',
            'descricao' => 'Parte dos dados foi atualizada. Tente novamente mais tarde.',
            'requer_acao' => false
        ];
    }

    if (
        str_contains($estado, 'CREATED') ||
        str_contains($estado, 'UPDAT') ||
        str_contains($estado, 'PROCESS') ||
        str_contains($estado, 'EXECUT')
    ) {
        return [
            'classe' => 'processing',
            'rotulo' => 'Processando',
            'descricao' => 'A instituição ainda está preparando os dados.',
            'requer_acao' => false
        ];
    }

    return [
        'classe' => 'ok',
        'rotulo' => 'Conectada',
        'descricao' => 'Os dados disponíveis podem ser sincronizados com segurança.',
        'requer_acao' => false
    ];
}

function pluggyNormalizarData(?string $iso): ?string
{
    if (!$iso) {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $iso)) {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);

        return $data && $data->format('Y-m-d') === $iso ? $iso : null;
    }

    try {
        return (new DateTimeImmutable($iso))
            ->setTimezone(new DateTimeZone('America/Sao_Paulo'))
            ->format('Y-m-d');
    } catch (Throwable) {
        return null;
    }
}

function pluggyTipoSugerido(array $transacao, string $accountType): string
{
    $tipo = strtoupper((string) ($transacao['type'] ?? ''));
    $valor = (float) ($transacao['amount'] ?? 0);

    if (strtoupper($accountType) === 'CREDIT') {
        return $valor >= 0 ? 'despesa' : 'receita';
    }

    if ($tipo === 'CREDIT') {
        return 'receita';
    }
    if ($tipo === 'DEBIT') {
        return 'despesa';
    }

    return $valor < 0 ? 'despesa' : 'receita';
}
