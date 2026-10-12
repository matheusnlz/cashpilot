<?php
require_once __DIR__ . '/../includes/auth.php';
exigirLogin();
require_once __DIR__ . '/../database/conexao.php';
$tituloPagina = 'Privacidade e dados';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>


<div class="page-head">
    <div><span class="eyebrow">PRIVACIDADE</span>
        <h1>Seus dados, sob seu controle</h1>
        <p>Exporte suas informações ou solicite a exclusão definitiva da conta.</p>
    </div>
</div>
<div class="cp15-privacy-grid">
    <section class="surface-card">
        <h2>Exportar dados</h2>
        <p>Baixe os dados de cadastro, registros financeiros, negócio, conversas e preferências
            vinculados à sua conta em JSON. O arquivo é para consulta; não é um backup para restaurar o sistema.</p>
        <form method="POST" action="../actions/exportar_dados.php">
            <?= csrfCampo() ?>
            <button type="submit" class="btn btn-primario">Baixar meus dados</button>
        </form>
        <p>Se preferir consultar os lançamentos em planilha, use o CSV:</p><a
            class="btn btn-secundario" href="../actions/exportar.php?token=<?= urlencode(csrfToken()) ?>">Exportar lançamentos</a>
    </section>
    <section class="surface-card cp15-danger-zone">
        <h2>Excluir conta</h2>
        <p>Se houver banco conectado, primeiro <a href="open_finance.php">desconecte as instituições</a>
            para encerrar o acesso no provedor. Depois confirme a exclusão abaixo.</p>
        <p>A exclusão remove a conta e os registros ligados a ela. Esta ação não pode ser desfeita.
        </p>
        <form method="POST" action="../actions/excluir_conta.php"
            data-confirm="Excluir conta definitivamente?"
            data-confirm-message="Todos os dados vinculados à sua conta serão removidos.">
            <?= csrfCampo() ?><label>Digite EXCLUIR para confirmar
                <input name="confirmacao" required autocomplete="off">
            </label>
            <button class="btn btn-perigo">Excluir minha conta</button>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
