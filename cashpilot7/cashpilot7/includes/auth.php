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

/** Retorna o token CSRF da sessão, criando-o quando necessário. */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/** Retorna o campo oculto que deve estar em todos os formulários POST. */
function csrfCampo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . limpar(csrfToken()) . '">';
}

/** Interrompe solicitações POST sem um token válido da sessão atual. */
function validarCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Solicitação inválida. Atualize a página e tente novamente.');
    }
}
