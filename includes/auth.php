<?php
/**
 * CashPilot - Controle de sessão, autenticação e proteções HTTP.
 */

function cashpilotEhProducao(): bool
{
    return strtolower((string) getenv('CASHPILOT_ENV')) === 'production';
}

function cashpilotEhHttps(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }

    return (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/**
 * Remove o cookie da sessão usando a assinatura moderna do PHP 7.3+.
 * Centralizar isso evita misturar a assinatura antiga do setcookie()
 * com o array de opções.
 */
function cashpilotRemoverCookieSessao(): void
{
    if (!ini_get('session.use_cookies')) {
        return;
    }

    $parametros = session_get_cookie_params();

    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $parametros['path'] ?: '/',
        'domain' => $parametros['domain'] ?? '',
        'secure' => (bool) ($parametros['secure'] ?? false),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

if (cashpilotEhProducao()) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
}

if (PHP_SAPI !== 'cli') {
    // Reforça a sessão sem impedir o funcionamento local via HTTP/XAMPP.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => cashpilotEhHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    // Cabeçalhos compatíveis com a interface atual. CSP fica para produção,
    // pois o projeto ainda possui scripts inline legítimos.
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Cross-Origin-Opener-Policy: same-origin-allow-popups');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; script-src 'self' 'unsafe-inline' https://accounts.google.com https://accounts.gstatic.com https://cdnjs.cloudflare.com https://www.youtube.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' data: blob: https://img.youtube.com https://*.googleusercontent.com https://accounts.gstatic.com; font-src 'self' data: https://cdnjs.cloudflare.com; connect-src 'self' https://api.groq.com https://api.brevo.com https://oauth2.googleapis.com https://accounts.google.com; frame-src https://accounts.google.com https://www.youtube.com https://youtube.com; media-src 'self';");


    if (cashpilotEhHttps()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Expira sessões abandonadas após 4 horas de inatividade.
    $agora = time();
    $ultimaAtividade = (int) ($_SESSION['_cp_ultima_atividade'] ?? 0);
    if ($ultimaAtividade > 0 && ($agora - $ultimaAtividade) > 14400) {
        $_SESSION = [];
        cashpilotRemoverCookieSessao();
        session_destroy();
        session_start();
    }
    $_SESSION['_cp_ultima_atividade'] = $agora;
    if (!empty($_SESSION['usuario_id'])) {
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
    }

}

/** Garante que o usuário está logado. */
function exigirLogin(): void
{
    if (!empty($_SESSION['usuario_id'])) {
        return;
    }

    $possuiCookieSessao = isset($_COOKIE[session_name()]);
    $destino = '../pages/login.php';
    if ($possuiCookieSessao) {
        $destino .= '?sessao=expirada';
    }

    header('Location: ' . $destino);
    exit;
}

/** Evita que usuário autenticado volte ao login/cadastro. */
function redirecionarSeLogado(): void
{
    if (!empty($_SESSION['usuario_id'])) {
        header('Location: ../pages/dashboard.php');
        exit;
    }
}

/** Restringe uma rota ao método POST. */
function exigirPost(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Método não permitido.');
    }
}

function usuarioLogadoId(): ?int
{
    return isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;
}

function usuarioLogadoNome(): string
{
    return (string) ($_SESSION['usuario_nome'] ?? '');
}

function usuarioLogadoTipo(): string
{
    return (string) ($_SESSION['usuario_tipo'] ?? 'pessoa_fisica');
}

function usuarioLogadoUsername(): string
{
    return (string) ($_SESSION['usuario_username'] ?? '');
}

/** Escapa texto para saída HTML. */
function limpar($valor): string
{
    return htmlspecialchars(trim((string) ($valor ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

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

    return (string) $_SESSION['csrf_token'];
}

function renovarCsrf(): void
{
    unset($_SESSION['csrf_token']);
    csrfToken();
}

/** Campo oculto para formulários POST. */
function csrfCampo(): string
{
    return '<input type="hidden" name="csrf_token" value="' . limpar(csrfToken()) . '">';
}

/** Interrompe solicitações POST sem token CSRF válido. */
function validarCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Solicitação inválida. Atualize a página e tente novamente.');
    }
}

/** Valida categoria e conta pertencentes ao usuário. */
function validarVinculos(PDO $pdo, int $usuarioId, ?int $categoriaId, ?int $contaId, string $tipo): bool
{
    if ($categoriaId !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM categorias WHERE id=:id AND usuario_id=:uid AND tipo=:tipo');
        $stmt->execute(['id' => $categoriaId, 'uid' => $usuarioId, 'tipo' => $tipo]);
        if (!$stmt->fetchColumn()) {
            return false;
        }
    }

    if ($contaId !== null) {
        $stmt = $pdo->prepare('SELECT 1 FROM contas WHERE id=:id AND usuario_id=:uid');
        $stmt->execute(['id' => $contaId, 'uid' => $usuarioId]);
        if (!$stmt->fetchColumn()) {
            return false;
        }
    }

    return true;
}

/** Identificador pseudonimizado usado somente para limitar força bruta. */
function cashpilotHashTentativa(string $valor): string
{
    $chave = getenv('CASHPILOT_SECURITY_KEY');
    if (!is_string($chave) || strlen($chave) < 16) {
        $chave = 'cashpilot-local-rate-limit-v149';
    }

    return hash_hmac('sha256', mb_strtolower(trim($valor)), $chave);
}

function cashpilotIpCliente(): string
{
    // REMOTE_ADDR é preferido; cabeçalhos encaminhados só devem ser confiados
    // quando o servidor/proxy for configurado explicitamente para isso.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'local');
}

/** Retorna segundos restantes do bloqueio de login (0 = liberado). */
function cashpilotLoginBloqueado(PDO $pdo, string $identificador): int
{
    try {
        $stmt = $pdo->prepare(
            'SELECT bloqueado_ate FROM tentativas_login
             WHERE identificador_hash=:identificador AND ip_hash=:ip LIMIT 1'
        );
        $stmt->execute([
            'identificador' => cashpilotHashTentativa($identificador),
            'ip' => cashpilotHashTentativa(cashpilotIpCliente()),
        ]);
        $bloqueadoAte = $stmt->fetchColumn();
        if (!$bloqueadoAte) {
            return 0;
        }

        return max(0, strtotime((string) $bloqueadoAte) - time());
    } catch (Throwable $e) {
        // Mantém instalações antigas utilizáveis até a migration 013 ser aplicada.
        error_log('CashPilot/RateLimit: ' . $e->getMessage());
        return 0;
    }
}

/** Registra falha: 5 tentativas em 15 min geram bloqueio de 15 min. */
function cashpilotRegistrarFalhaLogin(PDO $pdo, string $identificador): void
{
    try {
        $idHash = cashpilotHashTentativa($identificador);
        $ipHash = cashpilotHashTentativa(cashpilotIpCliente());

        $stmt = $pdo->prepare(
            'SELECT id,tentativas,primeira_tentativa FROM tentativas_login
             WHERE identificador_hash=:identificador AND ip_hash=:ip LIMIT 1'
        );
        $stmt->execute(['identificador' => $idHash, 'ip' => $ipHash]);
        $registro = $stmt->fetch(PDO::FETCH_ASSOC);

        $agora = new DateTimeImmutable();
        if (!$registro || strtotime((string) $registro['primeira_tentativa']) < time() - 900) {
            $sql = 'INSERT INTO tentativas_login (identificador_hash,ip_hash,tentativas,primeira_tentativa,ultima_tentativa,bloqueado_ate)
                    VALUES (:identificador,:ip,1,NOW(),NOW(),NULL)
                    ON DUPLICATE KEY UPDATE tentativas=1,primeira_tentativa=NOW(),ultima_tentativa=NOW(),bloqueado_ate=NULL';
            $pdo->prepare($sql)->execute(['identificador' => $idHash, 'ip' => $ipHash]);
            return;
        }

        $tentativas = (int) $registro['tentativas'] + 1;
        $bloqueadoAte = $tentativas >= 5 ? $agora->modify('+15 minutes')->format('Y-m-d H:i:s') : null;
        $pdo->prepare(
            'UPDATE tentativas_login SET tentativas=:tentativas,ultima_tentativa=NOW(),bloqueado_ate=:bloqueado
             WHERE id=:id'
        )->execute(['tentativas' => $tentativas, 'bloqueado' => $bloqueadoAte, 'id' => (int) $registro['id']]);
    } catch (Throwable $e) {
        error_log('CashPilot/RateLimit: ' . $e->getMessage());
    }
}

function cashpilotLimparFalhasLogin(PDO $pdo, string $identificador): void
{
    try {
        $pdo->prepare(
            'DELETE FROM tentativas_login WHERE identificador_hash=:identificador AND ip_hash=:ip'
        )->execute([
            'identificador' => cashpilotHashTentativa($identificador),
            'ip' => cashpilotHashTentativa(cashpilotIpCliente()),
        ]);
    } catch (Throwable $e) {
        error_log('CashPilot/RateLimit: ' . $e->getMessage());
    }
}


/** Confirma que uma categoria pertence ao usuário autenticado. */
function cashpilotCategoriaPertenceAoUsuario(PDO $pdo, int $usuarioId, ?int $categoriaId, ?string $tipo = null): bool
{
    if ($categoriaId === null || $categoriaId <= 0) {
        return true;
    }

    $sql = 'SELECT 1 FROM categorias WHERE id = :id AND usuario_id = :uid';
    $parametros = ['id' => $categoriaId, 'uid' => $usuarioId];

    if ($tipo !== null) {
        $sql .= ' AND tipo = :tipo';
        $parametros['tipo'] = $tipo;
    }

    $stmt = $pdo->prepare($sql . ' LIMIT 1');
    $stmt->execute($parametros);

    return (bool) $stmt->fetchColumn();
}

/** Confirma que uma conta pertence ao usuário autenticado. */
function cashpilotContaPertenceAoUsuario(PDO $pdo, int $usuarioId, ?int $contaId): bool
{
    if ($contaId === null || $contaId <= 0) {
        return true;
    }

    $stmt = $pdo->prepare('SELECT 1 FROM contas WHERE id = :id AND usuario_id = :uid LIMIT 1');
    $stmt->execute(['id' => $contaId, 'uid' => $usuarioId]);

    return (bool) $stmt->fetchColumn();
}

function mesAbreviadoPt(string $data): string
{
    $meses = [1=>'JAN',2=>'FEV',3=>'MAR',4=>'ABR',5=>'MAI',6=>'JUN',7=>'JUL',8=>'AGO',9=>'SET',10=>'OUT',11=>'NOV',12=>'DEZ'];
    return $meses[(int) date('n', strtotime($data))] ?? '';
}

function mesAnoPt(string $data): string
{
    $meses = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
    $ts = strtotime($data);
    return ($meses[(int) date('n', $ts)] ?? '') . ' de ' . date('Y', $ts);
}
