<?php
$paginaAtual = basename($_SERVER['PHP_SELF']);
$itensMenu = [
    'dashboard.php'   => ['label' => 'Dashboard',   'icone' => 'grid'],
    'contas.php'      => ['label' => 'Contas',      'icone' => 'wallet'],
    'receitas.php'    => ['label' => 'Receitas',    'icone' => 'arrow-up'],
    'despesas.php'    => ['label' => 'Despesas',    'icone' => 'arrow-down'],
    'importar.php'    => ['label' => 'Importar extrato', 'icone' => 'upload'],
    'importacoes.php' => ['label' => 'Importações', 'icone' => 'history'],
    'categorias.php'  => ['label' => 'Categorias',  'icone' => 'tag'],
    'metas.php'       => ['label' => 'Metas',       'icone' => 'target'],
    'relatorios.php'  => ['label' => 'Relatórios',  'icone' => 'bar-chart'],
];
if (usuarioLogadoTipo() === 'mei') {
    $itensMenu = array_slice($itensMenu, 0, 3, true) + ['mei.php' => ['label' => 'Área MEI', 'icone' => 'briefcase']] + array_slice($itensMenu, 3, null, true);
}
// Marca "Importações" como ativo também na tela de revisão
if ($paginaAtual === 'importar_revisao.php') {
    $paginaAtual = 'importar.php';
}
?>
<div class="layout">
    <aside class="sidebar">
        <div class="sidebar-topo">
            <span class="logo">Cash<strong>Pilot</strong></span>
        </div>
        <nav class="sidebar-menu">
            <?php foreach ($itensMenu as $arquivo => $item): ?>
                <a href="<?= $arquivo ?>" class="menu-item <?= $paginaAtual === $arquivo ? 'ativo' : '' ?>">
                    <span class="menu-icone icone-<?= $item['icone'] ?>"></span>
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-rodape">
            <a href="perfil.php" class="menu-item <?= $paginaAtual === 'perfil.php' ? 'ativo' : '' ?>">
                <span class="menu-icone icone-user"></span>
                <span><?= limpar(usuarioLogadoNome()) ?></span>
            </a>
            <a href="../actions/logout.php" class="menu-item sair">
                <span class="menu-icone icone-logout"></span>
                <span>Sair</span>
            </a>
        </div>
    </aside>
    <main class="conteudo">
