<?php

function cpTermoRecorrencia(string $descricao): string
 {

        $texto = mb_strtoupper($descricao, 'UTF-8');

        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;

        $prefixos = [
            'PIX RECEBIDO',
            'PIX ENVIADO',
            'PIX REALIZADO',
            'PIX PAGO',
            'PAGAMENTO',
            'COMPRA',
            'DEBITO',
            'CREDITO',
            'TRANSFERENCIA',
            'TED',
            'DOC',
        ];

        foreach ($prefixos as $prefixo) {

                $texto = preg_replace('/^' . preg_quote($prefixo, '/') . '\s*/', '', $texto);

    }

        $texto = preg_replace('/\b\d{2}[\/\-]\d{2}(?:[\/\-]\d{2,4})?\b/', ' ', $texto);

        $texto = preg_replace('/\b\d{3,}\b/', ' ', $texto);

        $texto = preg_replace('/[^A-Z0-9 ]+/', ' ', $texto);

        $texto = preg_replace('/\s+/', ' ', trim($texto));

        return mb_substr($texto, 0, 80);

}

function cpPeriodicidadePorIntervalos(array $datas): ?string
 {

        if (count($datas) < 2) {

                return null;

    }

        sort($datas);

        $intervalos = [];

        for ($i = 1;  $i < count($datas);  $i++) {

                $a = strtotime($datas[$i - 1]);

                $b = strtotime($datas[$i]);

                if (!$a || !$b || $b <= $a) {

                        continue;

        }

                $dias = (int) round(($b - $a) / 86400);

                if ($dias > 0) {

                        $intervalos[] = $dias;

        }

    }

        if (!$intervalos) {

                return null;

    }

        sort($intervalos);

        $mediana = $intervalos[(int) floor(count($intervalos) / 2)];

        return match (true) {

                $mediana >= 5 && $mediana <= 9 => 'semanal',
                $mediana >= 12 && $mediana <= 18 => 'quinzenal',
                $mediana >= 25 && $mediana <= 35 => 'mensal',
                $mediana >= 350 && $mediana <= 380 => 'anual',
                default => null,

    };

}

function cpImportProximaDataRecorrencia(string $ultimaData, string $periodicidade): ?string
 {

        $data = DateTime::createFromFormat('Y-m-d', $ultimaData);

        if (!$data) {

                return null;

    }

        switch ($periodicidade) {

                case 'semanal':
                    $data->modify('+7 days');

                    break;

                case 'quinzenal':
                    $data->modify('+15 days');

                    break;

                case 'mensal':
                    $data->modify('+1 month');

                    break;

                case 'anual':
                    $data->modify('+1 year');

                    break;

                default:
                    return null;

    }

        return $data->format('Y-m-d');

}

function cpEhAssinaturaProvavel(string $descricao): bool
 {

        $termo = cpTermoRecorrencia($descricao);

        $assinaturas = [
            'NETFLIX',
            'SPOTIFY',
            'DISNEY',
            'HBO',
            'MAX COM',
            'PRIME VIDEO',
            'YOUTUBE PREMIUM',
            'DEEZER',
            'GOOGLE ONE',
            'APPLE COM BILL',
        ];

        foreach ($assinaturas as $assinatura) {

                if ($assinatura !== '' && str_contains($termo, $assinatura)) {

                        return true;

        }

    }

        return false;

}

function cpDetectarRecorrenciasImportacao(
    PDO $pdo,
    int $usuarioId,
    array $linhas
): array {

        if (!$linhas) {

                return [];

    }

        $historico = [];

        try {

                $stmt = $pdo->prepare(
                    'SELECT descricao, valor, data_mov, tipo
             FROM (
                 SELECT descricao, valor, data_receita AS data_mov, "receita" AS tipo
                 FROM receitas
                 WHERE usuario_id = :uid1
                   AND data_receita >= DATE_SUB(CURDATE(), INTERVAL 8 MONTH)

                 UNION ALL

                 SELECT descricao, valor, data_despesa AS data_mov, "despesa" AS tipo
                 FROM despesas
                 WHERE usuario_id = :uid2
                   AND data_despesa >= DATE_SUB(CURDATE(), INTERVAL 8 MONTH)
             ) historico
             ORDER BY data_mov'
                );

                $stmt->execute([
                    'uid1' => $usuarioId,
                    'uid2' => $usuarioId,
                ]);

                $historico = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    }  catch (Throwable $e) {

                $historico = [];

    }

        $grupos = [];

        foreach ($historico as $item) {

                $termo = cpTermoRecorrencia((string) ($item['descricao'] ?? ''));

                if (mb_strlen($termo) < 3) {

                        continue;

        }

                $chave = ($item['tipo'] ?? 'despesa') . '|' . $termo;

                $grupos[$chave][] = [
                    'data' => (string) $item['data_mov'],
                    'valor' => (float) $item['valor'],
                ];

    }

        foreach ($linhas as $indice => $linha) {

                if (!is_array($linha)) {

                        continue;

        }

                $termo = cpTermoRecorrencia((string) ($linha['descricao'] ?? ''));

                if (mb_strlen($termo) < 3) {

                        continue;

        }

                $chave = ($linha['tipo'] ?? 'despesa') . '|' . $termo;

                $grupos[$chave][] = [
                    'data' => (string) ($linha['data'] ?? ''),
                    'valor' => abs((float) ($linha['valor'] ?? 0)),
                    'indice_atual' => $indice,
                ];

    }

        $resultado = $linhas;

        foreach ($resultado as $indice => &$linha) {

                if (!is_array($linha)) {

                        continue;

        }

                $termo = cpTermoRecorrencia((string) ($linha['descricao'] ?? ''));

                $chave = ($linha['tipo'] ?? 'despesa') . '|' . $termo;

                $eventos = $grupos[$chave] ?? [];

                $valorAtual = abs((float) ($linha['valor'] ?? 0));

                $compatíveis = array_values(array_filter(
                    $eventos,
                    function (array $evento) use ($valorAtual): bool {

                            $valor = abs((float) ($evento['valor'] ?? 0));
                            $tolerancia = max(0.75, $valorAtual * 0.08);
                            return abs($valor - $valorAtual) <= $tolerancia;

        }
                ));

                $datas = array_values(array_filter(array_map(
                    fn(array $evento) => $evento['data'] ?? null,
                    $compatíveis
                )));

                $periodicidade = cpPeriodicidadePorIntervalos($datas);

                $assinatura = cpEhAssinaturaProvavel((string) ($linha['descricao'] ?? ''));

                $possivel = $periodicidade !== null || $assinatura;

                $linha['possivel_recorrencia'] = $possivel;

                $linha['recorrencia_periodicidade'] = $periodicidade ?: ($assinatura ? 'mensal' : null);

                $linha['recorrencia_tipo'] = $assinatura ? 'assinatura' : 'despesa';

                $linha['recorrencia_motivo'] = $assinatura
                    ? 'Assinatura conhecida ou cobrança com padrão de repetição.'
                    : ($periodicidade
                        ? 'Movimentação semelhante encontrada em datas compatíveis.'
                        : null);

                if ($possivel && !empty($linha['data'])) {

                        $ultimaData = max($datas ?: [(string) $linha['data']]);

                        $linha['recorrencia_proxima_data'] = cpImportProximaDataRecorrencia(
                            $ultimaData,
                            (string) $linha['recorrencia_periodicidade']
                        );

        }  else {

                        $linha['recorrencia_proxima_data'] = null;

        }

    }

        unset($linha);

        return $resultado;

}
