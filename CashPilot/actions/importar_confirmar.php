<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$pdo = conectar();
$usuarioId = usuarioLogadoId();

$preview = $_SESSION['importacao_preview'] ?? null;
if (!$preview || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../pages/importar.php');
    exit;
}

$linhasPostadas = $_POST['linhas'] ?? [];
$contaId = (int) $preview['conta_id'];

$stmtConta = $pdo->prepare('SELECT id FROM contas WHERE id = :id AND usuario_id = :uid');
$stmtConta->execute(['id' => $contaId, 'uid' => $usuarioId]);
if (!$stmtConta->fetch()) {
    $_SESSION['erro_importacao'] = 'Conta inválida para esta importação.';
    header('Location: ../pages/importar.php');
    exit;
}

try {
    $pdo->beginTransaction();

    $stmtImportacao = $pdo->prepare(
        'INSERT INTO importacoes (usuario_id, conta_id, nome_arquivo, hash_arquivo, quantidade_linhas, status)
         VALUES (:uid, :conta, :nome, :hash, :qtd, "processando")'
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

        $descricao = trim($linha['descricao'] ?? '');
        $valor = (float) str_replace(',', '.', $linha['valor'] ?? '0');
        $data = $linha['data'] ?? null;
        $tipo = ($linha['tipo'] ?? '') === 'receita' ? 'receita' : 'despesa';
        $categoriaId = !empty($linha['categoria_id']) ? (int) $linha['categoria_id'] : null;

        if ($descricao === '' || $valor <= 0 || !$data) {
            $ignoradas++;
            continue;
        }

        $parametros = [
            'uid' => $usuarioId, 'cat' => $categoriaId, 'conta' => $contaId, 'imp' => $importacaoId,
            'valor' => $valor, 'descricao' => $descricao, 'data' => $data,
        ];

        if ($tipo === 'receita') {
            $stmtReceita->execute($parametros);
        } else {
            $stmtDespesa->execute($parametros);
        }
        $importadas++;
    }

    $stmtAtualizaImportacao = $pdo->prepare(
        'UPDATE importacoes SET quantidade_importadas = :importadas, quantidade_ignoradas = :ignoradas, status = "concluida" WHERE id = :id'
    );
    $stmtAtualizaImportacao->execute(['importadas' => $importadas, 'ignoradas' => $ignoradas, 'id' => $importacaoId]);

    $pdo->commit();

    unset($_SESSION['importacao_preview']);
    $_SESSION['mensagem_importacao'] = "Extrato importado com sucesso. {$importadas} movimentação(ões) foram adicionadas à sua conta.";
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['erro_importacao'] = 'Não foi possível concluir a importação. Nenhuma movimentação foi salva.';
    header('Location: ../pages/importar.php');
    exit;
}

header('Location: ../pages/importacoes.php');
exit;
