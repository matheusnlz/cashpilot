<?php

/**
 * CashPilot — envio de e-mails transacionais e códigos de verificação.
 *
 * Provedor: Brevo API.
 *
 * Configure BREVO_API_KEY como variável de ambiente e reinicie o XAMPP.
 * Nunca coloque a chave real neste arquivo ou no GitHub.
 */

function cpEmailConfig(): array
 {

        $local = [];

        $arquivoLocal = __DIR__ . '/email_config.php';

        if (is_file($arquivoLocal)) {

                $config = require $arquivoLocal;

                if (is_array($config)) {

                        $local = $config;

        }

    }

        return [
            'provider' => 'brevo',
            'api_url' => $local['api_url']
                ?? getenv('BREVO_API_URL')
                ?: 'https://api.brevo.com/v3/smtp/email',
            'api_key' => $local['api_key']
                ?? getenv('BREVO_API_KEY')
                ?: '',
            'from_email' => $local['from_email']
                ?? getenv('CASHMAIL_FROM_EMAIL')
                ?: 'cashpilot.oficial@gmail.com',
            'from_name' => $local['from_name']
                ?? getenv('CASHMAIL_FROM_NAME')
                ?: 'CashPilot',
        ];

}

function cpEnviarEmail(string $destino, string $assunto, string $html): array
 {

        $config = cpEmailConfig();

        if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {

                return [
                    'ok' => false,
                    'erro' => 'O endereço de e-mail de destino é inválido.',
                    'message_id' => null,
                ];

    }

        if ($config['api_key'] === '') {

                return [
                    'ok' => false,
                    'erro' => 'Brevo não configurada. Defina a variável BREVO_API_KEY.',
                    'message_id' => null,
                ];

    }

        if (!function_exists('curl_init')) {

                return [
                    'ok' => false,
                    'erro' => 'A extensão cURL do PHP não está habilitada.',
                    'message_id' => null,
                ];

    }

        $payload = [
            'sender' => [
                'name' => $config['from_name'],
                'email' => $config['from_email'],
            ],
            'to' => [
                [
                    'email' => $destino,
                ],
            ],
            'subject' => $assunto,
            'htmlContent' => $html,
        ];

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {

                return [
                    'ok' => false,
                    'erro' => 'Não foi possível preparar o conteúdo do e-mail.',
                    'message_id' => null,
                ];

    }

        $ch = curl_init($config['api_url']);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_POST => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'api-key: ' . $config['api_key'],
                'content-type: application/json',
            ],
            CURLOPT_POSTFIELDS => $json,
        ]);

        $resposta = curl_exec($ch);

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $curlErro = curl_error($ch);

        curl_close($ch);

        if ($resposta === false || $curlErro !== '') {

                error_log('CashPilot/Brevo: ' . $curlErro);

                return [
                    'ok' => false,
                    'erro' => 'Não foi possível conectar ao serviço de e-mail.',
                    'message_id' => null,
                ];

    }

        $dadosResposta = json_decode((string) $resposta, true);

        if ($httpCode >= 200 && $httpCode < 300) {

                return [
                    'ok' => true,
                    'erro' => null,
                    'message_id' => is_array($dadosResposta)
                        ? ($dadosResposta['messageId'] ?? null)
                        : null,
                ];

    }

        $mensagemBrevo = is_array($dadosResposta)
            ? trim((string) ($dadosResposta['message'] ?? ''))
            : '';

        error_log(
            'CashPilot/Brevo HTTP ' . $httpCode
            . ($mensagemBrevo !== '' ? ': ' . $mensagemBrevo : '')
        );

        $erroPublico = match ($httpCode) {

                400 => 'O serviço de e-mail recusou os dados enviados.',
                401, 403 => 'O serviço de e-mail não está autorizado. Verifique a configuração da Brevo.',
                429 => 'O limite temporário de envio de e-mails foi atingido. Tente novamente mais tarde.',
                default => 'Não foi possível enviar o e-mail agora. Tente novamente em alguns minutos.',

    };

        return [
            'ok' => false,
            'erro' => $erroPublico,
            'message_id' => null,
        ];

}

function cpGerarCodigo6(): string
 {

        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

}

function cpSalvarCodigo(
    PDO $pdo,
    int $usuarioId,
    string $tipo,
    string $destino,
    string $codigo
): void {

        $pdo->prepare(
            'UPDATE codigos_verificacao
         SET usado_em = NOW()
         WHERE usuario_id = :uid
           AND tipo = :tipo
           AND usado_em IS NULL'
        )->execute([
            'uid' => $usuarioId,
            'tipo' => $tipo,
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO codigos_verificacao (
            usuario_id,
            tipo,
            destino,
            codigo_hash,
            expira_em
         ) VALUES (
            :uid,
            :tipo,
            :destino,
            :hash,
            DATE_ADD(NOW(), INTERVAL 15 MINUTE)
         )'
        );

        $stmt->execute([
            'uid' => $usuarioId,
            'tipo' => $tipo,
            'destino' => $destino,
            'hash' => password_hash($codigo, PASSWORD_DEFAULT),
        ]);

}

function cpSegundosParaReenvio(
    PDO $pdo,
    int $usuarioId,
    string $tipo,
    int $esperaSegundos
): int {

        if ($esperaSegundos <= 0) {

                return 0;

    }

        $stmt = $pdo->prepare(
            'SELECT GREATEST(
            0,
            :espera - TIMESTAMPDIFF(SECOND, criado_em, NOW())
         )
         FROM codigos_verificacao
         WHERE usuario_id = :uid
           AND tipo = :tipo
         ORDER BY id DESC
         LIMIT 1'
        );

        $stmt->bindValue(':espera', $esperaSegundos, PDO::PARAM_INT);

        $stmt->bindValue(':uid', $usuarioId, PDO::PARAM_INT);

        $stmt->bindValue(':tipo', $tipo);

        $stmt->execute();

        $restante = $stmt->fetchColumn();

        return $restante === false ? 0 : max(0, (int) $restante);

}

function cpPodeReenviarCodigo(
    PDO $pdo,
    int $usuarioId,
    string $tipo,
    int $esperaSegundos = 30
): bool {

        return cpSegundosParaReenvio(
            $pdo,
            $usuarioId,
            $tipo,
            $esperaSegundos
        ) === 0;

}

function cpEnviarCodigo(
    PDO $pdo,
    int $usuarioId,
    string $tipo,
    string $destino,
    int $esperaReenvio = 0
): array {

        if ($esperaReenvio > 0) {

                $restante = cpSegundosParaReenvio(
                    $pdo,
                    $usuarioId,
                    $tipo,
                    $esperaReenvio
                );

                if ($restante > 0) {

                        $textoTempo = $restante < 60
                            ? $restante . ' segundos'
                            : (int) ceil($restante / 60) . ' minuto(s)';

                        return [
                            'ok' => false,
                            'erro' => 'Aguarde ' . $textoTempo . ' para solicitar outro código.',
                            'aguarde' => $restante,
                        ];

        }

    }

        $codigo = cpGerarCodigo6();

        $titulo = match ($tipo) {

                'recuperacao_senha' => 'Recuperação de senha',
                'troca_email' => 'Confirme seu novo e-mail',
                default => 'Confirme seu e-mail',

    };

        $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;padding:32px;color:#202722">'
            . '<h2 style="margin:0 0 10px">CashPilot</h2>'
            . '<p style="color:#66716a">' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<div style="font-size:34px;letter-spacing:8px;font-weight:700;margin:28px 0;color:#315e59">'
            . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8')
            . '</div>'
            . '<p>Este código é válido por <strong>15 minutos</strong>.</p>'
            . '<p style="font-size:12px;color:#89928c">Se você não solicitou esta ação, ignore este e-mail.</p>'
            . '<hr style="border:0;border-top:1px solid #e2e6e2;margin:28px 0">'
            . '<small style="color:#89928c">CashPilot · desenvolvido pela Syem Tech</small>'
            . '</div>';

        $envio = cpEnviarEmail($destino, $titulo . ' · CashPilot', $html);

        if (!$envio['ok']) {

                return $envio;

    }

        cpSalvarCodigo($pdo, $usuarioId, $tipo, $destino, $codigo);

        return ['ok' => true, 'erro' => null];

}

function cpValidarCodigo(
    PDO $pdo,
    int $usuarioId,
    string $tipo,
    string $codigo
): array {

        $stmt = $pdo->prepare(
            'SELECT
            id,
            codigo_hash,
            expira_em,
            tentativas,
            destino,
            CASE WHEN expira_em < NOW() THEN 1 ELSE 0 END AS expirado
         FROM codigos_verificacao
         WHERE usuario_id = :uid
           AND tipo = :tipo
           AND usado_em IS NULL
         ORDER BY id DESC
         LIMIT 1'
        );

        $stmt->execute([
            'uid' => $usuarioId,
            'tipo' => $tipo,
        ]);

        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$registro) {

                return ['ok' => false, 'erro' => 'Solicite um novo código.'];

    }

        if ((int) $registro['tentativas'] >= 6) {

                return ['ok' => false, 'erro' => 'Muitas tentativas. Solicite um novo código.'];

    }

        if ((int) ($registro['expirado'] ?? 0) === 1) {

                return [
                    'ok' => false,
                    'erro' => 'Este código expirou. Solicite um novo código.',
                ];

    }

        if (!password_verify($codigo, $registro['codigo_hash'])) {

                $pdo->prepare(
                    'UPDATE codigos_verificacao
             SET tentativas = tentativas + 1
             WHERE id = :id'
                )->execute(['id' => $registro['id']]);

                return ['ok' => false, 'erro' => 'Código incorreto.'];

    }

        $pdo->prepare(
            'UPDATE codigos_verificacao
         SET usado_em = NOW()
         WHERE id = :id'
        )->execute(['id' => $registro['id']]);

        return [
            'ok' => true,
            'erro' => null,
            'destino' => $registro['destino'],
        ];

}
