<?php

/**
 * Integração central com a Groq.
 *
 * O CashPilot usa PHP + cURL para conversar com a API. A chave deve ficar em
 * GROQ_API_KEY ou, apenas no ambiente local, em includes/groq_config.php.
 */

function obterVariavelAmbienteIA(string $nome): string
 {

        $candidatos = [];

        $valorGetenv = getenv($nome);

        if ($valorGetenv !== false) {

                $candidatos[] = $valorGetenv;

    }

        if (isset($_ENV[$nome])) {

                $candidatos[] = $_ENV[$nome];

    }

        if (isset($_SERVER[$nome])) {

                $candidatos[] = $_SERVER[$nome];

    }

        if (function_exists('apache_getenv')) {

                $valorApache = @apache_getenv($nome, true);

                if (is_string($valorApache)) {

                        $candidatos[] = $valorApache;

        }

    }

        foreach ($candidatos as $valor) {

                $valor = trim((string) $valor);

                if ($valor !== '') {

                        return $valor;

        }

    }

        return '';

}

function modeloIADescontinuado(string $modelo): bool
 {

        return in_array(
            trim($modelo),
            [
                'llama-3.3-70b-versatile',
                'llama-3.1-8b-instant',
            ],
            true
        );

}

function configuracaoIA(): array
 {

        $modeloAmbiente = obterVariavelAmbienteIA('GROQ_MODEL');

        $fallbackAmbiente = obterVariavelAmbienteIA('GROQ_FALLBACK_MODEL');

        $config = [
            'provider' => 'groq',
            'api_key' => obterVariavelAmbienteIA('GROQ_API_KEY'),
            'model' => $modeloAmbiente !== ''
                ? $modeloAmbiente
                : 'openai/gpt-oss-120b',
            'fallback_model' => $fallbackAmbiente !== ''
                ? $fallbackAmbiente
                : 'openai/gpt-oss-20b',
            'connect_timeout' => 8,
            'timeout' => 45,
            'max_completion_tokens' => 1400,
            'temperature' => 0.2,
        ];

        $arquivoLocal = __DIR__ . '/groq_config.php';

        if (is_file($arquivoLocal)) {

                $configLocal = require $arquivoLocal;

                if (is_array($configLocal)) {

                        $config = array_merge($config, $configLocal);

        }

    }

        /**
     * Migração automática de configurações antigas.
     * Isso evita que uma variável GROQ_MODEL antiga continue derrubando o
     * Copiloto depois da atualização do projeto.
     */
        if (modeloIADescontinuado((string) ($config['model'] ?? ''))) {

                $config['model'] = 'openai/gpt-oss-120b';

    }

        if (
            modeloIADescontinuado((string) ($config['fallback_model'] ?? '')) ||
            ($config['fallback_model'] ?? '') === ($config['model'] ?? '')
        ) {

                $config['fallback_model'] = 'openai/gpt-oss-20b';

    }

        return $config;

}

function chaveIAConfigurada(): bool
 {

        $config = configuracaoIA();

        $chave = trim((string) ($config['api_key'] ?? ''));

        return $chave !== '' && $chave !== 'COLE_SUA_CHAVE_AQUI';

}

function extrairTextoRespostaGroq(array $dados): ?string
 {

        $conteudo = $dados['choices'][0]['message']['content'] ?? null;

        if (is_string($conteudo)) {

                $conteudo = trim($conteudo);

                return $conteudo !== '' ? $conteudo : null;

    }

        /**
     * Compatibilidade preventiva caso o provider retorne conteúdo em partes.
     */
        if (is_array($conteudo)) {

                $partes = [];

                foreach ($conteudo as $parte) {

                        if (is_string($parte)) {

                                $partes[] = $parte;

                                continue;

            }

                        if (is_array($parte) && isset($parte['text'])) {

                                $partes[] = (string) $parte['text'];

            }

        }

                $texto = trim(implode("\n", $partes));

                return $texto !== '' ? $texto : null;

    }

        return null;

}

function requisicaoGroqDetalhada(
    array $mensagens,
    string $modelo,
    array $config
): array {

        $inicio = microtime(true);

        $chave = trim((string) ($config['api_key'] ?? ''));

        $resultadoBase = [
            'ok' => false,
            'provider' => 'groq',
            'model' => $modelo,
            'http_status' => null,
            'curl_error' => null,
            'api_error' => null,
            'response' => null,
            'elapsed' => 0.0,
        ];

        if ($chave === '' || $chave === 'COLE_SUA_CHAVE_AQUI') {

                $resultadoBase['api_error'] = 'GROQ_API_KEY não encontrada pelo PHP.';

                return $resultadoBase;

    }

        if (!function_exists('curl_init')) {

                $resultadoBase['api_error'] = 'A extensão cURL do PHP não está disponível.';

                return $resultadoBase;

    }

        $payload = [
            'model' => $modelo,
            'messages' => $mensagens,
            'temperature' => (float) ($config['temperature'] ?? 0.2),
            'max_completion_tokens' => (int) ($config['max_completion_tokens'] ?? 1400),
            'stream' => false,
        ];

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {

                $resultadoBase['api_error'] = 'Não foi possível montar o JSON da requisição.';

                return $resultadoBase;

    }

        $curl = curl_init('https://api.groq.com/openai/v1/chat/completions');

        curl_setopt_array(
            $curl,
            [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => (int) ($config['connect_timeout'] ?? 8),
                CURLOPT_TIMEOUT => (int) ($config['timeout'] ?? 45),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $chave,
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
                CURLOPT_POSTFIELDS => $json,
            ]
        );

        $respostaBruta = curl_exec($curl);

        $statusHttp = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        $erroCurl = curl_error($curl);

        curl_close($curl);

        $resultadoBase['elapsed'] = microtime(true) - $inicio;

        $resultadoBase['http_status'] = $statusHttp ?: null;

        $resultadoBase['curl_error'] = $erroCurl !== '' ? $erroCurl : null;

        if ($respostaBruta === false) {

                error_log(
                    'CashPilot/Groq cURL [' . $modelo . ']: ' .
                    ($erroCurl !== '' ? $erroCurl : 'erro desconhecido')
                );

                return $resultadoBase;

    }

        $dados = json_decode((string) $respostaBruta, true);

        if ($statusHttp < 200 || $statusHttp >= 300) {

                $mensagemApi = null;

                if (is_array($dados)) {

                        $mensagemApi = $dados['error']['message'] ?? $dados['message'] ?? null;

        }

                $resultadoBase['api_error'] = is_string($mensagemApi)
                    ? $mensagemApi
                    : 'A Groq recusou a requisição.';

                error_log(
                    'CashPilot/Groq HTTP ' . $statusHttp .
                    ' [' . $modelo . ']: ' .
                    mb_substr((string) $respostaBruta, 0, 900)
                );

                return $resultadoBase;

    }

        if (!is_array($dados)) {

                $resultadoBase['api_error'] = 'A Groq retornou uma resposta JSON inválida.';

                return $resultadoBase;

    }

        $texto = extrairTextoRespostaGroq($dados);

        if ($texto === null) {

                $resultadoBase['api_error'] = 'O modelo respondeu sem conteúdo de texto.';

                return $resultadoBase;

    }

        $resultadoBase['ok'] = true;

        $resultadoBase['response'] = $texto;

        return $resultadoBase;

}

function deveTentarFallbackIA(array $resultado): bool
 {

        if (!empty($resultado['ok'])) {

                return false;

    }

        $status = (int) ($resultado['http_status'] ?? 0);

        /**
     * 401/403 normalmente significam problema de chave/permissão e seriam
     * repetidos no segundo modelo. Nos demais erros, o fallback pode ajudar.
     */
        if (in_array($status, [401, 403], true)) {

                return false;

    }

        return true;

}

function enviarParaIADetalhado(array $mensagens): array
 {

        $config = configuracaoIA();

        if (!chaveIAConfigurada()) {

                return [
                    'ok' => false,
                    'provider' => 'groq',
                    'model' => (string) ($config['model'] ?? ''),
                    'used_fallback' => false,
                    'primary' => null,
                    'fallback' => null,
                    'response' => null,
                    'error' => 'GROQ_API_KEY não encontrada pelo PHP.',
                ];

    }

        $modeloPrincipal = (string) ($config['model'] ?? 'openai/gpt-oss-120b');

        $modeloFallback = (string) ($config['fallback_model'] ?? 'openai/gpt-oss-20b');

        $principal = requisicaoGroqDetalhada(
            $mensagens,
            $modeloPrincipal,
            $config
        );

        if (!empty($principal['ok'])) {

                return [
                    'ok' => true,
                    'provider' => 'groq',
                    'model' => $modeloPrincipal,
                    'used_fallback' => false,
                    'primary' => $principal,
                    'fallback' => null,
                    'response' => $principal['response'],
                    'error' => null,
                ];

    }

        $fallback = null;

        if (
            $modeloFallback !== '' &&
            $modeloFallback !== $modeloPrincipal &&
            deveTentarFallbackIA($principal)
        ) {

                $fallback = requisicaoGroqDetalhada(
                    $mensagens,
                    $modeloFallback,
                    $config
                );

                if (!empty($fallback['ok'])) {

                        return [
                            'ok' => true,
                            'provider' => 'groq',
                            'model' => $modeloFallback,
                            'used_fallback' => true,
                            'primary' => $principal,
                            'fallback' => $fallback,
                            'response' => $fallback['response'],
                            'error' => null,
                        ];

        }

    }

        $erro = $fallback['api_error']
            ?? $fallback['curl_error']
            ?? $principal['api_error']
            ?? $principal['curl_error']
            ?? 'Não foi possível consultar os modelos configurados.';

        return [
            'ok' => false,
            'provider' => 'groq',
            'model' => $modeloPrincipal,
            'used_fallback' => false,
            'primary' => $principal,
            'fallback' => $fallback,
            'response' => null,
            'error' => $erro,
        ];

}

function enviarParaIA(array $mensagens): ?string
 {

        $resultado = enviarParaIADetalhado($mensagens);

        if (empty($resultado['ok'])) {

                return null;

    }

        $texto = $resultado['response'] ?? null;

        return is_string($texto) && trim($texto) !== ''
            ? trim($texto)
            : null;

}
