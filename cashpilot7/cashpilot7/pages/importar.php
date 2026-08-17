<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$tituloPagina = 'Importar extrato';
$pdo = conectar();
$usuarioId = usuarioLogadoId();

// Limpa qualquer pré-visualização pendente de uma importação anterior não concluída
unset($_SESSION['importacao_preview']);

$stmt = $pdo->prepare('SELECT id, nome, tipo FROM contas WHERE usuario_id = :uid ORDER BY padrao DESC, nome');
$stmt->execute(['uid' => $usuarioId]);
$contas = $stmt->fetchAll();

$erro = $_SESSION['erro_importacao'] ?? null;
unset($_SESSION['erro_importacao']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>
<a href="perfil.php" class="voltar-perfil">← Voltar para Meu Perfil</a>
<div class="topo-pagina">
    <div>
        <h1>Importar extrato</h1>
        <p>Envie um arquivo CSV do seu banco e o CashPilot organiza e classifica as movimentações para você.</p>
    </div>
</div>

<?php if ($erro): ?>
    <div class="alerta-mensagem erro" style="max-width:640px;"><?= limpar($erro) ?></div>
<?php endif; ?>

<div class="grade-dupla">
    <div class="cartao">
        <h3 style="margin-bottom:16px;">1. Selecione a conta e o arquivo</h3>
        <form autocomplete="off" action="../actions/importar_upload.php" method="POST" enctype="multipart/form-data">
            <?= csrfCampo() ?>
            <div class="form-grupo">
                <label for="conta_id">Conta</label>
                <select id="conta_id" name="conta_id" required>
                    <?php foreach ($contas as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= limpar($c['nome']) ?></option>
                    <?php endforeach; ?>
                    <option value="nova">+ Criar nova conta</option>
                </select>
            </div>

            <div class="form-grupo" id="grupo-nova-conta" style="display:none;">
                <label for="nome_nova_conta">Nome da nova conta</label>
                <input autocomplete="off" type="text" id="nome_nova_conta" name="nome_nova_conta" placeholder="Ex: Nubank, Banco do Brasil, Caixa da loja">
            </div>

            <div class="form-grupo">
                <label for="arquivo_csv">Arquivo CSV do extrato</label>
                <input type="file" id="arquivo_csv" name="arquivo_csv" accept=".csv" required>
            </div>

            <button type="submit" class="btn btn-primario btn-bloco">Enviar e pré-visualizar</button>
        </form>
    </div>

    <div class="cartao">
        <h3 style="margin-bottom:12px;">Como funciona</h3>
        <ol style="padding-left:18px; display:flex; flex-direction:column; gap:10px; font-size:13.5px; color:var(--cor-texto-suave);">
            <li>Envie o CSV exportado do seu banco (colunas de data, descrição e valor).</li>
            <li>O CashPilot identifica automaticamente o tipo e a categoria de cada movimentação.</li>
            <li>Você revisa e corrige o que quiser antes de confirmar.</li>
            <li>As movimentações são salvas na conta escolhida e o dashboard é atualizado.</li>
        </ol>
        <p style="font-size:12.5px; color:var(--cor-texto-fraco); margin-top:16px;">
            Tamanho máximo: 2MB · Formato aceito: .csv
        </p>
    </div>
</div>

<script>
    document.getElementById('conta_id').addEventListener('change', function () {
        document.getElementById('grupo-nova-conta').style.display = this.value === 'nova' ? 'block' : 'none';
        document.getElementById('nome_nova_conta').required = this.value === 'nova';
    });
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
