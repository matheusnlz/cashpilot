<?php

require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/csv_helper.php';

require_once __DIR__ . '/../includes/ofx_helper.php';

require_once __DIR__ . '/../includes/pdf_helper.php';

require_once __DIR__ . '/../includes/python_helper.php';

require_once __DIR__ . '/../includes/importacao_inteligente.php';

exigirLogin();

$pdo = conectar();

$usuarioId = (int) usuarioLogadoId();

function voltarComErro(string $mensagem): void
 {

        $_SESSION['erro_importacao'] = $mensagem;

        header('Location: ../pages/importar.php');

        exit;

}

function classificarComPython(int $usuarioId, array $linhas): array
 {

        $validas = array_values(array_filter(
            $linhas,
            fn($linha) => is_array($linha)
                && isset($linha['descricao'], $linha['valor'], $linha['tipo'], $linha['data'])
        ));

        if (!$validas) {

                return [];

    }

        $entrada = json_encode(
            array_map(
                fn(array $linha): array => [
                    'descricao' => (string) $linha['descricao'],
                    'valor' => $linha['tipo'] === 'despesa'
                        ? -abs((float) $linha['valor'])
                        : abs((float) $linha['valor']),
                    'data' => (string) $linha['data'],
                ],
                $validas
            ),
            JSON_UNESCAPED_UNICODE
        );

        if ($entrada === false) {

                return [];

    }

        $pastaPython = __DIR__ . '/../python';

        $script = $pastaPython . '/classificar.py';

        if (!is_file($script)) {

                return [];

    }

        $resultadoProcesso = executarProcessoCashPilot(
            [caminhoPythonCashPilot(), $script, (string) $usuarioId],
            $pastaPython,
            $entrada,
            20
        );

        if (empty($resultadoProcesso['ok'])) {
            $motivo = !empty($resultadoProcesso['timeout'])
                ? 'tempo limite excedido'
                : trim((string) ($resultadoProcesso['stderr'] ?? ''));

            error_log('CashPilot/Classificador Python: ' . mb_substr($motivo, 0, 800));
            return [];
        }

        $saida = (string) ($resultadoProcesso['stdout'] ?? '');

        $dados = json_decode(trim((string) $saida), true);

        return is_array($dados) ? $dados : [];

}

function cpImportFingerprint(array $linha): string
 {

        $descricao = mb_strtoupper(trim((string) ($linha['descricao'] ?? '')), 'UTF-8');

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $descricao);

        if ($ascii !== false) {

                $descricao = $ascii;

    }

        $descricao = preg_replace('/[^A-Z0-9]+/', ' ', $descricao) ?? $descricao;

        $descricao = preg_replace('/\s+/', ' ', trim($descricao)) ?? trim($descricao);

        return hash('sha256', implode('|', [
            (string) ($linha['data'] ?? ''),
            (string) ($linha['tipo'] ?? ''),
            number_format(abs((float) ($linha['valor'] ?? 0)), 2, '.', ''),
            $descricao,
        ]));

}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['arquivo_extrato'])) {

        voltarComErro('Envie um arquivo para continuar.');

}

validarCsrf();

$formato = strtolower((string) ($_POST['formato'] ?? 'csv'));

if (!in_array($formato, ['csv', 'ofx', 'pdf'], true)) {

        voltarComErro('Formato de extrato inválido.');

}

$arquivo = $_FILES['arquivo_extrato'];

$validacao = match ($formato) {

        'csv' => validarArquivoCsv($arquivo),
        'ofx' => validarArquivoOfx($arquivo),
        'pdf' => validarArquivoPdf($arquivo),

};

if ($validacao !== null) {

        voltarComErro($validacao);

}

$contaId = $_POST['conta_id'] ?? '';

if ($contaId === 'nova') {

        $nomeNovaConta = trim((string) ($_POST['nome_nova_conta'] ?? ''));

        if ($nomeNovaConta === '') {

                voltarComErro('Informe um nome para a nova conta.');

    }

        $stmt = $pdo->prepare('INSERT INTO contas (usuario_id, nome, tipo) VALUES (:uid, :nome, "corrente")');

        $stmt->execute(['uid' => $usuarioId, 'nome' => mb_substr($nomeNovaConta, 0, 80)]);

        $contaId = (int) $pdo->lastInsertId();

}  else {

        $contaId = (int) $contaId;

        $stmt = $pdo->prepare('SELECT id FROM contas WHERE id = :id AND usuario_id = :uid');

        $stmt->execute(['id' => $contaId, 'uid' => $usuarioId]);

        if (!$stmt->fetchColumn()) {

                voltarComErro('Conta inválida.');

    }

}

$hash = hash_file('sha256', (string) $arquivo['tmp_name']);

if (!is_string($hash) || $hash === '') {

        voltarComErro('Não foi possível validar o arquivo enviado.');

}

$stmt = $pdo->prepare(
    'SELECT id FROM importacoes WHERE usuario_id = :uid AND hash_arquivo = :hash ORDER BY data_importacao DESC LIMIT 1'
);

$stmt->execute(['uid' => $usuarioId, 'hash' => $hash]);

if ($stmt->fetchColumn() && empty($_POST['ignorar_duplicidade'])) {

        voltarComErro('Este mesmo arquivo já foi importado anteriormente. Use outro extrato ou revise seu histórico de importações.');

}

$resultado = match ($formato) {

        'csv' => parsearCsvExtrato((string) $arquivo['tmp_name']),
        'ofx' => parsearOfxExtrato((string) $arquivo['tmp_name']),
        'pdf' => parsearPdfExtrato((string) $arquivo['tmp_name'], (string) ($_POST['banco_pdf'] ?? 'auto')),

};

if (($resultado['erro'] ?? null) !== null) {

        voltarComErro((string) $resultado['erro']);

}

$linhas = array_values(array_filter(
    $resultado['linhas'] ?? [],
    fn($linha) => is_array($linha)
        && isset($linha['descricao'], $linha['valor'], $linha['tipo'], $linha['data'])
        && abs((float) $linha['valor']) > 0.00001
));

if (!$linhas) {

        voltarComErro('Nenhuma movimentação válida foi encontrada no extrato.');

}

// Duplicatas dentro do próprio arquivo.
$vistosNoArquivo = [];

foreach ($linhas as &$linha) {

        $fingerprint = cpImportFingerprint($linha);

        $linha['duplicata_no_arquivo'] = isset($vistosNoArquivo[$fingerprint]);

        $vistosNoArquivo[$fingerprint] = true;

        $linha['fingerprint'] = $fingerprint;

}

unset($linha);

$dupReceita = $pdo->prepare(
    'SELECT COUNT(*) FROM receitas
     WHERE usuario_id = :uid AND descricao = :descricao AND valor = :valor AND data_receita = :data'
);

$dupDespesa = $pdo->prepare(
    'SELECT COUNT(*) FROM despesas
     WHERE usuario_id = :uid AND descricao = :descricao AND valor = :valor AND data_despesa = :data'
);

foreach ($linhas as &$linha) {

        $stmtDup = $linha['tipo'] === 'receita' ? $dupReceita : $dupDespesa;

        $stmtDup->execute([
            'uid' => $usuarioId,
            'descricao' => $linha['descricao'],
            'valor' => abs((float) $linha['valor']),
            'data' => $linha['data'],
        ]);

        $linha['possivel_duplicata'] = (bool) $stmtDup->fetchColumn() || !empty($linha['duplicata_no_arquivo']);

}

unset($linha);

$classificacoes = classificarComPython($usuarioId, $linhas);

foreach ($linhas as $indice => &$linha) {

        $classificacao = $classificacoes[$indice] ?? [];

        // A classificação pode melhorar a categoria e, quando confiável, o tipo.
        if (!empty($classificacao['tipo']) && ($linha['tipo_confianca'] ?? 'baixa') !== 'alta') {

                $linha['tipo'] = $classificacao['tipo'];

    }

        $linha['categoria_id'] = $classificacao['categoria_id'] ?? null;

        $linha['categoria_nome'] = $classificacao['categoria_nome'] ?? 'Outros';

        $linha['confianca'] = $classificacao['confianca'] ?? 'baixa';

        $linha['confianca_leitura'] = max(0, min(100, (int) ($linha['confianca_leitura'] ?? 70)));

}

unset($linha);

$linhas = cpDetectarRecorrenciasImportacao($pdo, $usuarioId, $linhas);

$confiancaAlta = 0;

$confiancaMedia = 0;

$confiancaBaixa = 0;

$duplicadas = 0;

foreach ($linhas as $linha) {

        $score = (int) ($linha['confianca_leitura'] ?? 0);

        if ($score >= 85) {

                $confiancaAlta++;

    }  elseif ($score >= 70) {

                $confiancaMedia++;

    }  else {

                $confiancaBaixa++;

    }

        if (!empty($linha['possivel_duplicata'])) {

                $duplicadas++;

    }

}

$_SESSION['importacao_preview'] = [
    'conta_id' => (int) $contaId,
    'nome_arquivo' => mb_substr((string) ($arquivo['name'] ?? 'extrato'), 0, 180),
    'hash_arquivo' => $hash,
    'formato' => $formato,
    'banco_pdf' => $formato === 'pdf' ? ($resultado['banco'] ?? ($_POST['banco_pdf'] ?? 'auto')) : null,
    'quantidade_linhas' => count($linhas) + (int) ($resultado['ignoradas'] ?? 0),
    'quantidade_ignoradas' => (int) ($resultado['ignoradas'] ?? 0),
    'avisos' => is_array($resultado['avisos'] ?? null) ? $resultado['avisos'] : [],
    'meta' => is_array($resultado['meta'] ?? null) ? $resultado['meta'] : [],
    'estatisticas' => [
        'alta' => $confiancaAlta,
        'media' => $confiancaMedia,
        'baixa' => $confiancaBaixa,
        'duplicadas' => $duplicadas,
    ],
    'linhas' => $linhas,
];

header('Location: ../pages/importar_revisao.php');

exit;
