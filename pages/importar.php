<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$tituloPagina = 'Importar extrato';
$pdo = conectar();
$uid = (int) usuarioLogadoId();
unset($_SESSION['importacao_preview']);

$stmt = $pdo->prepare(
    'SELECT id, nome, tipo FROM contas WHERE usuario_id = :uid ORDER BY padrao DESC, nome'
);
$stmt->execute(['uid' => $uid]);
$contas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$erro = $_SESSION['erro_importacao'] ?? null;
unset($_SESSION['erro_importacao']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>


<div class="page-head">
    <div>
        <a href="perfil.php" class="back-link">← Voltar para Configurações</a>
        <span class="eyebrow">IMPORTAÇÃO INTELIGENTE</span>
        <h1>Importar extrato</h1>
        <p>
            Envie um extrato CSV, revise as movimentações e confirme antes de salvar.
            Para sincronizar automaticamente, acesse <a href="open_finance.php">Conecte seu banco</a>.
        </p>
    </div>
</div>

<?php if ($erro): ?>


<div class="alerta-mensagem erro"><?= limpar($erro) ?></div>

<?php endif; ?>


<div class="import-clean-layout">
    <section class="surface-card">
        <form action="../actions/importar_upload.php" method="POST" enctype="multipart/form-data"
            autocomplete="off" id="importForm">
            <?= csrfCampo() ?>

            <div class="import-step">
                <span>1</span>
                <div>
                    <strong>Escolha a conta</strong>
                    <small>As movimentações confirmadas serão vinculadas a ela.</small>
                </div>
            </div>

            <div class="form-grupo">
                <label for="conta_id">Conta</label>
                <select id="conta_id" name="conta_id" required>

                    <?php foreach ($contas as $conta): ?>


                    <option value="<?= (int) $conta['id'] ?>"><?= limpar($conta['nome']) ?></option>

                    <?php endforeach; ?>


                    <option value="nova">Criar nova conta</option>
                </select>
            </div>

            <div class="form-grupo" id="grupo-nova-conta" hidden>
                <label for="nome_nova_conta">Nome da nova conta</label>
                <input id="nome_nova_conta" name="nome_nova_conta"
                    placeholder="Ex.: Nubank, Inter, Conta da loja">
            </div>

            <div class="import-step">
                <span>2</span>
                <div>
                    <strong>Prepare seu CSV</strong>
                    <small>Inclua data, descrição e valor. Valores negativos representam despesas.</small>
                </div>
            </div>

            <input type="hidden" name="formato" value="csv">
            <p class="secao-ajuda">Separadores aceitos: ponto e vírgula, vírgula, tabulação e barra vertical.
                <a href="../assets/exemplos/extrato_csv.csv" download>Baixar CSV de exemplo</a>.</p>

            <div class="import-step">
                <span>3</span>
                <div>
                    <strong>Envie o arquivo</strong>
                    <small>CSV · máximo 2 MB · até 3.000 linhas para análise.</small>
                </div>
            </div>

            <label class="upload-zone" for="arquivo_extrato">
                <input type="file" id="arquivo_extrato" name="arquivo_extrato"
                    accept=".csv,text/csv" required hidden>
                <span class="upload-icon">⇧</span>
                <strong>Arraste ou selecione seu extrato</strong>
                <small id="uploadFile">Nenhum arquivo selecionado</small>
            </label>

            <button class="btn btn-primario btn-bloco" style="margin-top:14px">
                Analisar extrato
            </button>
        </form>
    </section>

    <aside class="surface-card import-how">
        <span class="eyebrow">COMO FUNCIONA</span>
        <h2>O CashPilot não importa no escuro</h2>
        <ol>
            <li>
                <span>01</span>
                <div>
                    <strong>Leitura</strong>
                    <p>O leitor CSV detecta separador, codificação e colunas. Confira as informações
                        reconhecidas na próxima etapa.</p>
                </div>
            </li>
            <li>
                <span>02</span>
                <div>
                    <strong>Validação</strong>
                    <p>Datas, valores, tipo, duplicatas e confiança da leitura são avaliados antes
                        da confirmação.</p>
                </div>
            </li>
            <li>
                <span>03</span>
                <div>
                    <strong>Revisão</strong>
                    <p>Você pode corrigir descrição, data, valor, tipo e categoria antes de salvar.
                    </p>
                </div>
            </li>
            <li>
                <span>04</span>
                <div>
                    <strong>Confirmação</strong>
                    <p>Nenhuma movimentação entra no financeiro até você confirmar.</p>
                </div>
            </li>
        </ol>

        <div class="import-note">
            <strong>Outra forma de registrar</strong>
            <p>Você também pode cadastrar receitas e despesas manualmente ou conectar uma instituição
                pela Pluggy, conforme a disponibilidade da integração.</p>
        </div>
    </aside>
</div>

<script>
    const conta = document.getElementById('conta_id');
    const grupo = document.getElementById('grupo-nova-conta');
    const nome = document.getElementById('nome_nova_conta');
    const arquivo = document.getElementById('arquivo_extrato');
    const fileText = document.getElementById('uploadFile');

    function syncConta() {
        const nova = conta.value === 'nova';
        grupo.hidden = !nova;
        nome.required = nova;
    }

    conta.addEventListener('change', syncConta);
    arquivo.addEventListener('change', () => {
        const selecionado = arquivo.files[0];
        const valido = !selecionado || (/\.csv$/i.test(selecionado.name) && selecionado.size <= 2 * 1024 * 1024);
        arquivo.setCustomValidity(valido ? '' : 'Selecione um CSV de até 2 MB.');
        fileText.textContent = selecionado?.name || 'Nenhum arquivo selecionado';
    });
    syncConta();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
