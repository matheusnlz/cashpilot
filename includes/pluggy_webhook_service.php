<?php
require_once __DIR__ . '/pluggy_repository.php';

function pluggyWebhookItemId(array $payload): string
{
    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    $item = is_array($data['item'] ?? null) ? $data['item'] : [];

    return trim((string) ($payload['itemId'] ?? ($data['itemId'] ?? ($item['id'] ?? ''))));
}

function pluggyWebhookTransacoesIds(array $payload): array
{
    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    $candidatos =
        $payload['transactionIds'] ??
        ($data['transactionIds'] ?? ($payload['deletedTransactions'] ?? []));
    if (!is_array($candidatos)) {
        return [];
    }

    $ids = [];
    foreach ($candidatos as $candidato) {
        $id = is_array($candidato)
            ? trim((string) ($candidato['id'] ?? ''))
            : trim((string) $candidato);
        if ($id !== '' && preg_match('/^[a-z0-9-]{8,80}$/i', $id)) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

function pluggyWebhookUsuarioDoPayload(PDO $pdo, array $payload, string $itemId): ?int
{
    $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
    $item = is_array($data['item'] ?? null) ? $data['item'] : [];
    $clientUserId = trim(
        (string) ($payload['clientUserId'] ??
            ($data['clientUserId'] ?? ($item['clientUserId'] ?? '')))
    );
    if (preg_match('/^cashpilot-user-(\d+)$/', $clientUserId, $resultado)) {
        $usuarioId = (int) $resultado[1];
        if ($usuarioId > 0) {
            return $usuarioId;
        }
    }

    if ($itemId === '') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT usuario_id FROM pluggy_items WHERE pluggy_item_id = :item LIMIT 1'
    );
    $stmt->execute(['item' => $itemId]);
    $usuarioId = $stmt->fetchColumn();

    return $usuarioId === false ? null : (int) $usuarioId;
}

function pluggyWebhookRegistrar(PDO $pdo, array $payload, string $corpoOriginal): int
{
    $evento = mb_substr(
        trim((string) ($payload['event'] ?? ($payload['type'] ?? 'desconhecido'))),
        0,
        100
    );
    $itemId = pluggyWebhookItemId($payload);
    $usuarioId = pluggyWebhookUsuarioDoPayload($pdo, $payload, $itemId);
    $hash = hash('sha256', $corpoOriginal);

    $stmt = $pdo->prepare(
        'INSERT INTO pluggy_webhook_eventos (
            usuario_id,
            item_id,
            evento,
            evento_hash,
            payload_json
        ) VALUES (
            :uid,
            :item,
            :evento,
            :hash,
            :payload
        )
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            usuario_id = COALESCE(pluggy_webhook_eventos.usuario_id, VALUES(usuario_id)),
            item_id = COALESCE(pluggy_webhook_eventos.item_id, VALUES(item_id))'
    );
    $stmt->execute([
        'uid' => $usuarioId,
        'item' => $itemId !== '' ? mb_substr($itemId, 0, 64) : null,
        'evento' => $evento !== '' ? $evento : 'desconhecido',
        'hash' => $hash,
        'payload' => json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        )
    ]);

    return (int) $pdo->lastInsertId();
}

function pluggyWebhookProcessarEvento(PDO $pdo, int $eventoId): string
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT * FROM pluggy_webhook_eventos WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $stmt->execute(['id' => $eventoId]);
        $registro = $stmt->fetch();

        if (!$registro) {
            $pdo->rollBack();
            return 'ignorado';
        }

        if (in_array($registro['status'], ['processado', 'ignorado'], true)) {
            $pdo->commit();
            return (string) $registro['status'];
        }

        if (
            $registro['status'] === 'processando' &&
            strtotime((string) $registro['atualizado_em']) > time() - 600
        ) {
            $pdo->commit();
            return 'adiado';
        }

        if ((int) $registro['tentativas'] >= 5) {
            $stmt = $pdo->prepare(
                'UPDATE pluggy_webhook_eventos
                    SET status = "erro", erro_mensagem = "Limite de tentativas atingido"
                    WHERE id = :id'
            );
            $stmt->execute(['id' => $eventoId]);
            $pdo->commit();
            return 'erro';
        }

        $stmt = $pdo->prepare(
            'UPDATE pluggy_webhook_eventos
                SET status = "processando", tentativas = tentativas + 1, erro_mensagem = NULL
                WHERE id = :id'
        );
        $stmt->execute(['id' => $eventoId]);
        $pdo->commit();

        $evento = (string) $registro['evento'];
        $itemId = trim((string) $registro['item_id']);
        $usuarioId = (int) $registro['usuario_id'];
        $statusFinal = 'ignorado';

        if ($usuarioId > 0 && $itemId !== '' && $evento === 'item/deleted') {
            $stmt = $pdo->prepare(
                'UPDATE pluggy_items
                    SET status = "DELETED", execution_status = "DELETED"
                    WHERE usuario_id = :uid AND pluggy_item_id = :item'
            );
            $stmt->execute(['uid' => $usuarioId, 'item' => $itemId]);
            $statusFinal = 'processado';
        } elseif ($usuarioId > 0 && $itemId !== '' && $evento === 'transactions/deleted') {
            $payload = json_decode((string) $registro['payload_json'], true);
            $transacoesIds = is_array($payload) ? pluggyWebhookTransacoesIds($payload) : [];
            if ($transacoesIds) {
                $marcadores = [];
                $parametros = ['uid' => $usuarioId, 'item' => $itemId];
                foreach ($transacoesIds as $indice => $transacaoId) {
                    $chave = 'tx' . $indice;
                    $marcadores[] = ':' . $chave;
                    $parametros[$chave] = $transacaoId;
                }

                $stmt = $pdo->prepare(
                    'UPDATE pluggy_transactions
                        SET status_externo = "DELETED",
                            ignorada = IF(importada = 1, ignorada, 1)
                        WHERE usuario_id = :uid
                            AND pluggy_item_id = :item
                            AND pluggy_transaction_id IN (' .
                        implode(', ', $marcadores) .
                        ')'
                );
                $stmt->execute($parametros);
            }
            $statusFinal = 'processado';
        } elseif (
            $usuarioId > 0 &&
            $itemId !== '' &&
            (str_starts_with($evento, 'item/') || str_starts_with($evento, 'transactions/'))
        ) {
            pluggySalvarItemEFinanceiro($pdo, $usuarioId, $itemId);
            $statusFinal = 'processado';
        }

        $stmt = $pdo->prepare(
            'UPDATE pluggy_webhook_eventos
                SET status = :status, processado_em = NOW(), erro_mensagem = NULL
                WHERE id = :id'
        );
        $stmt->execute(['status' => $statusFinal, 'id' => $eventoId]);

        return $statusFinal;
    } catch (Throwable $erro) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $stmt = $pdo->prepare(
            'UPDATE pluggy_webhook_eventos
                SET status = "erro", erro_mensagem = :erro
                WHERE id = :id'
        );
        $stmt->execute([
            'erro' => mb_substr($erro->getMessage(), 0, 255),
            'id' => $eventoId
        ]);
        throw $erro;
    }
}

function pluggyWebhookProcessarPendentes(PDO $pdo, ?int $usuarioId = null, int $limite = 3): array
{
    $limite = max(1, min($limite, 10));
    $parametros = [];
    $filtroUsuario = '';
    if ($usuarioId !== null && $usuarioId > 0) {
        $filtroUsuario = ' AND usuario_id = :uid';
        $parametros['uid'] = $usuarioId;
    }

    $stmt = $pdo->prepare(
        'SELECT id
            FROM pluggy_webhook_eventos
            WHERE (
                    status IN ("pendente", "erro")
                    OR (status = "processando" AND atualizado_em < DATE_SUB(NOW(), INTERVAL 10 MINUTE))
                )
                AND tentativas < 5' .
            $filtroUsuario .
            ' ORDER BY recebido_em ASC LIMIT ' .
            $limite
    );
    $stmt->execute($parametros);
    $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $resultado = ['processados' => 0, 'ignorados' => 0, 'erros' => 0];
    foreach ($ids as $id) {
        try {
            $status = pluggyWebhookProcessarEvento($pdo, $id);
            if ($status === 'processado') {
                $resultado['processados']++;
            } elseif ($status === 'ignorado') {
                $resultado['ignorados']++;
            } elseif ($status === 'erro') {
                $resultado['erros']++;
            }
        } catch (Throwable $erro) {
            cp17Log('PluggyWebhookProcess', $erro);
            $resultado['erros']++;
        }
    }

    return $resultado;
}
