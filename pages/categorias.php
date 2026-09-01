<?php
require_once __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../database/conexao.php';

exigirLogin();


$tituloPagina = 'Categorias';

$pdo = conectar();

$usuarioId = usuarioLogadoId();


$stmt = $pdo->prepare('SELECT * FROM categorias WHERE usuario_id = :uid ORDER BY tipo, nome');

$stmt->execute(['uid' => $usuarioId]);

$categorias = $stmt->fetchAll();

$receitasCat = array_filter($categorias, fn($c) => $c['tipo'] === 'receita');

$despesasCat = array_filter($categorias, fn($c) => $c['tipo'] === 'despesa');


require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/navbar.php';?>
<a href="perfil.php" class="voltar-perfil">← Voltar para Meu Perfil</a>
<div class="topo-pagina">
<div>
<h1>Categorias</h1>
<p><?= usuarioLogadoTipo()==='mei' ? 'Categorias voltadas à operação do negócio.' : 'Categorias pessoais para organizar melhor seu dinheiro.'?></p>
</div>
</div>
<div class="grade-dupla">
<div>
<div class="cartao" style="margin-bottom:20px;">
<div class="radar-titulo">
<div>
<h3>Categorias de receita</h3>
<p class="secao-ajuda">As categorias padrão seguem seu tipo de conta; as criadas por você podem ser removidas.</p>
</div>
</div>
            <?php foreach ($receitasCat as $c):?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--cor-borda);">
<span>
<span class="badge" style="background:<?= $c['cor']?>22; color:<?= $c['cor']?>;"><?= limpar($c['nome'])?></span>
<small class="categoria-origem"><?= $c['padrao'] ? 'Padrão do perfil' : 'Personalizada'?></small>
</span>
                    <?php if (!$c['padrao']):?>
                        <form autocomplete="off" action="../actions/categorias.php" method="POST" data-confirm="Excluir categoria?">
                            <?= csrfCampo()?>
                            <input type="hidden" name="acao" value="excluir">
<input type="hidden" name="id" value="<?= $c['id']?>">
<button type="submit" class="excluir" style="border:none;background:none;font-size:12px;">Excluir</button>
</form>
                    <?php endif;?>
                </div>
            <?php endforeach;?>
        </div>
<div class="cartao">
<div class="radar-titulo">
<div>
<h3>Categorias de despesa</h3>
<p class="secao-ajuda">Use “Outros” apenas quando não houver uma classificação adequada.</p>
</div>
</div>
            <?php foreach ($despesasCat as $c):?>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid var(--cor-borda);">
<span>
<span class="badge" style="background:<?= $c['cor']?>22; color:<?= $c['cor']?>;"><?= limpar($c['nome'])?></span>
<small class="categoria-origem"><?= $c['padrao'] ? 'Padrão do perfil' : 'Personalizada'?></small>
</span>
                    <?php if (usuarioLogadoTipo()!=='mei'):?><form action="../actions/categorias.php" method="POST" class="categoria-limite-form" autocomplete="off"><?=csrfCampo()?><input type="hidden" name="acao" value="limite">
<input type="hidden" name="id" value="<?= $c['id']?>">
<input type="number" step="0.01" min="0" name="limite_mensal" value="<?= $c['limite_mensal'] ?? ''?>" placeholder="Orçamento mensal">
<button class="link-botao">Salvar</button>
</form><?php endif;?>
                    <?php if (!$c['padrao']):?>
                        <form autocomplete="off" action="../actions/categorias.php" method="POST" data-confirm="Excluir categoria?">
                            <?= csrfCampo()?>
                            <input type="hidden" name="acao" value="excluir">
<input type="hidden" name="id" value="<?= $c['id']?>">
<button type="submit" class="excluir" style="border:none;background:none;font-size:12px;">Excluir</button>
</form>
                    <?php endif;?>
                </div>
            <?php endforeach;?>
        </div>
</div>
<div class="cartao">
<h3 style="margin-bottom:16px;">Nova categoria</h3>
<form autocomplete="off" action="../actions/categorias.php" method="POST">
            <?= csrfCampo()?>
            <input type="hidden" name="acao" value="criar">
<div class="form-grupo">
<label for="nome">Nome</label>
<input autocomplete="off" type="text" id="nome" name="nome" required>
</div>
<div class="form-grupo">
<label for="tipo">Tipo</label>
<select id="tipo" name="tipo">
<option value="receita">Receita</option>
<option value="despesa">Despesa</option>
</select>
</div>
<div class="form-grupo">
<label for="cor">Cor</label>
<input type="color" id="cor" name="cor" value="#2F5D62">
</div>
<button type="submit" class="btn btn-primario btn-bloco">Adicionar categoria</button>
</form>
</div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php';?>
