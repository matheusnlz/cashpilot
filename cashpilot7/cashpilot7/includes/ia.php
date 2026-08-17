<?php
/**
 * CashPilot - Camada central de Inteligência Artificial
 *
 * Provedor atual: Groq
 * Mantém a integração isolada do restante do sistema para facilitar
 * uma eventual troca de provedor no futuro sem reescrever o chatbot.
 */

function configuracaoIA(): array
{
    $config = [
        'provider' => 'groq',
        'api_key' => getenv('GROQ_API_KEY') ?: '',
        'model' => getenv('GROQ_MODEL') ?: 'llama-3.3-70b-versatile',
        'timeout' => 30,
    ];

    // Fallback opcional para ambiente local/XAMPP caso o Apache não enxergue
    // a variável de ambiente do Windows. O arquivo real fica fora do Git.
    $arquivoLocal = __DIR__ . '/groq_config.php';
    if (is_file($arquivoLocal)) {
        $local = require $arquivoLocal;
        if (is_array($local)) {
            $config = array_merge($config, $local);
        }
    }

    return $config;
}

function chaveIAConfigurada(): bool
{
    $config = configuracaoIA();
    $chave = trim((string) ($config['api_key'] ?? ''));

    return $chave !== '' && $chave !== 'COLE_SUA_CHAVE_AQUI';
}

/**
 * Envia uma conversa ao Groq usando o endpoint compatível com Chat Completions.
 * Retorna apenas o texto da resposta ou null em caso de indisponibilidade.
 */
function enviarParaIA(array $mensagens): ?string
{
    $config = configuracaoIA();
    $apiKey = trim((string) ($config['api_key'] ?? ''));

    if ($apiKey === '' || $apiKey === 'COLE_SUA_CHAVE_AQUI' || !function_exists('curl_init')) {
        return null;
    }

    $payload = [
        'model' => $config['model'] ?: 'llama-3.3-70b-versatile',
        'messages' => $mensagens,
        'temperature' => 0.35,
        'max_completion_tokens' => 900,
    ];

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($jsonPayload === false) {
        return null;
    }

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => (int) ($config['timeout'] ?? 30),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $jsonPayload,
    ]);

    $resposta = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch);
    curl_close($ch);

    if ($resposta === false || $status < 200 || $status >= 300) {
        // O usuário recebe o fallback local; detalhes técnicos ficam no log do PHP.
        if ($erroCurl !== '') {
            error_log('CashPilot/Groq cURL: ' . $erroCurl);
        } elseif (is_string($resposta) && $resposta !== '') {
            error_log('CashPilot/Groq HTTP ' . $status . ': ' . mb_substr($resposta, 0, 500));
        }
        return null;
    }

    $dados = json_decode($resposta, true);
    if (!is_array($dados)) {
        return null;
    }

    $texto = $dados['choices'][0]['message']['content'] ?? null;
    return is_string($texto) && trim($texto) !== '' ? trim($texto) : null;
}
