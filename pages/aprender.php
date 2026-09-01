<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../database/conexao.php';
require_once __DIR__ . '/../includes/aprender_helper.php';
require_once __DIR__ . '/../includes/personalizacao.php';

exigirLogin();

$tituloPagina = 'Aprender';
$pdo = conectar();
$usuarioId = (int) usuarioLogadoId();
$perfil = usuarioLogadoTipo() === 'mei'
    ? 'mei'
    : 'pessoa_fisica';

$videos = cpVideosAprender(
    $pdo,
    $perfil
);

$trilhas = cpTrilhasAprender(
    $pdo,
    $perfil,
    $usuarioId
);

$admin = cpUsuarioAdmin(
    $pdo,
    $usuarioId
);

$categorias = [];

foreach ($videos as $video) {
    $categorias[$video['categoria']] = true;
}

$categorias = array_keys($categorias);

$continuar = null;

foreach ($videos as $video) {
    if (
        (float) $video['percentual'] > 0 &&
        empty($video['concluido'])
    ) {
        $continuar = $video;
        break;
    }
}

$recomendados = [];
$perfilNegocioAprender = null;

if ($perfil === 'mei') {
    $perfilNegocioAprender = cpPerfilEmpreendedor(
        $pdo,
        $usuarioId
    );

    foreach ($videos as $video) {
        $pontos = cpVideoCombinaPerfil(
            $video,
            $perfilNegocioAprender
        );

        if ($pontos > 0) {
            $video['_pontos_recomendacao'] = $pontos;
            $recomendados[] = $video;
        }
    }

    usort(
        $recomendados,
        fn (array $a, array $b): int =>
            $b['_pontos_recomendacao'] <=>
            $a['_pontos_recomendacao']
    );

    $recomendados = array_slice(
        $recomendados,
        0,
        4
    );
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-head">
    <div>
        <span class="eyebrow">
            EDUCAÇÃO NO SEU RITMO
        </span>

        <h1>Aprender</h1>

        <p>
            <?= $perfil === 'mei'
                ? 'Conteúdos práticos para entender melhor gestão, margem, caixa e operação.'
                : 'Conteúdos práticos para entender orçamento, crédito, metas e decisões financeiras.' ?>
        </p>
    </div>

    <?php if ($admin): ?>
        <a
            class="btn btn-secundario"
            href="aprender_gerenciar.php"
        >
            Gerenciar conteúdo
        </a>
    <?php endif; ?>
</div>

<?php if ($continuar): ?>
    <section class="learning-feature cartao">
        <div class="learning-feature-thumb">
            <img
                src="https://img.youtube.com/vi/<?= limpar($continuar['youtube_video_id']) ?>/hqdefault.jpg"
                alt=""
            >
        </div>

        <div>
            <span class="eyebrow">
                CONTINUE DE ONDE PAROU
            </span>

            <h2>
                <?= limpar($continuar['titulo']) ?>
            </h2>

            <p>
                <?= limpar($continuar['descricao'] ?? '') ?>
            </p>

            <div class="learning-progress">
                <span
                    style="width: <?= min(100, (float) $continuar['percentual']) ?>%;"
                ></span>
            </div>

            <small>
                <?= number_format(
                    (float) $continuar['percentual'],
                    0
                ) ?>
                % concluído
            </small>

            <br>

            <a
                class="btn btn-primario"
                href="aula.php?id=<?= (int) $continuar['id'] ?>"
            >
                Continuar aula
            </a>
        </div>
    </section>
<?php endif; ?>

<?php if ($perfil === 'mei' && $recomendados): ?>
    <section class="section-block">
        <div class="section-title">
            <div>
                <span class="eyebrow">
                    RECOMENDADO PARA SEU NEGÓCIO
                </span>

                <h2>
                    Conteúdos mais próximos do seu momento
                </h2>

                <?php if ($perfilNegocioAprender): ?>
                    <p class="secao-ajuda">
                        Considerando
                        <?= limpar($perfilNegocioAprender['nicho'] ?? 'seu segmento') ?>
                        e a prioridade
                        <?= limpar(
                            $perfilNegocioAprender['objetivo_principal']
                            ?? 'atual do negócio'
                        ) ?>.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="learning-grid">
            <?php foreach ($recomendados as $video): ?>
                <article class="learning-card learning-card-recommended">
                    <a
                        href="aula.php?id=<?= (int) $video['id'] ?>"
                        class="learning-thumb"
                    >
                        <img
                            loading="lazy"
                            src="https://img.youtube.com/vi/<?= limpar($video['youtube_video_id']) ?>/hqdefault.jpg"
                            alt=""
                        >

                        <span class="play-circle">
                            ▶
                        </span>
                    </a>

                    <div class="learning-body">
                        <small>
                            <?= limpar($video['categoria']) ?>
                        </small>

                        <h3>
                            <?= limpar($video['titulo']) ?>
                        </h3>

                        <p>
                            <?= limpar($video['descricao'] ?? '') ?>
                        </p>

                        <div class="learning-meta">
                            <span>
                                Relacionado ao seu perfil
                            </span>

                            <a href="aula.php?id=<?= (int) $video['id'] ?>">
                                Assistir →
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="section-block">
    <div class="section-title">
        <div>
            <span class="eyebrow">
                TRILHAS
            </span>

            <h2>
                Aprenda por objetivo
            </h2>
        </div>
    </div>

    <?php if (!$trilhas): ?>
        <div class="cartao estado-vazio">
            <span>▶</span>
            <h3>Nenhuma trilha cadastrada</h3>
            <p>
                Os conteúdos adicionados pelo administrador
                aparecerão aqui.
            </p>
        </div>
    <?php else: ?>
        <div class="learning-tracks">
            <?php foreach ($trilhas as $trilha): ?>
                <article class="cartao learning-track">
                    <div>
                        <small>
                            <?= limpar(
                                $trilha['perfil'] === 'mei'
                                    ? 'EMPREENDEDOR'
                                    : (
                                        $trilha['perfil'] === 'pessoa_fisica'
                                            ? 'PESSOA FÍSICA'
                                            : 'GERAL'
                                    )
                            ) ?>
                        </small>

                        <h3>
                            <?= limpar($trilha['titulo']) ?>
                        </h3>

                        <p>
                            <?= limpar($trilha['descricao'] ?? '') ?>
                        </p>
                    </div>

                    <div class="learning-track-progress">
                        <div class="learning-progress">
                            <span
                                style="width: <?= (float) $trilha['percentual'] ?>%;"
                            ></span>
                        </div>

                        <span>
                            <?= (int) $trilha['concluidos'] ?>
                            de
                            <?= (int) $trilha['total'] ?>
                            aulas
                        </span>
                    </div>

                    <?php if ($trilha['videos']): ?>
                        <a
                            href="aula.php?id=<?= (int) $trilha['videos'][0]['id'] ?>"
                            class="link-limpar"
                        >
                            Abrir trilha →
                        </a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section-block">
    <div class="section-title">
        <div>
            <span class="eyebrow">
                BIBLIOTECA
            </span>

            <h2>
                Aulas disponíveis
            </h2>
        </div>

        <div class="learning-filters">
            <button
                class="periodo ativo"
                data-cat="todos"
            >
                Todos
            </button>

            <?php foreach ($categorias as $categoria): ?>
                <button
                    class="periodo"
                    data-cat="<?= limpar(mb_strtolower($categoria)) ?>"
                >
                    <?= limpar($categoria) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!$videos): ?>
        <div class="cartao estado-vazio">
            <span>▶</span>
            <h3>Ainda não há vídeos</h3>
            <p>
                O CashPilot só exibirá conteúdos adicionados por vocês.
            </p>
        </div>
    <?php else: ?>
        <div class="learning-grid" id="learningGrid">
            <?php foreach ($videos as $video): ?>
                <article
                    class="learning-card"
                    data-cat="<?= limpar(mb_strtolower($video['categoria'])) ?>"
                >
                    <a
                        href="aula.php?id=<?= (int) $video['id'] ?>"
                        class="learning-thumb"
                    >
                        <img
                            loading="lazy"
                            src="https://img.youtube.com/vi/<?= limpar($video['youtube_video_id']) ?>/hqdefault.jpg"
                            alt=""
                        >

                        <span class="play-circle">
                            ▶
                        </span>
                    </a>

                    <div class="learning-body">
                        <small>
                            <?= limpar($video['categoria']) ?>
                        </small>

                        <h3>
                            <?= limpar($video['titulo']) ?>
                        </h3>

                        <p>
                            <?= limpar($video['descricao'] ?? '') ?>
                        </p>

                        <div class="learning-progress">
                            <span
                                style="width: <?= min(100, (float) $video['percentual']) ?>%;"
                            ></span>
                        </div>

                        <div class="learning-meta">
                            <span>
                                <?= !empty($video['concluido'])
                                    ? 'Concluída'
                                    : number_format(
                                        (float) $video['percentual'],
                                        0
                                    ) . '% assistido' ?>
                            </span>

                            <a href="aula.php?id=<?= (int) $video['id'] ?>">
                                Assistir →
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
document.querySelectorAll(
    '.learning-filters [data-cat]'
).forEach((botao) => {
    botao.addEventListener('click', () => {
        document.querySelectorAll(
            '.learning-filters [data-cat]'
        ).forEach((item) => {
            item.classList.remove('ativo');
        });

        botao.classList.add('ativo');

        const categoria = botao.dataset.cat;

        document.querySelectorAll(
            '.learning-card'
        ).forEach((card) => {
            card.hidden =
                categoria !== 'todos' &&
                card.dataset.cat !== categoria;
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
