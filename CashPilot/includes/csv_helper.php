<?php
/**
 * CashPilot - Utilitário de leitura de CSV de extrato bancário
 *
 * Responsável por validar e interpretar arquivos CSV enviados pelo
 * usuário, sem nunca executar o conteúdo do arquivo.
 */

const CSV_TAMANHO_MAXIMO = 2 * 1024 * 1024; // 2MB
const CSV_LINHAS_MAXIMAS = 2000;

/**
 * Valida o arquivo enviado (upload). Retorna null se válido,
 * ou uma mensagem de erro amigável caso contrário.
 */
function validarArquivoCsv(array $arquivo): ?string
{
    if (!isset($arquivo['error']) || $arquivo['error'] !== UPLOAD_ERR_OK) {
        return 'Não foi possível receber o arquivo. Tente novamente.';
    }

    if ($arquivo['size'] <= 0) {
        return 'O arquivo enviado está vazio.';
    }

    if ($arquivo['size'] > CSV_TAMANHO_MAXIMO) {
        return 'O arquivo é muito grande. O tamanho máximo permitido é 2MB.';
    }

    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if ($extensao !== 'csv') {
        return 'Formato inválido. Envie um arquivo no formato .csv.';
    }

    // Verifica o tipo real do conteúdo (não confia apenas na extensão)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);
    $mimesAceitos = ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'];
    if (!in_array($mime, $mimesAceitos, true)) {
        return 'Não foi possível importar o arquivo. Verifique se o arquivo está no formato CSV esperado.';
    }

    return null;
}

/**
 * Detecta o delimitador mais provável (';' é comum em bancos brasileiros).
 */
function detectarDelimitadorCsv(string $primeiraLinha): string
{
    $candidatos = [';', ',', "\t"];
    $melhor = ',';
    $maiorContagem = 0;

    foreach ($candidatos as $delimitador) {
        $contagem = substr_count($primeiraLinha, $delimitador);
        if ($contagem > $maiorContagem) {
            $maiorContagem = $contagem;
            $melhor = $delimitador;
        }
    }

    return $melhor;
}

/**
 * Normaliza um cabeçalho de coluna para comparação (minúsculo, sem acento).
 */
function normalizarCabecalho(string $texto): string
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $mapa = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','õ'=>'o','ô'=>'o','ú'=>'u','ç'=>'c'];
    $texto = strtr($texto, $mapa);
    return preg_replace('/[^a-z0-9]/', '', $texto);
}

/**
 * Converte um valor textual (formatos "1.234,56" ou "1234.56" ou "-45,90")
 * para float.
 */
function converterValorCsv(string $valor): float
{
    $valor = trim($valor);
    $valor = preg_replace('/[^0-9,.\-]/', '', $valor);

    // Formato brasileiro: 1.234,56 -> remove milhar, troca vírgula por ponto
    if (preg_match('/,\d{1,2}$/', $valor)) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return (float) $valor;
}

/**
 * Tenta interpretar uma data em formatos comuns (dd/mm/yyyy, yyyy-mm-dd, dd-mm-yyyy).
 * Retorna no formato Y-m-d ou null se não conseguir interpretar.
 */
function converterDataCsv(string $data): ?string
{
    $data = trim($data);

    $formatos = ['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y', 'Y/m/d'];
    foreach ($formatos as $formato) {
        $dt = DateTime::createFromFormat($formato, $data);
        if ($dt !== false) {
            return $dt->format('Y-m-d');
        }
    }

    return null;
}

/**
 * Faz o parsing completo do arquivo CSV.
 *
 * Retorna um array:
 * [
 *   'erro' => string|null,
 *   'linhas' => [['descricao' => ..., 'valor' => ..., 'data' => ...], ...],
 * ]
 */
function parsearCsvExtrato(string $caminhoArquivo): array
{
    $conteudo = file_get_contents($caminhoArquivo);
    if ($conteudo === false || trim($conteudo) === '') {
        return ['erro' => 'O arquivo está vazio ou não pôde ser lido.', 'linhas' => []];
    }

    // Remove BOM UTF-8, se existir
    $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);

    $linhasBrutas = preg_split('/\r\n|\r|\n/', trim($conteudo));
    if (count($linhasBrutas) < 2) {
        return ['erro' => 'O arquivo não contém movimentações suficientes para importar.', 'linhas' => []];
    }

    if (count($linhasBrutas) - 1 > CSV_LINHAS_MAXIMAS) {
        return ['erro' => 'O arquivo excede o limite de ' . CSV_LINHAS_MAXIMAS . ' movimentações por importação.', 'linhas' => []];
    }

    $delimitador = detectarDelimitadorCsv($linhasBrutas[0]);
    $cabecalho = str_getcsv($linhasBrutas[0], $delimitador);
    $cabecalhoNormalizado = array_map('normalizarCabecalho', $cabecalho);

    $colunasData = ['data', 'date', 'dtlancamento', 'datalancamento', 'dt'];
    $colunasDescricao = ['descricao', 'historico', 'description', 'lancamento', 'memo', 'title'];
    $colunasValor = ['valor', 'value', 'amount', 'valorrs'];

    $indiceData = null;
    $indiceDescricao = null;
    $indiceValor = null;

    foreach ($cabecalhoNormalizado as $indice => $coluna) {
        if ($indiceData === null && in_array($coluna, $colunasData, true)) {
            $indiceData = $indice;
        }
        if ($indiceDescricao === null && in_array($coluna, $colunasDescricao, true)) {
            $indiceDescricao = $indice;
        }
        if ($indiceValor === null && in_array($coluna, $colunasValor, true)) {
            $indiceValor = $indice;
        }
    }

    if ($indiceData === null || $indiceDescricao === null || $indiceValor === null) {
        return [
            'erro' => 'Não foi possível identificar as colunas de data, descrição e valor. '
                    . 'O arquivo deve conter colunas como "Data", "Descrição" e "Valor".',
            'linhas' => [],
        ];
    }

    $linhas = [];
    $ignoradas = 0;

    for ($i = 1; $i < count($linhasBrutas); $i++) {
        if (trim($linhasBrutas[$i]) === '') {
            continue;
        }

        $colunas = str_getcsv($linhasBrutas[$i], $delimitador);

        $descricaoBruta = trim($colunas[$indiceDescricao] ?? '');
        $valorBruto = trim($colunas[$indiceValor] ?? '');
        $dataBruta = trim($colunas[$indiceData] ?? '');

        $data = converterDataCsv($dataBruta);
        $valor = converterValorCsv($valorBruto);

        if ($descricaoBruta === '' || $data === null || $valor == 0.0) {
            $ignoradas++;
            continue;
        }

        $linhas[] = [
            'descricao' => mb_substr($descricaoBruta, 0, 180),
            'valor'     => $valor,
            'data'      => $data,
            'tipo'      => $valor >= 0 ? 'receita' : 'despesa',
        ];
    }

    if (empty($linhas)) {
        return ['erro' => 'Nenhuma movimentação válida foi encontrada no arquivo.', 'linhas' => []];
    }

    return ['erro' => null, 'linhas' => $linhas, 'ignoradas' => $ignoradas];
}
