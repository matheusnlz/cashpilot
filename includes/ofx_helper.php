<?php
require_once __DIR__ . '/csv_helper.php';

/** CashPilot 14.4 - Importador OFX/QFX simples e determinístico. */

const OFX_TAMANHO_MAXIMO = 4 * 1024 * 1024;

function validarArquivoOfx(array $arquivo): ?string
 {

        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {

                return 'Não foi possível receber o arquivo OFX.';

    }

        $tamanho = (int) ($arquivo['size'] ?? 0);

        if ($tamanho <= 0 || $tamanho > OFX_TAMANHO_MAXIMO) {

                return 'O OFX está vazio ou ultrapassa o limite de 4 MB.';

    }

        $ext = strtolower(pathinfo((string) ($arquivo['name'] ?? ''), PATHINFO_EXTENSION));

        if (!in_array($ext, ['ofx', 'qfx'], true)) {

                return 'Envie um arquivo .ofx ou .qfx.';

    }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file((string) $arquivo['tmp_name']);
        $aceitos = ['application/x-ofx', 'application/xml', 'text/xml', 'text/plain', 'application/octet-stream'];
        if (!in_array($mime, $aceitos, true)) {
                return 'O conteúdo enviado não parece ser um OFX válido.';
        }

        $inicio = @file_get_contents((string) $arquivo['tmp_name'], false, null, 0, 4096);
        if (!is_string($inicio) || !preg_match('/<\s*(OFX|OFXHEADER|STMTTRN)/i', $inicio)) {
                return 'O conteúdo enviado não parece ser um extrato OFX válido.';
        }

        return null;

}

function cpOfxCampo(string $bloco, string $campo): ?string
 {

        if (preg_match('/<' . preg_quote($campo, '/') . '>\s*([^<\r\n]+)/i', $bloco, $m)) {

                return trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    }

        return null;

}

function cpOfxData(?string $valor): ?string
 {

        if (!$valor || !preg_match('/(\d{4})(\d{2})(\d{2})/', $valor, $m)) {

                return null;

    }

        $data = sprintf('%s-%s-%s', $m[1], $m[2], $m[3]);

        $dt = DateTime::createFromFormat('!Y-m-d', $data);

        return $dt ? $dt->format('Y-m-d') : null;

}

function parsearOfxExtrato(string $caminho): array
 {

        $conteudo = @file_get_contents($caminho);

        if ($conteudo === false || trim($conteudo) === '') {

                return ['erro' => 'O arquivo OFX está vazio ou não pôde ser lido.', 'linhas' => []];

    }

        [$conteudo] = (function (string $texto): array {

                $utf8 = function_exists('mb_check_encoding')
                    ? mb_check_encoding($texto, 'UTF-8')
                    : preg_match('//u', $texto) === 1;
                if ($utf8) {

                        return [$texto, 'UTF-8'];

        }

                $convertido = function_exists('mb_convert_encoding')
                    ? mb_convert_encoding($texto, 'UTF-8', 'Windows-1252')
                    : iconv('Windows-1252', 'UTF-8//IGNORE', $texto);
                return [(string) $convertido, 'Windows-1252'];

    })($conteudo);

        if (!preg_match_all('/<STMTTRN>(.*?)(?:<\/STMTTRN>|(?=<STMTTRN>|<\/BANKTRANLIST>|$))/is', $conteudo, $matches)) {

                return ['erro' => 'O OFX foi aberto, mas nenhuma transação STMTTRN foi encontrada.', 'linhas' => []];

    }

        $linhas = [];

        $ignoradas = 0;

        foreach ($matches[1] as $bloco) {

                $data = cpOfxData(cpOfxCampo($bloco, 'DTPOSTED'));

                $valorBruto = cpOfxCampo($bloco, 'TRNAMT');

                $valor = $valorBruto !== null ? (float) str_replace(',', '.', $valorBruto) : 0.0;

                $nome = cpOfxCampo($bloco, 'NAME') ?? '';

                $memo = cpOfxCampo($bloco, 'MEMO') ?? '';

                $fitid = cpOfxCampo($bloco, 'FITID');

                $tipoOfx = cpOfxCampo($bloco, 'TRNTYPE');

                $descricao = trim($nome);

                if ($memo !== '' && normalizarCabecalho($memo) !== normalizarCabecalho($nome)) {

                        $descricao = trim($descricao . ($descricao !== '' ? ' — ' : '') . $memo);

        }

                if ($data === null || $descricao === '' || abs($valor) < 0.00001) {

                        $ignoradas++;

                        continue;

        }

                $tipo = $valor < 0 ? 'despesa' : 'receita';

                $linhas[] = [
                    'descricao' => cpCsvSubstr($descricao, 0, 180),
                    'valor' => $tipo === 'despesa' ? -abs($valor) : abs($valor),
                    'data' => $data,
                    'tipo' => $tipo,
                    'tipo_confianca' => 'alta',
                    'confianca_leitura' => 100,
                    'id_externo' => $fitid,
                    'tipo_ofx' => $tipoOfx,
                ];

    }

        if (!$linhas) {

                return ['erro' => 'Nenhuma movimentação válida foi encontrada no OFX.', 'linhas' => []];

    }

        return [
            'erro' => null,
            'linhas' => $linhas,
            'ignoradas' => $ignoradas,
            'avisos' => [],
            'meta' => [
                'motor' => 'OFX estruturado',
                'confianca_media' => 100,
            ],
        ];

}
