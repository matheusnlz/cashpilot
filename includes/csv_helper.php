<?php
/**
 * CashPilot 14.4 - Leitor inteligente de CSV bancário.
 *
 * O objetivo aqui não é "adivinhar" silenciosamente. O leitor detecta
 * codificação, delimitador, cabeçalho e formatos comuns de data/valor e
 * devolve metadados de confiança para a tela de revisão.
 */

const CSV_TAMANHO_MAXIMO = 2 * 1024 * 1024;

const CSV_LINHAS_MAXIMAS = 3000;

const CSV_LINHAS_CABECALHO_MAX = 25;

function cpCsvLower(string $texto): string
 {

        return function_exists('mb_strtolower')
            ? mb_strtolower($texto, 'UTF-8')
            : strtolower($texto);

}

function cpCsvSubstr(string $texto, int $inicio, int $tamanho): string
 {

        return function_exists('mb_substr')
            ? mb_substr($texto, $inicio, $tamanho, 'UTF-8')
            : substr($texto, $inicio, $tamanho);

}

function validarArquivoCsv(array $arquivo): ?string
 {

        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {

                return 'Não foi possível receber o arquivo CSV. Tente novamente.';

    }

        $tamanho = (int) ($arquivo['size'] ?? 0);

        if ($tamanho <= 0) {

                return 'O arquivo CSV enviado está vazio.';

    }

        if ($tamanho > CSV_TAMANHO_MAXIMO) {

                return 'O CSV é muito grande. O tamanho máximo permitido é 2 MB.';

    }

        if (strtolower(pathinfo((string) ($arquivo['name'] ?? ''), PATHINFO_EXTENSION)) !== 'csv') {

                return 'Formato inválido. Envie um arquivo .csv.';

    }

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $mime = (string) $finfo->file((string) $arquivo['tmp_name']);

        $aceitos = [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            'application/octet-stream',
        ];

        if (!in_array($mime, $aceitos, true)) {

                return 'O conteúdo enviado não parece ser um CSV válido.';

    }

        $amostra = @file_get_contents((string) $arquivo['tmp_name'], false, null, 0, 8192);
        if (!is_string($amostra) || strpos($amostra, "\0") !== false) {
                return 'O conteúdo enviado não parece ser um CSV de texto válido.';
        }

        return null;

}

function cpCsvNormalizarEncoding(string $conteudo): array
 {

        $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo) ?? $conteudo;

        $utf8Valido = function_exists('mb_check_encoding')
            ? mb_check_encoding($conteudo, 'UTF-8')
            : (preg_match('//u', $conteudo) === 1);

        if ($utf8Valido) {

                return [$conteudo, 'UTF-8'];

    }

        foreach (['Windows-1252', 'ISO-8859-1'] as $origem) {

                if (function_exists('mb_convert_encoding')) {

                        $convertido = @mb_convert_encoding($conteudo, 'UTF-8', $origem);

        }  else {

                        $convertido = @iconv($origem, 'UTF-8//IGNORE', $conteudo);

        }

                $convertidoValido = is_string($convertido) && (
                    function_exists('mb_check_encoding')
                        ? mb_check_encoding($convertido, 'UTF-8')
                        : preg_match('//u', $convertido) === 1
                );

                if ($convertidoValido) {

                        return [$convertido, $origem];

        }

    }

        return [$conteudo, 'desconhecida'];

}

function detectarDelimitadorCsv(string $amostra): string
 {

        $candidatos = [';', ',', "\t", '|'];

        $linhas = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', $amostra) ?: [],
            fn(string $linha): bool => trim($linha) !== ''
        ));

        $linhas = array_slice($linhas, 0, 8);

        $melhor = ';';

        $melhorPontuacao = -1;

        foreach ($candidatos as $delimitador) {

                $contagens = [];

                foreach ($linhas as $linha) {

                        $contagens[] = max(0, count(str_getcsv($linha, $delimitador)) - 1);

        }

                if (!$contagens) {

                        continue;

        }

                $positivos = array_values(array_filter($contagens, fn(int $n): bool => $n > 0));

                if (!$positivos) {

                        continue;

        }

                $media = array_sum($positivos) / count($positivos);

                $variacao = 0.0;

                foreach ($positivos as $n) {

                        $variacao += abs($n - $media);

        }

                $variacao /= max(1, count($positivos));

                // Premia muitas colunas e consistência entre as linhas.
                $pontuacao = ($media * 10) - ($variacao * 4) + count($positivos);

                if ($pontuacao > $melhorPontuacao) {

                        $melhorPontuacao = $pontuacao;

                        $melhor = $delimitador;

        }

    }

        return $melhor;

}

function normalizarCabecalho(string $texto): string
 {

        $texto = trim($texto);

        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto) ?? $texto;

        $texto = cpCsvLower($texto);

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

        if ($ascii !== false) {

                $texto = $ascii;

    }

        return preg_replace('/[^a-z0-9]/', '', $texto) ?? '';

}

function converterValorCsv(string $valor): float
 {

        $original = trim($valor);

        if ($original === '') {

                return 0.0;

    }

        $negativo = false;

        if (preg_match('/^\(.*\)$/', $original)) {

                $negativo = true;

    }

        if (preg_match('/^-/', $original) || preg_match('/\bD\s*$/i', $original)) {

                $negativo = true;

    }

        $limpo = preg_replace('/[^0-9,.\-]/', '', $original) ?? '';

        $limpo = ltrim($limpo, '+');

        $ultimaVirgula = strrpos($limpo, ',');

        $ultimoPonto = strrpos($limpo, '.');

        if ($ultimaVirgula !== false && $ultimoPonto !== false) {

                if ($ultimaVirgula > $ultimoPonto) {

                        // 1.234,56
                        $limpo = str_replace('.', '', $limpo);

                        $limpo = str_replace(',', '.', $limpo);

        }  else {

                        // 1,234.56
                        $limpo = str_replace(',', '', $limpo);

        }

    }  elseif ($ultimaVirgula !== false) {

                $casas = strlen($limpo) - $ultimaVirgula - 1;

                if ($casas <= 2) {

                        $limpo = str_replace('.', '', $limpo);

                        $limpo = str_replace(',', '.', $limpo);

        }  else {

                        $limpo = str_replace(',', '', $limpo);

        }

    }  elseif ($ultimoPonto !== false) {

                $casas = strlen($limpo) - $ultimoPonto - 1;

                if ($casas > 2) {

                        $limpo = str_replace('.', '', $limpo);

        }

    }

        $numero = (float) $limpo;

        if ($negativo) {

                return -abs($numero);

    }

        return $numero;

}

function converterDataCsv(string $data): ?string
 {

        $data = trim($data);

        if ($data === '') {

                return null;

    }

        // Remove horário quando vier junto da data.
        $data = preg_replace('/\s+\d{1,2}:\d{2}(?::\d{2})?.*$/', '', $data) ?? $data;

        foreach ([
            'd/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y', 'Y/m/d',
            'd.m.Y', 'm/d/Y', 'Y-m-d\TH:i:s',
        ] as $formato) {

                $dt = DateTime::createFromFormat('!' . $formato, $data);

                $erros = DateTime::getLastErrors();

                if ($dt !== false && ($erros === false || ($erros['warning_count'] === 0 && $erros['error_count'] === 0))) {

                        return $dt->format('Y-m-d');

        }

    }

        $timestamp = strtotime(str_replace('/', '-', $data));

        if ($timestamp !== false) {

                $ano = (int) date('Y', $timestamp);

                if ($ano >= 2000 && $ano <= 2100) {

                        return date('Y-m-d', $timestamp);

        }

    }

        return null;

}

function cpCsvIndicePorAliases(array $normalizado, array $aliases): ?int
 {

        foreach ($normalizado as $indice => $nome) {

                if (in_array($nome, $aliases, true)) {

                        return $indice;

        }

    }

        return null;

}

function localizarCabecalhoCsv(array $linhasBrutas): ?array
 {

        $aliases = [
            'data' => [
                'data', 'date', 'dt', 'datalancamento', 'dtlancamento', 'datamovimento',
                'datamovimentacao', 'datatransacao', 'datadatransacao', 'datadolancamento',
            ],
            'descricao' => [
                'descricao', 'description', 'history', 'historico', 'historicodatransacao', 'lancamento',
                'memo', 'detalhes', 'detalhe', 'titulo', 'title', 'estabelecimento', 'nome',
            ],
            'descricao2' => ['historico', 'complemento', 'memo', 'detalhes', 'observacao'],
            'valor' => ['valor', 'value', 'amount', 'valorrs', 'valormovimento', 'valordatransacao'],
            'debito' => ['debito', 'debit', 'saidas', 'saida', 'valorDebito', 'valordebito'],
            'credito' => ['credito', 'credit', 'entradas', 'entrada', 'valorCredito', 'valorcredito'],
            'tipo' => ['tipo', 'type', 'natureza', 'operacao', 'movimento', 'debitoCredito', 'debitcredit'],
        ];

        $aliases = array_map(
            fn(array $lista): array => array_map('normalizarCabecalho', $lista),
            $aliases
        );

        $limite = min(count($linhasBrutas), CSV_LINHAS_CABECALHO_MAX);

        for ($i = 0;  $i < $limite;  $i++) {

                $linha = (string) $linhasBrutas[$i];

                if (trim($linha) === '') {

                        continue;

        }

                $delimitador = detectarDelimitadorCsv(implode("\n", array_slice($linhasBrutas, max(0, $i), 5)));

                $colunas = str_getcsv($linha, $delimitador);

                if (count($colunas) < 2) {

                        continue;

        }

                $normalizado = array_map('normalizarCabecalho', $colunas);

                $indiceData = cpCsvIndicePorAliases($normalizado, $aliases['data']);

                $indiceDescricao = cpCsvIndicePorAliases($normalizado, $aliases['descricao']);

                $indiceDescricao2 = cpCsvIndicePorAliases($normalizado, $aliases['descricao2']);

                $indiceValor = cpCsvIndicePorAliases($normalizado, $aliases['valor']);

                $indiceDebito = cpCsvIndicePorAliases($normalizado, $aliases['debito']);

                $indiceCredito = cpCsvIndicePorAliases($normalizado, $aliases['credito']);

                $indiceTipo = cpCsvIndicePorAliases($normalizado, $aliases['tipo']);

                $temValor = $indiceValor !== null || $indiceDebito !== null || $indiceCredito !== null;

                if ($indiceData !== null && $indiceDescricao !== null && $temValor) {

                        return [
                            'linha_indice' => $i,
                            'delimitador' => $delimitador,
                            'cabecalhos' => $colunas,
                            'indice_data' => $indiceData,
                            'indice_descricao_principal' => $indiceDescricao,
                            'indice_descricao_fallback' => $indiceDescricao2,
                            'indice_valor' => $indiceValor,
                            'indice_debito' => $indiceDebito,
                            'indice_credito' => $indiceCredito,
                            'indice_tipo' => $indiceTipo,
                        ];

        }

    }

        return null;

}

function cpCsvEhResumo(string $descricao): bool
 {

        $texto = normalizarCabecalho($descricao);

        if ($texto === '') {

                return true;

    }

        foreach ([
            'saldo', 'saldoinicial', 'saldofinal', 'saldodisponivel',
            'totalentradas', 'totalsaidas', 'totaldeentradas', 'totaldesaidas',
            'resumodoperiodo', 'resumo',
        ] as $termo) {

                if ($texto === $termo || str_starts_with($texto, $termo)) {

                        return true;

        }

    }

        return false;

}

function cpCsvTipoPorTexto(string $tipo, float $valor): array
 {

        $texto = normalizarCabecalho($tipo);

        if (in_array($texto, ['d', 'debito', 'debit', 'saida', 'despesa'], true)) {

                return ['despesa', 'alta'];

    }

        if (in_array($texto, ['c', 'credito', 'credit', 'entrada', 'receita'], true)) {

                return ['receita', 'alta'];

    }

        if ($valor < 0) {

                return ['despesa', 'alta'];

    }

        return ['receita', 'media'];

}

function cpCsvDescricao(array $colunas, ?int $principal, ?int $fallback): string
 {

        $a = $principal !== null ? trim((string) ($colunas[$principal] ?? '')) : '';

        $b = $fallback !== null ? trim((string) ($colunas[$fallback] ?? '')) : '';

        if ($a !== '' && $b !== '' && normalizarCabecalho($a) !== normalizarCabecalho($b)) {

                return trim($a . ' — ' . $b);

    }

        return $a !== '' ? $a : $b;

}

function parsearCsvExtrato(string $caminhoArquivo): array
 {

        $conteudo = @file_get_contents($caminhoArquivo);

        if ($conteudo === false || trim($conteudo) === '') {

                return ['erro' => 'O arquivo CSV está vazio ou não pôde ser lido.', 'linhas' => []];

    }

        [$conteudo, $encoding] = cpCsvNormalizarEncoding($conteudo);

        $linhasBrutas = preg_split('/\r\n|\r|\n/', $conteudo) ?: [];

        if (count(array_filter($linhasBrutas, fn(string $l): bool => trim($l) !== '')) < 2) {

                return ['erro' => 'O CSV não contém movimentações suficientes para importar.', 'linhas' => []];

    }

        $cabecalho = localizarCabecalhoCsv($linhasBrutas);

        if ($cabecalho === null) {

                return [
                    'erro' => 'Não foi possível identificar as colunas do extrato. Procure um CSV com Data, Descrição/Histórico e Valor, ou colunas separadas de Débito e Crédito.',
                    'linhas' => [],
                ];

    }

        $linhasDados = array_slice($linhasBrutas, $cabecalho['linha_indice'] + 1);

        if (count($linhasDados) > CSV_LINHAS_MAXIMAS) {

                return ['erro' => 'O CSV excede o limite de ' . CSV_LINHAS_MAXIMAS . ' linhas por importação.', 'linhas' => []];

    }

        $linhas = [];

        $ignoradas = 0;

        $avisos = [];

        foreach ($linhasDados as $numeroRelativo => $linhaBruta) {

                if (trim((string) $linhaBruta) === '') {

                        continue;

        }

                $colunas = str_getcsv((string) $linhaBruta, $cabecalho['delimitador']);

                $descricao = cpCsvDescricao(
                    $colunas,
                    $cabecalho['indice_descricao_principal'],
                    $cabecalho['indice_descricao_fallback']
                );

                $dataBruta = trim((string) ($colunas[$cabecalho['indice_data']] ?? ''));

                $data = converterDataCsv($dataBruta);

                $valor = 0.0;

                $tipoTexto = $cabecalho['indice_tipo'] !== null
                    ? trim((string) ($colunas[$cabecalho['indice_tipo']] ?? ''))
                    : '';

                if ($cabecalho['indice_valor'] !== null) {

                        $valor = converterValorCsv((string) ($colunas[$cabecalho['indice_valor']] ?? ''));

        }  else {

                        $debito = $cabecalho['indice_debito'] !== null
                            ? abs(converterValorCsv((string) ($colunas[$cabecalho['indice_debito']] ?? '')))
                            : 0.0;

                        $credito = $cabecalho['indice_credito'] !== null
                            ? abs(converterValorCsv((string) ($colunas[$cabecalho['indice_credito']] ?? '')))
                            : 0.0;

                        if ($debito > 0 && $credito <= 0) {

                                $valor = -$debito;

                                $tipoTexto = 'debito';

            }  elseif ($credito > 0 && $debito <= 0) {

                                $valor = $credito;

                                $tipoTexto = 'credito';

            }  else {

                                $ignoradas++;

                                continue;

            }

        }

                if ($descricao === '' || cpCsvEhResumo($descricao) || $data === null || abs($valor) < 0.00001) {

                        $ignoradas++;

                        continue;

        }

                [$tipo, $confiancaTipo] = cpCsvTipoPorTexto($tipoTexto, $valor);

                $score = 55;

                $score += $data !== null ? 20 : 0;

                $score += $descricao !== '' ? 10 : 0;

                $score += $confiancaTipo === 'alta' ? 15 : 8;

                $score = min(100, $score);

                $linhas[] = [
                    'descricao' => cpCsvSubstr($descricao, 0, 180),
                    'valor' => $tipo === 'despesa' ? -abs($valor) : abs($valor),
                    'data' => $data,
                    'tipo' => $tipo,
                    'tipo_confianca' => $confiancaTipo,
                    'confianca_leitura' => $score,
                    'origem_linha' => $cabecalho['linha_indice'] + $numeroRelativo + 2,
                ];

    }

        if (!$linhas) {

                return ['erro' => 'Nenhuma movimentação válida foi encontrada no CSV.', 'linhas' => []];

    }

        $media = (int) round(array_sum(array_column($linhas, 'confianca_leitura')) / count($linhas));

        if ($encoding !== 'UTF-8') {

                $avisos[] = 'O arquivo foi convertido automaticamente de ' . $encoding . ' para UTF-8.';

    }

        return [
            'erro' => null,
            'linhas' => $linhas,
            'ignoradas' => $ignoradas,
            'avisos' => $avisos,
            'meta' => [
                'motor' => 'CSV inteligente',
                'encoding' => $encoding,
                'delimitador' => $cabecalho['delimitador'] === "\t" ? 'TAB' : $cabecalho['delimitador'],
                'confianca_media' => $media,
                'cabecalho_linha' => $cabecalho['linha_indice'] + 1,
            ],
        ];

}
