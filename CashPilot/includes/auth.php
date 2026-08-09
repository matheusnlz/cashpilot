<?php
/**
 * CashPilot - Controle de sessão e autenticação
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Garante que o usuário está logado. Caso não esteja, redireciona ao login.
 */
function exigirLogin(): void
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: ../pages/login.php');
        exit;
    }
}

/**
 * Se já estiver logado, evita que volte para login/cadastro.
 */
function redirecionarSeLogado(): void
{
    if (!empty($_SESSION['usuario_id'])) {
        header('Location: ../pages/dashboard.php');
        exit;
    }
}

function usuarioLogadoId(): ?int
{
    return $_SESSION['usuario_id'] ?? null;
}

function usuarioLogadoNome(): string
{
    return $_SESSION['usuario_nome'] ?? '';
}

function usuarioLogadoTipo(): string
{
    return $_SESSION['usuario_tipo'] ?? 'pessoa_fisica';
}

/**
 * Sanitiza uma string de entrada.
 */
function limpar(string $valor): string
{
    return htmlspecialchars(trim($valor), ENT_QUOTES, 'UTF-8');
}

/**
 * Formata valor em Real brasileiro.
 */
function formatarMoeda(float $valor): string
{
    return 'R$ ' . number_format($valor, 2, ',', '.');
}
