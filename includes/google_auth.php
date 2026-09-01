<?php

function cpGoogleClientId(): string
 {

        $local = [];

        $arquivo = __DIR__ . '/oauth_config.php';

        if (is_file($arquivo)) {

                $config = require $arquivo;

                if (is_array($config)) {

                        $local = $config;

        }

    }

        $ambiente = getenv('GOOGLE_CLIENT_ID');

        return trim((string) (
            (is_string($ambiente) && $ambiente !== '' ? $ambiente : null)
            ?? $local['google_client_id']
            ?? ''
        ));

}

/**
 * Valida a credencial emitida pelo Google Identity Services.
 *
 * Para o ambiente atual do TCC, a validação usa o endpoint tokeninfo do
 * próprio Google e ainda confere audience, issuer, expiração e e-mail.
 */
function cpGoogleVerificarCredential(string $credential): array
 {

        $clientId = cpGoogleClientId();

        if ($clientId === '') {

                return [
                    'ok' => false,
                    'erro' => 'Google Login ainda não foi configurado neste ambiente.',
                ];

    }

        $credential = trim($credential);

        if ($credential === '') {

                return ['ok' => false, 'erro' => 'Credencial Google ausente.'];

    }

        if (!function_exists('curl_init')) {

                return [
                    'ok' => false,
                    'erro' => 'O PHP cURL precisa estar habilitado para entrar com Google.',
                ];

    }

        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($credential);

        $curl = curl_init($url);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($curl);

        $erroCurl = curl_error($curl);

        $http = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($body === false || $http !== 200) {

                error_log(
                    'CashPilot/Google: tokeninfo HTTP ' . $http .
                    ($erroCurl !== '' ? ' - ' . $erroCurl : '')
                );

                return [
                    'ok' => false,
                    'erro' => 'Não foi possível validar sua conta Google. Tente novamente.',
                ];

    }

        $dados = json_decode($body, true);

        if (!is_array($dados)) {

                return ['ok' => false, 'erro' => 'Resposta de autenticação Google inválida.'];

    }

        $issuer = (string) ($dados['iss'] ?? '');

        $issuerValido = in_array(
            $issuer,
            ['accounts.google.com', 'https://accounts.google.com'],
            true
        );

        if (!$issuerValido) {

                return ['ok' => false, 'erro' => 'Emissor da credencial Google inválido.'];

    }

        if (!hash_equals($clientId, (string) ($dados['aud'] ?? ''))) {

                return [
                    'ok' => false,
                    'erro' => 'Esta credencial Google não pertence ao CashPilot.',
                ];

    }

        if ((int) ($dados['exp'] ?? 0) <= time()) {

                return [
                    'ok' => false,
                    'erro' => 'A sessão do Google expirou. Entre novamente.',
                ];

    }

        $emailVerificado = filter_var(
            $dados['email_verified'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $sub = trim((string) ($dados['sub'] ?? ''));

        $email = mb_strtolower(trim((string) ($dados['email'] ?? '')));

        if ($sub === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

                return [
                    'ok' => false,
                    'erro' => 'O Google não retornou os dados necessários para entrar.',
                ];

    }

        if (!$emailVerificado) {

                return [
                    'ok' => false,
                    'erro' => 'O Google não confirmou este endereço de e-mail.',
                ];

    }

        return [
            'ok' => true,
            'erro' => null,
            'sub' => $sub,
            'email' => $email,
            'nome' => trim((string) ($dados['name'] ?? '')),
            'foto' => trim((string) ($dados['picture'] ?? '')),
        ];

}
