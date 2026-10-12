<?php
/**
 * Cadastra uma assinatura global da Pluggy para a URL pública do CashPilot.
 * Execute somente no servidor de produção: php scripts/configurar_webhook_pluggy.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/../includes/pluggy_service.php';

try {
    $url = pluggyWebhookUrl();
    $segredo = pluggyEnv('PLUGGY_WEBHOOK_TOKEN');
    if ($url === null || $segredo === '') {
        throw new RuntimeException(
            'Defina CASHPILOT_PUBLIC_URL com HTTPS e PLUGGY_WEBHOOK_TOKEN antes de continuar.'
        );
    }

    $apiKey = pluggyApiKey(true);
    $existentes = pluggyRequest('GET', '/webhooks', null, $apiKey);
    foreach ($existentes['results'] ?? [] as $webhook) {
        if (is_array($webhook) && (string) ($webhook['url'] ?? '') === $url) {
            echo '[OK] O webhook desta URL já está cadastrado.' . PHP_EOL;
            echo 'Se o segredo mudou, exclua a assinatura antiga na Pluggy e execute novamente.' .
                PHP_EOL;
            exit(0);
        }
    }

    pluggyRequest(
        'POST',
        '/webhooks',
        [
            'url' => $url,
            'event' => 'all',
            'headers' => [
                'X-CashPilot-Webhook-Token' => $segredo
            ]
        ],
        $apiKey
    );

    echo '[OK] Webhook global da Pluggy cadastrado com autenticação por cabeçalho.' . PHP_EOL;
} catch (Throwable $erro) {
    $requestId = $erro instanceof PluggyApiException ? $erro->requestId() : null;
    fwrite(
        STDERR,
        '[FALHA] ' . pluggyMensagemPublica($erro) .
            ($requestId ? ' Request ID: ' . $requestId : '') . PHP_EOL
    );
    exit(1);
}

