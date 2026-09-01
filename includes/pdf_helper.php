<?php
require_once __DIR__ . '/python_helper.php';

function extrairJsonSeguroDoPython(string $saida): ?array
 {

        $saida = trim($saida);

        if ($saida === '') {

                return null;

    }

        $dados = json_decode($saida, true);

        if (is_array($dados)) {

                return $dados;

    }

        $inicio = strpos($saida, '{');

        $fim = strrpos($saida, '}');

        if ($inicio !== false && $fim !== false && $fim > $inicio) {

                $dados = json_decode(substr($saida, $inicio, $fim - $inicio + 1), true);

                if (is_array($dados)) {

                        return $dados;

        }

    }

        return null;

}

function validarArquivoPdf(array $arquivo): ?string
 {

        if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {

                return 'Não foi possível receber o arquivo PDF.';

    }

        $tamanho = (int) ($arquivo['size'] ?? 0);

        if ($tamanho <= 0 || $tamanho > 8 * 1024 * 1024) {

                return 'O PDF está vazio ou ultrapassa o limite de 8 MB.';

    }

        if (strtolower(pathinfo((string) ($arquivo['name'] ?? ''), PATHINFO_EXTENSION)) !== 'pdf') {

                return 'Envie um arquivo .pdf.';

    }

        $finfo = new finfo(FILEINFO_MIME_TYPE);

        $mime = (string) $finfo->file((string) $arquivo['tmp_name']);

        if (!in_array($mime, ['application/pdf', 'application/octet-stream'], true)) {

                return 'O conteúdo enviado não parece ser um PDF válido.';

    }

        $cabecalho = @file_get_contents((string) $arquivo['tmp_name'], false, null, 0, 5);
        if ($cabecalho !== '%PDF-') {
                return 'O arquivo enviado não possui uma assinatura PDF válida.';
        }

        return null;

}

function parsearPdfExtrato(string $arquivo, string $banco = 'auto'): array
 {

        $bancosPermitidos = ['auto', 'nubank', 'itau', 'bradesco', 'santander', 'inter', 'caixa', 'bb', 'outro'];
        $banco = mb_strtolower(trim($banco));
        if (!in_array($banco, $bancosPermitidos, true)) {
                $banco = 'auto';
        }


        $script = __DIR__ . '/../python/pdf_extrato.py';

        $python = caminhoPythonCashPilot();

        if (!is_file($script) || !is_file($arquivo)) {

                return [
                    'erro' => 'O leitor de PDF ou o arquivo enviado não foi encontrado.',
                    'linhas' => [],
                    'ignoradas' => 0,
                ];

    }

        $processo = executarProcessoCashPilot(
            [$python, $script, $arquivo, $banco],
            __DIR__ . '/../python',
            null,
            20
        );

        if (!empty($processo['timeout'])) {
            error_log('CashPilot/PDF: tempo limite excedido.');
            return [
                'erro' => 'O leitor de PDF demorou além do limite permitido.',
                'linhas' => [],
                'ignoradas' => 0,
            ];
        }

        $saida = (string) ($processo['stdout'] ?? '');
        $stderr = (string) ($processo['stderr'] ?? '');
        $status = (int) ($processo['status'] ?? -1);

        $dados = extrairJsonSeguroDoPython((string) $saida);

        if (is_array($dados)) {

                return [
                    'erro' => $dados['erro'] ?? null,
                    'linhas' => is_array($dados['linhas'] ?? null) ? $dados['linhas'] : [],
                    'ignoradas' => (int) ($dados['ignoradas'] ?? 0),
                    'banco' => $dados['banco'] ?? $banco,
                    'avisos' => is_array($dados['avisos'] ?? null) ? $dados['avisos'] : [],
                    'meta' => is_array($dados['meta'] ?? null) ? $dados['meta'] : [],
                ];

    }

        error_log('CashPilot PDF status ' . $status . ' | ' . trim((string) $stderr));

        return [
            'erro' => 'O PDF foi recebido, mas o CashPilot não conseguiu interpretar a resposta do leitor local.',
            'linhas' => [],
            'ignoradas' => 0,
        ];

}
