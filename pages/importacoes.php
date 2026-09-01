<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$tituloPagina = 'Histórico de importações';
$pdo = conectar();
$usuarioId = usuarioLogadoId();

$stmt = $pdo->prepare(
    'SELECT i.*, c.nome AS conta_nome
     FROM importacoes i
     LEFT JOIN contas c ON c.id = i.conta_id
     WHERE i.usuario_id = :uid
     ORDER BY i.data_importacao DESC'
);
$stmt->execute(['uid' => $usuarioId]);
$importacoes = $stmt->fetchAll();

$mensagem = $_SESSION['mensagem_importacao'] ?? null;
unset($_SESSION['mensagem_importacao']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<a href="perfil.php" class="voltar-perfil">← Voltar para Meu Perfil</a>
<div class="topo-pagina">
    <div>
        <h1>Histórico de importações</h1>
        <p>Acompanhe todos os extratos que você já importou.</p>
    </div>
    <a href="importar.php" class="btn btn-primario">Importar novo extrato</a>
</div>

<?php if ($mensagem): ?>
    <div class="alerta-mensagem sucesso" style="max-width:640px;"><?= limpar($mensagem) ?></div>
<?php endif; ?>

<div class="cartao">
    <?php if (empty($importacoes)): ?>
        <div class="texto-vazio">
            <p>Você ainda não importou nenhum extrato.</p>
            <p style="margin-top:6px;">
                <a href="importar.php" class="btn btn-primario" style="margin-top:12px;">Importar extrato</a>
            </p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr><th>Conta</th><th>Arquivo</th><th>Data</th><th>Movimentações</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($importacoes as $imp): ?>
                <tr>
                    <td><?= limpar($imp['conta_nome'] ?? 'Conta removida') ?></td>
                    <td><?= limpar($imp['nome_arquivo']) ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($imp['data_importacao'])) ?></td>
                    <td><?= (int) $imp['quantidade_importadas'] ?> importada(s)<?= $imp['quantidade_ignoradas'] > 0 ? ', ' . (int) $imp['quantidade_ignoradas'] . ' ignorada(s)' : '' ?></td>
                    <td>
                        <?php if ($imp['status'] === 'concluida'): ?>
                            <span class="badge" style="background:#E8F0E9; color:var(--cor-sucesso);">Importado com sucesso</span>
                        <?php elseif ($imp['status'] === 'erro'): ?>
                            <span class="badge" style="background:#F7E9E8; color:var(--cor-erro);">Erro na importação</span>
                        <?php else: ?>
                            <span class="badge">Processando</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
