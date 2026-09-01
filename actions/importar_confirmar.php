<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

require_once __DIR__ . '/../includes/importacao_inteligente.php';

exigirLogin();

$pdo = conectar();

$usuarioId = (int) usuarioLogadoId();

$preview = $_SESSION['importacao_preview'] ?? null;

if (!$preview || $_SERVER['REQUEST_METHOD'] !== 'POST') {

        header('Location: ../pages/importar.php');

        exit;

}

validarCsrf();

$linhasPostadas = $_POST['linhas'] ?? [];

$contaId = (int) $preview['conta_id'];

function termoRegraImportacao(string $descricao): string
 {

        $texto = mb_strtoupper($descricao, 'UTF-8');

        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

        if ($ascii !== false) {

                $texto = $ascii;

    }

        $texto = preg_replace('/\d+/', ' ', $texto) ?? $texto;

        $texto = preg_replace('/[^A-Z ]+/', ' ', $texto) ?? $texto;

        $texto = preg_replace('/\s+/', ' ', trim($texto)) ?? trim($texto);

        return mb_substr($texto, 0, 80);

}

function criarRecorrenciaImportada(PDO $pdo, int $usuarioId, array $linha): void
 {

        if (usuarioLogadoTipo() !== 'pessoa_fisica' || empty($linha['criar_recorrencia'])) {

                return;

    }

        $descricao = trim((string) ($linha['descricao'] ?? ''));

        $valor = abs((float) str_replace(',', '.', (string) ($linha['valor'] ?? '0')));

        $categoriaId = !empty($linha['categoria_id']) ? (int) $linha['categoria_id'] : null;

        if ($descricao === '' || $valor <= 0) {

                return;

    }

        $periodicidade = in_array(
            $linha['recorrencia_periodicidade'] ?? '',
            ['semanal', 'quinzenal', 'mensal', 'anual'],
            true
        ) ? $linha['recorrencia_periodicidade'] : 'mensal';

        $tipo = ($linha['recorrencia_tipo'] ?? '') === 'assinatura' ? 'assinatura' : 'despesa';

        $proximaData = $linha['recorrencia_proxima_data'] ?? null;

        if (!$proximaData || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $proximaData)) {

                $proximaData = cpImportProximaDataRecorrencia((string) ($linha['data'] ?? date('Y-m-d')), $periodicidade);

    }

        $dia = $proximaData ? max(1, min(28, (int) date('d', strtotime($proximaData)))) : 10;

        $termo = cpTermoRecorrencia($descricao);

        $stmt = $pdo->prepare(
            'SELECT id, nome FROM recorrencias_pf
         WHERE usuario_id = :uid AND ativo = 1
           AND ABS(valor - :valor) <= GREATEST(0.75, :valor2 * 0.08)'
        );

        $stmt->execute(['uid' => $usuarioId, 'valor' => $valor, 'valor2' => $valor]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $existente) {

                if (cpTermoRecorrencia((string) $existente['nome']) === $termo) {

                        return;

        }

    }

        $stmt = $pdo->prepare(
            'INSERT INTO recorrencias_pf (
            usuario_id, nome, categoria_id, valor, tipo, periodicidade,
            intervalo_dias, dia_vencimento, proxima_data
         ) VALUES (
            :uid, :nome, :cat, :valor, :tipo, :periodicidade,
            NULL, :dia, :proxima
         )'
        );

        $stmt->execute([
            'uid' => $usuarioId,
            'nome' => mb_substr($descricao, 0, 140),
            'cat' => $categoriaId,
            'valor' => $valor,
            'tipo' => $tipo,
            'periodicidade' => $periodicidade,
            'dia' => $dia,
            'proxima' => $proximaData,
        ]);

}

$stmtConta = $pdo->prepare('SELECT id FROM contas WHERE id = :id AND usuario_id = :uid');

$stmtConta->execute(['id' => $contaId, 'uid' => $usuarioId]);

if (!$stmtConta->fetchColumn()) {

        $_SESSION['erro_importacao'] = 'Conta inválida para esta importação.';

        header('Location: ../pages/importar.php');

        exit;

}

// Somente categorias do próprio usuário podem ser usadas no POST.
$stmtCategorias = $pdo->prepare('SELECT id, tipo FROM categorias WHERE usuario_id = :uid');

$stmtCategorias->execute(['uid' => $usuarioId]);

$categoriasPermitidas = [];

foreach ($stmtCategorias->fetchAll(PDO::FETCH_ASSOC) as $categoria) {

        $categoriasPermitidas[(int) $categoria['id']] = (string) $categoria['tipo'];

}

try {

        $pdo->beginTransaction();

        $stmtImportacao = $pdo->prepare(
            'INSERT INTO importacoes (
            usuario_id, conta_id, nome_arquivo, hash_arquivo, quantidade_linhas, status
         ) VALUES (:uid, :conta, :nome, :hash, :qtd, "processando")'
        );

        $stmtImportacao->execute([
            'uid' => $usuarioId,
            'conta' => $contaId,
            'nome' => $preview['nome_arquivo'],
            'hash' => $preview['hash_arquivo'],
            'qtd' => $preview['quantidade_linhas'],
        ]);

        $importacaoId = (int) $pdo->lastInsertId();

        $stmtReceita = $pdo->prepare(
            'INSERT INTO receitas (usuario_id, categoria_id, conta_id, importacao_id, valor, descricao, data_receita)
         VALUES (:uid, :cat, :conta, :imp, :valor, :descricao, :data)'
        );

        $stmtDespesa = $pdo->prepare(
            'INSERT INTO despesas (usuario_id, categoria_id, conta_id, importacao_id, valor, descricao, data_despesa)
         VALUES (:uid, :cat, :conta, :imp, :valor, :descricao, :data)'
        );

        $importadas = 0;

        $ignoradas = (int) ($preview['quantidade_ignoradas'] ?? 0);

        foreach ($linhasPostadas as $linha) {

                if (empty($linha['incluir'])) {

                        $ignoradas++;

                        continue;

        }

                $descricao = trim((string) ($linha['descricao'] ?? ''));

                $descricao = preg_replace('/\s+/', ' ', $descricao) ?? $descricao;

                $descricao = mb_substr($descricao, 0, 180);

                $valor = abs((float) str_replace(',', '.', (string) ($linha['valor'] ?? '0')));

                $data = (string) ($linha['data'] ?? '');

                $tipo = ($linha['tipo'] ?? '') === 'receita' ? 'receita' : 'despesa';

                $categoriaId = !empty($linha['categoria_id']) ? (int) $linha['categoria_id'] : null;

                $categoriaSugeridaId = !empty($linha['categoria_sugerida_id']) ? (int) $linha['categoria_sugerida_id'] : null;

                $dataObj = DateTime::createFromFormat('!Y-m-d', $data);

                $errosData = DateTime::getLastErrors();

                $dataValida = $dataObj !== false
                    && ($errosData === false || ($errosData['warning_count'] === 0 && $errosData['error_count'] === 0));

                if ($descricao === '' || $valor <= 0 || !$dataValida) {

                        $ignoradas++;

                        continue;

        }

                if ($categoriaId !== null) {

                        $tipoCategoria = $categoriasPermitidas[$categoriaId] ?? null;

                        if ($tipoCategoria !== $tipo) {

                                $categoriaId = null;

            }

        }

                $parametros = [
                    'uid' => $usuarioId,
                    'cat' => $categoriaId,
                    'conta' => $contaId,
                    'imp' => $importacaoId,
                    'valor' => $valor,
                    'descricao' => $descricao,
                    'data' => $dataObj->format('Y-m-d'),
                ];

                if ($tipo === 'receita') {

                        $stmtReceita->execute($parametros);

        }  else {

                        $stmtDespesa->execute($parametros);

                        criarRecorrenciaImportada($pdo, $usuarioId, array_merge($linha, [
                            'descricao' => $descricao,
                            'valor' => $valor,
                            'data' => $dataObj->format('Y-m-d'),
                            'categoria_id' => $categoriaId,
                        ]));

        }

                if (
                    $categoriaId
                    && $categoriaSugeridaId
                    && $categoriaId !== $categoriaSugeridaId
                    && !empty($linha['lembrar_regra'])
                ) {

                        $termo = termoRegraImportacao($descricao);

                        if (mb_strlen($termo) >= 3) {

                                try {

                                        $regra = $pdo->prepare(
                                            'INSERT INTO classificacao_regras (usuario_id, termo, tipo, categoria_id)
                         VALUES (:uid, :termo, :tipo, :cat)
                         ON DUPLICATE KEY UPDATE categoria_id = VALUES(categoria_id)'
                                        );

                                        $regra->execute([
                                            'uid' => $usuarioId,
                                            'termo' => $termo,
                                            'tipo' => $tipo,
                                            'cat' => $categoriaId,
                                        ]);

                }  catch (Throwable $e) {

                                        // A regra de aprendizado é opcional e não bloqueia a importação.

                }

            }

        }

                $importadas++;

    }

        $stmtAtualiza = $pdo->prepare(
            'UPDATE importacoes
         SET quantidade_importadas = :importadas,
             quantidade_ignoradas = :ignoradas,
             status = "concluida"
         WHERE id = :id'
        );

        $stmtAtualiza->execute([
            'importadas' => $importadas,
            'ignoradas' => $ignoradas,
            'id' => $importacaoId,
        ]);

        $pdo->commit();

        unset($_SESSION['importacao_preview']);

        $_SESSION['mensagem_importacao'] = "Extrato importado com sucesso. {$importadas} movimentação(ões) foram adicionadas.";

}  catch (Throwable $e) {

        if ($pdo->inTransaction()) {

                $pdo->rollBack();

    }

        error_log('CashPilot importação: ' . $e->getMessage());

        $_SESSION['erro_importacao'] = 'Não foi possível concluir a importação. Nenhuma movimentação foi salva.';

        header('Location: ../pages/importar.php');

        exit;

}

header('Location: ../pages/importacoes.php');

exit;
