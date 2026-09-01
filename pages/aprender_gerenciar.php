<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/aprender_helper.php';

exigirLogin();

$tituloPagina = 'Gerenciar Aprender';
$pdo = conectar();
$usuarioId = (int) usuarioLogadoId();

if (!cpUsuarioAdmin($pdo, $usuarioId)) {
    http_response_code(403);
    exit('Acesso restrito ao administrador.');
}

$trilhas = $pdo
    ->query(
        'SELECT *
         FROM aprender_trilhas
         WHERE ativo = 1
         ORDER BY ordem, id'
    )
    ->fetchAll(PDO::FETCH_ASSOC);

$videos = $pdo
    ->query(
        'SELECT *
         FROM aprender_videos
         WHERE ativo = 1
         ORDER BY ordem, id DESC'
    )
    ->fetchAll(PDO::FETCH_ASSOC);

$mensagem = $_SESSION['mensagem_aprender'] ?? null;
unset($_SESSION['mensagem_aprender']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-head">
    <div>
        <a href="aprender.php" class="back-link">
            ← Voltar
        </a>

        <span class="eyebrow">
            ADMINISTRAÇÃO
        </span>

        <h1>
            Conteúdo do Aprender
        </h1>

        <p>
            Somente vídeos cadastrados aqui aparecem para os usuários.
            Nicho e objetivo podem ser usados para personalizar recomendações.
        </p>
    </div>
</div>

<?php if ($mensagem): ?>
    <div class="alerta-mensagem sucesso">
        <?= limpar($mensagem) ?>
    </div>
<?php endif; ?>

<div class="admin-learning-grid">
    <section class="cartao">
        <h3>
            Nova trilha
        </h3>

        <form
            action="../actions/aprender.php"
            method="POST"
            autocomplete="off"
        >
            <?= csrfCampo() ?>

            <input
                type="hidden"
                name="acao"
                value="trilha_criar"
            >

            <div class="form-grupo">
                <label>Título</label>
                <input name="titulo" required>
            </div>

            <div class="form-grupo">
                <label>Descrição</label>
                <textarea name="descricao"></textarea>
            </div>

            <div class="form-linha">
                <div class="form-grupo">
                    <label>Público</label>

                    <select name="perfil">
                        <option value="pessoa_fisica">
                            Pessoa física
                        </option>
                        <option value="mei">
                            Empreendedor
                        </option>
                        <option value="ambos">
                            Ambos
                        </option>
                    </select>
                </div>

                <div class="form-grupo">
                    <label>Ordem</label>
                    <input
                        type="number"
                        name="ordem"
                        value="0"
                    >
                </div>
            </div>

            <button class="btn btn-primario">
                Criar trilha
            </button>
        </form>
    </section>

    <section class="cartao">
        <h3>
            Novo vídeo do YouTube
        </h3>

        <form
            action="../actions/aprender.php"
            method="POST"
            autocomplete="off"
        >
            <?= csrfCampo() ?>

            <input
                type="hidden"
                name="acao"
                value="video_criar"
            >

            <div class="form-grupo">
                <label>Título</label>
                <input name="titulo" required>
            </div>

            <div class="form-grupo">
                <label>URL ou ID do YouTube</label>
                <input
                    name="youtube_video_id"
                    required
                    placeholder="https://youtube.com/watch?v=..."
                >
            </div>

            <div class="form-grupo">
                <label>Descrição</label>
                <textarea name="descricao"></textarea>
            </div>

            <div class="form-linha">
                <div class="form-grupo">
                    <label>Categoria</label>
                    <input
                        name="categoria"
                        value="Geral"
                    >
                </div>

                <div class="form-grupo">
                    <label>Público</label>

                    <select name="perfil">
                        <option value="pessoa_fisica">
                            Pessoa física
                        </option>
                        <option value="mei">
                            Empreendedor
                        </option>
                        <option value="ambos">
                            Ambos
                        </option>
                    </select>
                </div>
            </div>

            <div class="form-grupo">
                <label>
                    Tags para RadarPilot/Copiloto
                </label>

                <input
                    name="tags"
                    placeholder="ex.: financiamento juros crédito cartão"
                >
            </div>

            <div class="form-linha">
                <div class="form-grupo">
                    <label>
                        Nichos relacionados
                    </label>

                    <input
                        name="nichos"
                        placeholder="ex.: barbearia beleza estética"
                    >

                    <small class="secao-ajuda">
                        Opcional. Use palavras simples separadas por espaço.
                    </small>
                </div>

                <div class="form-grupo">
                    <label>
                        Objetivos relacionados
                    </label>

                    <input
                        name="objetivos"
                        placeholder="ex.: margem lucro vendas estoque"
                    >

                    <small class="secao-ajuda">
                        Opcional. Ajuda a seção “Recomendado para seu negócio”.
                    </small>
                </div>
            </div>

            <div class="form-linha">
                <div class="form-grupo">
                    <label>
                        Trilha
                    </label>

                    <select name="trilha_id">
                        <option value="0">
                            Nenhuma
                        </option>

                        <?php foreach ($trilhas as $trilha): ?>
                            <option value="<?= (int) $trilha['id'] ?>">
                                <?= limpar($trilha['titulo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-grupo">
                    <label>
                        Ordem
                    </label>

                    <input
                        type="number"
                        name="ordem"
                        value="0"
                    >
                </div>
            </div>

            <button class="btn btn-primario">
                Adicionar vídeo
            </button>
        </form>
    </section>
</div>

<section class="cartao section-block">
    <h3>
        Organizar vídeo em trilha
    </h3>

    <p class="secao-ajuda">
        Vincule um vídeo já existente a uma trilha.
    </p>

    <form
        action="../actions/aprender.php"
        method="POST"
    >
        <?= csrfCampo() ?>

        <input
            type="hidden"
            name="acao"
            value="trilha_vincular"
        >

        <div class="form-linha">
            <div class="form-grupo">
                <label>Vídeo</label>

                <select name="video_id" required>
                    <?php foreach ($videos as $video): ?>
                        <option value="<?= (int) $video['id'] ?>">
                            <?= limpar($video['titulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grupo">
                <label>Trilha</label>

                <select name="trilha_id" required>
                    <?php foreach ($trilhas as $trilha): ?>
                        <option value="<?= (int) $trilha['id'] ?>">
                            <?= limpar($trilha['titulo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grupo">
                <label>Ordem</label>
                <input
                    type="number"
                    name="ordem"
                    value="0"
                >
            </div>
        </div>

        <button class="btn btn-secundario">
            Vincular
        </button>
    </form>
</section>

<section class="cartao section-block">
    <h3>
        Conteúdo ativo
    </h3>

    <?php if (!$videos): ?>
        <p class="texto-vazio">
            Nenhum vídeo cadastrado.
        </p>
    <?php else: ?>
        <div class="admin-video-list">
            <?php foreach ($videos as $video): ?>
                <div>
                    <div>
                        <strong>
                            <?= limpar($video['titulo']) ?>
                        </strong>

                        <small>
                            <?= limpar($video['categoria']) ?>
                            ·
                            <?= limpar($video['perfil']) ?>
                            ·
                            <?= limpar($video['youtube_video_id']) ?>

                            <?php if (!empty($video['nichos'])): ?>
                                · nichos:
                                <?= limpar($video['nichos']) ?>
                            <?php endif; ?>
                        </small>
                    </div>

                    <form
                        action="../actions/aprender.php"
                        method="POST"
                        data-confirm="Desativar este vídeo?"
                    >
                        <?= csrfCampo() ?>

                        <input
                            type="hidden"
                            name="acao"
                            value="video_desativar"
                        >

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $video['id'] ?>"
                        >

                        <button class="excluir">
                            Desativar
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
