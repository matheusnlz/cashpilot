<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
exigirLogin();

$tituloPagina = 'Importar extrato';
$pdo = conectar();
$uid = (int) usuarioLogadoId();
unset($_SESSION['importacao_preview']);

$stmt = $pdo->prepare('SELECT id, nome, tipo FROM contas WHERE usuario_id = :uid ORDER BY padrao DESC, nome');
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
            OFX e CSV são os formatos recomendados. PDFs com texto selecionável também podem ser analisados
            pelo leitor local e sempre passam por revisão antes de salvar.
        </p>
    </div>
</div>

<?php if ($erro): ?>
    <div class="alerta-mensagem erro"><?= limpar($erro) ?></div>
<?php endif; ?>

<div class="import-clean-layout">
    <section class="surface-card">
        <form
            action="../actions/importar_upload.php"
            method="POST"
            enctype="multipart/form-data"
            autocomplete="off"
            id="importForm"
        >
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
                    <option value="nova">＋ Criar nova conta</option>
                </select>
            </div>

            <div class="form-grupo" id="grupo-nova-conta" hidden>
                <label for="nome_nova_conta">Nome da nova conta</label>
                <input id="nome_nova_conta" name="nome_nova_conta" placeholder="Ex.: Nubank, Inter, Conta da loja">
            </div>

            <div class="import-step">
                <span>2</span>
                <div>
                    <strong>Escolha o formato</strong>
                    <small>Quanto mais estruturado o arquivo, maior tende a ser a precisão.</small>
                </div>
            </div>

            <div class="import-format-switch cp144-format-switch">
                <label>
                    <input type="radio" name="formato" value="ofx" checked>
                    <span>
                        <b>OFX</b>
                        <small>Mais confiável para extratos</small>
                    </span>
                </label>
                <label>
                    <input type="radio" name="formato" value="csv">
                    <span>
                        <b>CSV</b>
                        <small>Detecção automática de colunas</small>
                    </span>
                </label>
                <label>
                    <input type="radio" name="formato" value="pdf">
                    <span>
                        <b>PDF</b>
                        <small>Leitura por layout + revisão</small>
                    </span>
                </label>
            </div>

            <div class="form-grupo" id="bancoPdf" hidden>
                <label for="banco_pdf">Banco do PDF</label>
                <select name="banco_pdf" id="banco_pdf">
                    <option value="auto">Detectar automaticamente</option>
                    <option value="nubank">Nubank</option>
                    <option value="inter">Banco Inter</option>
                    <option value="itau">Itaú</option>
                    <option value="bradesco">Bradesco</option>
                    <option value="santander">Santander</option>
                    <option value="bb">Banco do Brasil</option>
                    <option value="caixa">Caixa</option>
                    <option value="outro">Outro / formato genérico</option>
                </select>
                <small class="secao-ajuda">
                    Se a detecção automática falhar, escolher o banco ajuda o leitor a interpretar o documento.
                </small>
            </div>

            <div class="import-step">
                <span>3</span>
                <div>
                    <strong>Envie o arquivo</strong>
                    <small id="formatHelp">OFX/QFX · máximo 4 MB.</small>
                </div>
            </div>

            <label class="upload-zone" for="arquivo_extrato">
                <input
                    type="file"
                    id="arquivo_extrato"
                    name="arquivo_extrato"
                    accept=".ofx,.qfx,application/x-ofx"
                    required
                    hidden
                >
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
                    <p>OFX é lido como dado estruturado. CSV detecta separador, codificação e colunas. PDF usa pdfplumber e fallback compatível.</p>
                </div>
            </li>
            <li>
                <span>02</span>
                <div>
                    <strong>Validação</strong>
                    <p>Datas, valores, tipo, duplicatas e confiança da leitura são avaliados antes da confirmação.</p>
                </div>
            </li>
            <li>
                <span>03</span>
                <div>
                    <strong>Revisão</strong>
                    <p>Você pode corrigir descrição, data, valor, tipo e categoria antes de salvar.</p>
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
            <strong>Ordem recomendada</strong>
            <p>Prefira OFX. Se o banco não oferecer, use CSV. PDF deve ser a alternativa, porque cada instituição monta o documento de um jeito diferente.</p>
        </div>
        <div class="import-note">
            <strong>PDF escaneado</strong>
            <p>Se o arquivo for apenas imagem, a 14.4 identifica essa situação e não tenta inventar lançamentos. OCR será uma camada opcional posterior.</p>
        </div>
    </aside>
</div>

<script>
const conta = document.getElementById('conta_id');

const grupo = document.getElementById('grupo-nova-conta');

const nome = document.getElementById('nome_nova_conta');

const arquivo = document.getElementById('arquivo_extrato');

const fileText = document.getElementById('uploadFile');

const bancoPdf = document.getElementById('bancoPdf');

const help = document.getElementById('formatHelp');


function syncConta() {

    const nova = conta.value === 'nova';

    grupo.hidden = !nova;

    nome.required = nova;

}


function syncFormato() {

    const formato = document.querySelector('input[name="formato"]:checked').value;

    const pdf = formato === 'pdf';

    bancoPdf.hidden = !pdf;


    if (formato === 'pdf') {

        arquivo.accept = '.pdf,application/pdf';

        help.textContent = 'PDF com texto selecionável · máximo 8 MB.';

    }
 else if (formato === 'csv') {

        arquivo.accept = '.csv,text/csv';

        help.textContent = 'CSV · máximo 2 MB.';

    }
 else {

        arquivo.accept = '.ofx,.qfx,application/x-ofx';

        help.textContent = 'OFX/QFX · máximo 4 MB.';

    }


    arquivo.value = '';

    fileText.textContent = 'Nenhum arquivo selecionado';

}


conta.addEventListener('change', syncConta);

document.querySelectorAll('input[name="formato"]').forEach((input) => {
    input.addEventListener('change', syncFormato);
});

arquivo.addEventListener('change', () => {
    fileText.textContent = arquivo.files[0]?.name || 'Nenhum arquivo selecionado';
});


syncConta();

syncFormato();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
