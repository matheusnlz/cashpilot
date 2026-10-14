<?php
/**
 * Carrega configurações do CashPilot sem dependências externas.
 *
 * Ordem de prioridade:
 * 1. Variáveis definidas pelo servidor (Railway, Apache ou sistema operacional);
 * 2. Arquivo privado externo ao projeto;
 * 3. Arquivo .env local, que nunca deve ser enviado no ZIP ou ao Git.
 */
date_default_timezone_set('America/Sao_Paulo');

function cashpilotVariaveisPermitidas(): array
{
    return [
        'CASHPILOT_ENV',
        'CASHPILOT_PERFORMANCE_LOG',
        'CASHPILOT_SECURITY_KEY',
        'CASHPILOT_PRIVACY_EMAIL',
        'CASHPILOT_IA_EXTERNAL_ENABLED',
        'CASHPILOT_PYTHON_PATH',
        'CASHPILOT_PUBLIC_URL',
        'CASHPILOT_SECRETS_FILE',
        'DB_HOST',
        'DB_PORT',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
        'MYSQLHOST',
        'MYSQLPORT',
        'MYSQLDATABASE',
        'MYSQLUSER',
        'MYSQLPASSWORD',
        'BREVO_API_KEY',
        'CASHMAIL_FROM_EMAIL',
        'CASHMAIL_FROM_NAME',
        'GOOGLE_CLIENT_ID',
        'GROQ_API_KEY',
        'GROQ_MODEL',
        'GROQ_FALLBACK_MODEL',
        'PLUGGY_API_URL',
        'PLUGGY_CLIENT_ID',
        'PLUGGY_CLIENT_SECRET',
        'PLUGGY_INCLUDE_SANDBOX',
        'PLUGGY_WEBHOOK_TOKEN'
    ];
}

function cashpilotDefinirAmbiente(string $nome, $valor): void
{
    if (!in_array($nome, cashpilotVariaveisPermitidas(), true)) {
        return;
    }

    if (getenv($nome) !== false || (!is_scalar($valor) && $valor !== null)) {
        return;
    }

    if (is_bool($valor)) {
        $valor = $valor ? 'true' : 'false';
    }

    $valor = trim((string) $valor);
    if ($valor === '') {
        return;
    }

    putenv($nome . '=' . $valor);
    $_ENV[$nome] = $valor;
}

function cashpilotCarregarArquivoAmbiente(string $arquivo): void
{
    if (!is_readable($arquivo)) {
        return;
    }

    foreach (file($arquivo, FILE_IGNORE_NEW_LINES) ?: [] as $linha) {
        $linha = trim($linha);
        if ($linha === '' || str_starts_with($linha, '#') || !str_contains($linha, '=')) {
            continue;
        }

        [$nome, $valor] = explode('=', $linha, 2);
        $nome = trim($nome);
        $valor = trim($valor);

        if (
            strlen($valor) >= 2 &&
            (($valor[0] === '"' && str_ends_with($valor, '"')) ||
                ($valor[0] === "'" && str_ends_with($valor, "'")))
        ) {
            $valor = substr($valor, 1, -1);
        }

        // Os valores são literais: comandos e variáveis não são expandidos.
        cashpilotDefinirAmbiente($nome, $valor);
    }
}

function cashpilotCaminhoSegredosExternos(): string
{
    $configurado = getenv('CASHPILOT_SECRETS_FILE');
    if (is_string($configurado) && trim($configurado) !== '') {
        return trim($configurado);
    }

    // No XAMPP: C:\xampp\cashpilot-private\secrets.php.
    $raizProjeto = dirname(__DIR__);
    $raizServidor = dirname($raizProjeto, 2);

    return $raizServidor .
        DIRECTORY_SEPARATOR .
        'cashpilot-private' .
        DIRECTORY_SEPARATOR .
        'secrets.php';
}

function cashpilotCarregarSegredosExternos(): void
{
    $arquivo = cashpilotCaminhoSegredosExternos();
    if (!is_file($arquivo) || !is_readable($arquivo)) {
        return;
    }

    $arquivoReal = realpath($arquivo);
    $raizReal = realpath(dirname(__DIR__));
    if ($arquivoReal === false || $raizReal === false) {
        return;
    }

    $prefixoProjeto = rtrim($raizReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (str_starts_with($arquivoReal, $prefixoProjeto)) {
        error_log('CashPilot/Config: o arquivo de segredos deve ficar fora do projeto.');
        return;
    }

    $configuracao = require $arquivoReal;
    if (!is_array($configuracao)) {
        error_log('CashPilot/Config: o arquivo privado deve retornar um array.');
        return;
    }

    foreach ($configuracao as $nome => $valor) {
        if (is_string($nome)) {
            cashpilotDefinirAmbiente($nome, $valor);
        }
    }
}

cashpilotCarregarSegredosExternos();
cashpilotCarregarArquivoAmbiente(dirname(__DIR__) . '/.env');

require_once __DIR__ . '/desempenho.php';
cpPerfIniciar();
