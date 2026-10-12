<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/pluggy_repository.php';

exigirLogin();
exigirPost();
validarCsrf();

$pdo = conectar();
$uid = (int) usuarioLogadoId();
$itemId = trim((string) ($_POST['item_id'] ?? ''));

if (!pluggyItemPertenceAoUsuario($pdo, $uid, $itemId)) {
    $_SESSION['open_finance_erro'] = 'Conexão bancária não encontrada.';
    header('Location: ../pages/open_finance.php');
    exit();
}

try {
    pluggyExcluirItem($itemId);
} catch (Throwable $e) {
    cp17Log('PluggyDisconnect', $e);
    if ((int) $e->getCode() !== 404) {
        $_SESSION['open_finance_erro'] =
            'A instituição não confirmou a desconexão. Tente novamente; sua conexão foi mantida.';
        header('Location: ../pages/open_finance.php');
        exit();
    }
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('UPDATE pluggy_webhook_eventos
        SET payload_json = "{}", erro_mensagem = NULL, item_id = NULL, status = "ignorado"
        WHERE item_id = ? AND (usuario_id = ? OR usuario_id IS NULL)');
    $stmt->execute([$itemId, $uid]);
    $stmt = $pdo->prepare(
        'DELETE FROM pluggy_transactions WHERE usuario_id = :uid AND pluggy_item_id = :item'
    );
    $stmt->execute(['uid' => $uid, 'item' => $itemId]);
    $stmt = $pdo->prepare(
        'DELETE FROM pluggy_accounts WHERE usuario_id = :uid AND pluggy_item_id = :item'
    );
    $stmt->execute(['uid' => $uid, 'item' => $itemId]);
    $stmt = $pdo->prepare(
        'DELETE FROM pluggy_items WHERE usuario_id = :uid AND pluggy_item_id = :item'
    );
    $stmt->execute(['uid' => $uid, 'item' => $itemId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    cp17Log('PluggyDisconnectLocal', $e);
    $_SESSION['open_finance_erro'] =
        'A instituição foi desconectada, mas a limpeza local não concluiu. Tente novamente para finalizar.';
    header('Location: ../pages/open_finance.php');
    exit();
}
$_SESSION['open_finance_ok'] =
    'Instituição desconectada. Os lançamentos já importados foram mantidos.';
header('Location: ../pages/open_finance.php');
exit();
