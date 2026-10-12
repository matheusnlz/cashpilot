<?php
require_once __DIR__ . '/pluggy_service.php';

function pluggyGarantirContaLocal(PDO $pdo, int $usuarioId, array $account): ?int
{
    $pluggyAccountId = (string) ($account['id'] ?? '');
    if ($pluggyAccountId === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT conta_id FROM pluggy_accounts WHERE pluggy_account_id = :aid AND usuario_id = :uid LIMIT 1'
    );
    $stmt->execute(['aid' => $pluggyAccountId, 'uid' => $usuarioId]);
    $existente = $stmt->fetchColumn();
    if ($existente) {
        return (int) $existente;
    }

    $nome = trim((string) ($account['marketingName'] ?? ($account['name'] ?? 'Conta conectada')));
    $tipoExterno = strtoupper((string) ($account['type'] ?? 'BANK'));
    $subtipo = strtoupper((string) ($account['subtype'] ?? ''));

    $tipoLocal = 'outra';
    if ($tipoExterno === 'BANK') {
        $tipoLocal = str_contains($subtipo, 'SAVING') ? 'poupanca' : 'corrente';
    } elseif ($tipoExterno === 'CREDIT') {
        $tipoLocal = 'outra';
    }

    $stmt = $pdo->prepare(
        'INSERT INTO
                contas (usuario_id, nome, tipo, saldo_inicial, padrao)
            VALUES
                (:uid, :nome, :tipo, 0, 0)'
    );
    $stmt->execute([
        'uid' => $usuarioId,
        'nome' => mb_substr($nome . ' · Open Finance', 0, 80),
        'tipo' => $tipoLocal
    ]);

    return (int) $pdo->lastInsertId();
}

function pluggySalvarItemEFinanceiro(
    PDO $pdo,
    int $usuarioId,
    string $itemId,
    ?string $perfil = null
): array {
    $item = pluggyObterItem($itemId);
    $clientUserId = (string) ($item['clientUserId'] ?? '');
    if (
        $clientUserId !== 'cashpilot-user-' . $usuarioId &&
        ($clientUserId !== '' || !pluggyItemPertenceAoUsuario($pdo, $usuarioId, $itemId))
    ) {
        throw new RuntimeException('Esta conexão não pertence ao usuário autenticado.');
    }

    $perfilFinal = in_array($perfil, ['pessoa_fisica', 'mei'], true) ? $perfil : null;

    if ($perfilFinal === null) {
        $stmtPerfil = $pdo->prepare('SELECT tipo_perfil FROM usuarios WHERE id = :uid LIMIT 1');
        $stmtPerfil->execute(['uid' => $usuarioId]);
        $perfilFinal = (string) ($stmtPerfil->fetchColumn() ?: 'pessoa_fisica');
    }

    $connector = is_array($item['connector'] ?? null) ? $item['connector'] : [];
    $connectorId = isset($item['connectorId'])
        ? (int) $item['connectorId']
        : (isset($connector['id'])
            ? (int) $connector['id']
            : null);
    $instituicao = trim(
        (string) ($connector['name'] ??
            ($connector['institutionName'] ?? ($item['name'] ?? 'Instituição conectada')))
    );
    $status = (string) ($item['status'] ?? 'UPDATED');
    $executionStatus = (string) ($item['executionStatus'] ?? $status);
    $erro = is_array($item['error'] ?? null) ? $item['error'] : [];

    $pdo->beginTransaction();
    try {
        // Serializa sincronizações do mesmo usuário e mantém a gravação indivisível.
        $bloqueio = $pdo->prepare('SELECT id FROM usuarios WHERE id = :uid FOR UPDATE');
        $bloqueio->execute(['uid' => $usuarioId]);
        if (!$bloqueio->fetchColumn()) {
            throw new RuntimeException('Usuário não encontrado.');
        }
        $dono = $pdo->prepare('SELECT usuario_id FROM pluggy_items WHERE pluggy_item_id = :item');
        $dono->execute(['item' => $itemId]);
        $donoId = $dono->fetchColumn();
        if ($donoId !== false && (int) $donoId !== $usuarioId) {
            throw new RuntimeException('Esta conexão já está vinculada a outra conta.');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO
                    pluggy_items (
                        usuario_id,
                        pluggy_item_id,
                        connector_id,
                        instituicao_nome,
                        status,
                        execution_status,
                        perfil,
                        erro_codigo,
                        erro_mensagem,
                        ultima_sincronizacao
                    )
                VALUES
                    (
                        :uid,
                        :item,
                        :connector,
                        :instituicao,
                        :status,
                        :execution,
                        :perfil,
                        :erro_codigo,
                        :erro_mensagem,
                        NOW()
                    )
                ON DUPLICATE KEY UPDATE
                    connector_id = VALUES(connector_id),
                    instituicao_nome = VALUES(instituicao_nome),
                    status = VALUES(status),
                    execution_status = VALUES(execution_status),
                    perfil = VALUES(perfil),
                    erro_codigo = VALUES(erro_codigo),
                    erro_mensagem = VALUES(erro_mensagem),
                    ultima_sincronizacao = NOW()'
        );
        $stmt->execute([
            'uid' => $usuarioId,
            'item' => $itemId,
            'connector' => $connectorId,
            'instituicao' => mb_substr($instituicao, 0, 150),
            'status' => mb_substr($status, 0, 50),
            'execution' => mb_substr($executionStatus, 0, 50),
            'perfil' => $perfilFinal,
            'erro_codigo' =>
                mb_substr((string) ($erro['code'] ?? ($erro['codeDescription'] ?? '')), 0, 80) ?:
                null,
            'erro_mensagem' => mb_substr((string) ($erro['message'] ?? ''), 0, 255) ?: null
        ]);

        $contas = pluggyListarContas($itemId);
        $totalTransacoes = 0;

        foreach ($contas as $account) {
            if (!is_array($account) || empty($account['id'])) {
                continue;
            }

            $contaLocalId = pluggyGarantirContaLocal($pdo, $usuarioId, $account);
            $accountId = (string) $account['id'];
            $nomeConta = trim(
                (string) ($account['marketingName'] ?? ($account['name'] ?? 'Conta'))
            );
            $tipoConta = strtoupper((string) ($account['type'] ?? 'BANK'));

            $stmtConta = $pdo->prepare(
                'INSERT INTO
                        pluggy_accounts (
                            usuario_id,
                            pluggy_item_id,
                            pluggy_account_id,
                            conta_id,
                            nome,
                            tipo,
                            subtipo,
                            numero_mascarado,
                            saldo,
                            moeda,
                            dados_credito_json
                        )
                    VALUES
                        (
                            :uid,
                            :item,
                            :account,
                            :conta_id,
                            :nome,
                            :tipo,
                            :subtipo,
                            :numero,
                            :saldo,
                            :moeda,
                            :credito
                        )
                    ON DUPLICATE KEY UPDATE
                        pluggy_item_id = VALUES(pluggy_item_id),
                        conta_id = COALESCE(
                            pluggy_accounts.conta_id,
                            VALUES
                                (conta_id)
                        ),
                        nome = VALUES(nome),
                        tipo = VALUES(tipo),
                        subtipo = VALUES(subtipo),
                        numero_mascarado = VALUES(numero_mascarado),
                        saldo = VALUES(saldo),
                        moeda = VALUES(moeda),
                        dados_credito_json = VALUES(dados_credito_json)'
            );
            $stmtConta->execute([
                'uid' => $usuarioId,
                'item' => $itemId,
                'account' => $accountId,
                'conta_id' => $contaLocalId,
                'nome' => mb_substr($nomeConta, 0, 180),
                'tipo' => mb_substr($tipoConta, 0, 30),
                'subtipo' => mb_substr((string) ($account['subtype'] ?? ''), 0, 60) ?: null,
                'numero' => mb_substr((string) ($account['number'] ?? ''), 0, 80) ?: null,
                'saldo' => isset($account['balance']) ? (float) $account['balance'] : null,
                'moeda' => mb_substr((string) ($account['currencyCode'] ?? 'BRL'), 0, 3),
                'credito' => isset($account['creditData'])
                    ? json_encode(
                        $account['creditData'],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    )
                    : null
            ]);

            // Primeira sincronização: até 12 meses, conforme disponibilidade da instituição.
            $dateFrom = (new DateTimeImmutable('-12 months'))->format('Y-m-d');
            $transacoes = pluggyListarTransacoes($accountId, $dateFrom);

            foreach ($transacoes as $transacao) {
                $txId = (string) ($transacao['id'] ?? '');
                $data = pluggyNormalizarData((string) ($transacao['date'] ?? ''));
                if ($txId === '' || $data === null) {
                    continue;
                }

                $descricao = trim(
                    (string) ($transacao['description'] ??
                        ($transacao['descriptionRaw'] ?? 'Movimentação bancária'))
                );
                $tipoSugerido = pluggyTipoSugerido($transacao, $tipoConta);
                $stmtTx = $pdo->prepare(
                    'INSERT INTO
                            pluggy_transactions (
                                usuario_id,
                                pluggy_item_id,
                                pluggy_account_id,
                                pluggy_transaction_id,
                                descricao,
                                valor,
                                data_transacao,
                                tipo_externo,
                                tipo_sugerido,
                                categoria_externa,
                                categoria_externa_id,
                                status_externo
                            )
                        VALUES
                            (
                                :uid,
                                :item,
                                :account,
                                :tx,
                                :descricao,
                                :valor,
                                :data,
                                :tipo_externo,
                                :tipo_sugerido,
                                :categoria,
                                :categoria_id,
                                :status
                            )
                        ON DUPLICATE KEY UPDATE
                            descricao = VALUES(descricao),
                            valor = VALUES(valor),
                            data_transacao = VALUES(data_transacao),
                            tipo_externo = VALUES(tipo_externo),
                            tipo_sugerido = IF(
                                pluggy_transactions.importada = 1,
                                pluggy_transactions.tipo_sugerido,
                                VALUES
                                    (tipo_sugerido)
                            ),
                            categoria_externa = VALUES(categoria_externa),
                            categoria_externa_id = VALUES(categoria_externa_id),
                            status_externo = VALUES(status_externo)'
                );
                $stmtTx->execute([
                    'uid' => $usuarioId,
                    'item' => $itemId,
                    'account' => $accountId,
                    'tx' => $txId,
                    'descricao' => mb_substr($descricao, 0, 255),
                    'valor' => (float) ($transacao['amount'] ?? 0),
                    'data' => $data,
                    'tipo_externo' => mb_substr((string) ($transacao['type'] ?? ''), 0, 30) ?: null,
                    'tipo_sugerido' => $tipoSugerido,
                    'categoria' =>
                        mb_substr((string) ($transacao['category'] ?? ''), 0, 120) ?: null,
                    'categoria_id' =>
                        mb_substr((string) ($transacao['categoryId'] ?? ''), 0, 40) ?: null,
                    'status' => mb_substr((string) ($transacao['status'] ?? ''), 0, 30) ?: null
                ]);
                // Uma pendência já importada passa a realizada quando o banco confirmar.
                $statusLocal =
                    strtoupper((string) ($transacao['status'] ?? '')) === 'POSTED'
                        ? 'efetivado'
                        : 'pendente';
                foreach (
                    ['receitas' => 'receita_id', 'despesas' => 'despesa_id']
                    as $tabela => $vinculo
                ) {
                    $atualizar = $pdo->prepare(
                        "UPDATE {$tabela} SET status = :status
                     WHERE usuario_id = :uid AND id = (
                         SELECT {$vinculo} FROM pluggy_transactions
                         WHERE pluggy_transaction_id = :tx AND usuario_id = :dono AND importada = 1
                     )"
                    );
                    $atualizar->execute([
                        'status' => $statusLocal,
                        'uid' => $usuarioId,
                        'tx' => $txId,
                        'dono' => $usuarioId
                    ]);
                }
                $totalTransacoes++;
            }
        }

        $pdo->commit();
        return [
            'item' => $item,
            'contas' => count($contas),
            'transacoes_processadas' => $totalTransacoes,
            'status' => $status,
            'execution_status' => $executionStatus,
            'estado' => pluggyEstadoApresentacao([
                'status' => $status,
                'execution_status' => $executionStatus
            ])
        ];
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $erro;
    }
}

function pluggyItemPertenceAoUsuario(PDO $pdo, int $usuarioId, string $itemId): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM pluggy_items WHERE usuario_id = :uid AND pluggy_item_id = :item LIMIT 1'
    );
    $stmt->execute(['uid' => $usuarioId, 'item' => $itemId]);
    return (bool) $stmt->fetchColumn();
}
