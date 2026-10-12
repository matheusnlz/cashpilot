<?php
require_once __DIR__ . '/negocio_financeiro.php';
require_once __DIR__ . '/financeiro_regras.php';

/** Uma única operação para venda, receita, itens e baixa de estoque. */
function cpRegistrarVenda(
    PDO $pdo,
    int $usuarioId,
    array $itens,
    string $data,
    ?int $contaId = null,
    ?string $observacao = null
): int {
    if (!cpValidarDataFinanceira($data) || $data > date('Y-m-d')) {
        throw new InvalidArgumentException('Informe uma data válida, até hoje, para a venda.');
    }
    $quantidades = [];
    foreach ($itens as $id => $quantidade) {
        if (!is_scalar($quantidade) || !preg_match('/^\d+$/', (string) $quantidade)) {
            throw new InvalidArgumentException('Quantidade inválida.');
        }
        if ((int) $id > 0 && (int) $quantidade > 0) {
            $quantidades[(int) $id] = (int) $quantidade;
        }
    }
    if (!$quantidades) {
        throw new InvalidArgumentException('Adicione pelo menos um item à venda.');
    }
    ksort($quantidades);
    $transacaoPropria = !$pdo->inTransaction();
    if ($transacaoPropria) {
        $pdo->beginTransaction();
    }
    try {
        if ($contaId !== null) {
            $stmt = $pdo->prepare('SELECT id FROM contas WHERE id = :id AND usuario_id = :uid');
            $stmt->execute(['id' => $contaId, 'uid' => $usuarioId]);
            if (!$stmt->fetchColumn()) {
                throw new InvalidArgumentException('Conta inválida.');
            }
        }
        $marcadores = implode(',', array_fill(0, count($quantidades), '?'));
        // Bloqueio dentro da transação impede duas vendas de consumirem o mesmo estoque.
        $stmt = $pdo->prepare(
            "SELECT * FROM produtos_servicos WHERE usuario_id = ? AND ativo = 1 AND id IN ({$marcadores}) ORDER BY id FOR UPDATE"
        );
        $stmt->execute(array_merge([$usuarioId], array_keys($quantidades)));
        $catalogo = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($catalogo) !== count($quantidades)) {
            throw new InvalidArgumentException('Um dos itens não está disponível no seu catálogo.');
        }
        $total = 0.0;
        $custo = 0.0;
        $descricoes = [];
        foreach ($catalogo as $item) {
            $qtd = $quantidades[(int) $item['id']];
            if (
                $item['tipo'] === 'produto' &&
                $item['controlar_estoque'] &&
                (int) $item['estoque_atual'] < $qtd
            ) {
                throw new InvalidArgumentException(
                    'Estoque insuficiente para ' . $item['nome'] . '.'
                );
            }
            $total += (float) $item['preco_venda'] * $qtd;
            $custo += (float) $item['custo_unitario'] * $qtd;
            $descricoes[] = $item['nome'] . ' x' . $qtd;
        }
        if ($total <= 0 || $total > 9999999999.99) {
            throw new InvalidArgumentException('Valor total da venda inválido.');
        }
        $categoria = cpCategoriaId($pdo, $usuarioId, 'Vendas', 'receita');
        $stmt = $pdo->prepare(
            'INSERT INTO
                    receitas (
                        usuario_id,
                        categoria_id,
                        conta_id,
                        valor,
                        descricao,
                        data_receita
                    )
                VALUES
                    (:uid, :cat, :conta, :valor, :descricao, :data)'
        );
        $stmt->execute([
            'uid' => $usuarioId,
            'cat' => $categoria,
            'conta' => $contaId,
            'valor' => round($total, 2),
            'descricao' => mb_substr('Venda · ' . implode(', ', $descricoes), 0, 180),
            'data' => $data
        ]);
        $receitaId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare(
            'INSERT INTO
                    vendas (
                        usuario_id,
                        receita_id,
                        data_venda,
                        valor_bruto,
                        custo_total,
                        observacao
                    )
                VALUES
                    (:uid, :rid, :data, :total, :custo, :obs)'
        );
        $stmt->execute([
            'uid' => $usuarioId,
            'rid' => $receitaId,
            'data' => $data,
            'total' => round($total, 2),
            'custo' => round($custo, 2),
            'obs' => $observacao === null ? null : mb_substr($observacao, 0, 255)
        ]);
        $vendaId = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare(
            'UPDATE receitas SET venda_id = :vid WHERE id = :rid AND usuario_id = :uid'
        );
        $stmt->execute(['vid' => $vendaId, 'rid' => $receitaId, 'uid' => $usuarioId]);
        foreach ($catalogo as $item) {
            $qtd = $quantidades[(int) $item['id']];
            $stmt = $pdo->prepare(
                'INSERT INTO
                        venda_itens (
                            venda_id,
                            produto_servico_id,
                            nome_item,
                            tipo,
                            quantidade,
                            preco_unitario,
                            custo_unitario
                        )
                    VALUES
                        (:vid, :pid, :nome, :tipo, :qtd, :preco, :custo)'
            );
            $stmt->execute([
                'vid' => $vendaId,
                'pid' => $item['id'],
                'nome' => $item['nome'],
                'tipo' => $item['tipo'],
                'qtd' => $qtd,
                'preco' => $item['preco_venda'],
                'custo' => $item['custo_unitario']
            ]);
            if ($item['tipo'] === 'produto' && $item['controlar_estoque']) {
                $stmt = $pdo->prepare(
                    'UPDATE produtos_servicos
                        SET
                            estoque_atual = estoque_atual - :qtd
                        WHERE
                            id = :id
                            AND usuario_id = :uid'
                );
                $stmt->execute(['qtd' => $qtd, 'id' => $item['id'], 'uid' => $usuarioId]);
                $stmt = $pdo->prepare(
                    'INSERT INTO
                            movimentacoes_estoque (
                                usuario_id,
                                produto_id,
                                tipo,
                                quantidade,
                                custo_unitario,
                                referencia,
                                venda_id,
                                data_movimentacao
                            )
                        VALUES
                            (
                                :uid,
                                :pid,
                                "venda",
                                :qtd,
                                :custo,
                                :ref,
                                :vid,
                                :data
                            )'
                );
                $stmt->execute([
                    'uid' => $usuarioId,
                    'pid' => $item['id'],
                    'qtd' => -$qtd,
                    'custo' => $item['custo_unitario'],
                    'ref' => 'Venda #' . $vendaId,
                    'vid' => $vendaId,
                    'data' => $data . ' 12:00:00'
                ]);
            }
        }
        if ($transacaoPropria) {
            $pdo->commit();
        }
        return $vendaId;
    } catch (Throwable $erro) {
        if ($transacaoPropria && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}
