<?php
/**
 * mcp-setup.php — MCP authorization window with explicit user consent.
 *
 * Security model:
 *   - Every JWT delivery is gated behind a POST + CSRF token. A logged-in
 *     admin who merely *loads* this URL (e.g. via a crafted link) does NOT
 *     exfiltrate their JWT — they have to read the callback and click
 *     "Autorizar MCP".
 *   - Inline login form (when no session) also carries a CSRF token so a
 *     third-party site cannot auto-submit a phishing form against this page.
 *   - All input parameters (`session`, `callback`) are format-validated;
 *     `callback` must be loopback or Docker-Desktop loopback-equivalent.
 *   - The CSRF token is single-use: it rotates after a successful delivery
 *     and after a successful inline login, so the same URL can't be replayed.
 *
 * Flow:
 *   - GET (no session)      → render login form
 *   - POST email/password   → authenticate + render the confirm form (one extra click)
 *   - GET (with session)    → render confirm form
 *   - POST confirm_auth=1   → deliver the JWT to the callback, show "done"
 */

define('DIR', __DIR__);

ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Never write the log under the web root: the CMS .htaccess does not deny
// arbitrary file names and the framework's router serves existing files.
// Use /tmp so a leaked file path can't be served over HTTP.
ini_set('error_log', sys_get_temp_dir() . '/web-framework-mcp-setup.log');

require_once __DIR__ . '/controllers/session.controller.php';

SessionController::startUniqueSession();

// --- helpers ----------------------------------------------------------------

function mcp_is_safe_callback(?string $url): bool {
    if (!$url) return false;
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return false;
    if ($parts['scheme'] !== 'http' && $parts['scheme'] !== 'https') return false;
    return in_array($parts['host'], [
        '127.0.0.1', 'localhost', '::1',
        'host.docker.internal', 'gateway.docker.internal',
    ], true);
}

function mcp_load_config(): array {
    $path = __DIR__ . '/config.php';
    if (!file_exists($path)) return [];
    $cfg = require $path;
    return is_array($cfg) ? $cfg : [];
}

function mcp_csrf_token(): string {
    if (empty($_SESSION['mcp_csrf'])) {
        $_SESSION['mcp_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['mcp_csrf'];
}

function mcp_check_csrf(): bool {
    $token = (string) ($_POST['_csrf'] ?? '');
    return $token !== ''
        && !empty($_SESSION['mcp_csrf'])
        && hash_equals($_SESSION['mcp_csrf'], $token);
}

function mcp_rotate_csrf(): void {
    unset($_SESSION['mcp_csrf']);
}

function mcp_perform_login(string $email, string $password): ?object {
    $cfg = mcp_load_config();
    $apiBase = rtrim((string) ($cfg['api']['base_url'] ?? ''), '/');
    $apiKey  = (string) ($cfg['api']['key'] ?? '');
    if ($apiBase === '' || $apiKey === '') return null;

    $ch = curl_init($apiBase . '/admins?login=true&suffix=admin');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'email_admin'    => $email,
            'password_admin' => $password,
        ]),
        CURLOPT_HTTPHEADER     => ['Authorization: ' . $apiKey],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$raw) return null;
    $body = json_decode((string) $raw);
    if (!is_object($body) || empty($body->results) || !is_array($body->results)) return null;
    $admin = $body->results[0] ?? null;
    if (!is_object($admin) || empty($admin->id_admin) || empty($admin->token_admin)) return null;
    return $admin;
}

function mcp_deliver_jwt(object $admin, string $session, string $callback): array {
    $payload = json_encode([
        'session'    => $session,
        'jwt'        => $admin->token_admin ?? '',
        'expires_at' => (int) ($admin->token_exp_admin ?? 0),
        'email'      => $admin->email_admin ?? '',
        'table'      => 'admins',
        'suffix'     => 'admin',
    ], JSON_UNESCAPED_SLASHES);

    $ch = curl_init($callback);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($code >= 200 && $code < 300) return ['ok' => true, 'msg' => ''];
    return ['ok' => false, 'msg' => 'HTTP ' . (int) $code . ($err ? " — $err" : '')];
}

function mcp_render(string $title, string $bodyHtml): void {
    ?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>MCP setup · <?= htmlspecialchars($title, ENT_QUOTES) ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        :root { color-scheme: light dark; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 480px; margin: 4rem auto; padding: 0 1.5rem; }
        h1 { font-size: 1.4rem; margin-bottom: 0.25rem; }
        p.subtitle { color: #666; margin-top: 0; }
        .card { border: 1px solid rgba(127,127,127,.25); border-radius: 12px; padding: 1.25rem 1.5rem; margin-top: 1rem; }
        .row { display: flex; justify-content: space-between; gap: 1rem; margin: 0.5rem 0; }
        .row span:first-child { color: #777; }
        .row span:last-child { font-family: ui-monospace, SFMono-Regular, monospace; font-size: 0.92rem; word-break: break-all; text-align: right; }
        label { display: block; margin-top: 0.85rem; font-size: 0.9rem; color: #555; }
        input[type="email"], input[type="password"] { width: 100%; padding: 0.6rem 0.7rem; border: 1px solid rgba(127,127,127,.35); border-radius: 8px; font-size: 1rem; box-sizing: border-box; margin-top: 0.25rem; }
        button { background: #0d6efd; color: #fff; border: 0; padding: 0.7rem 1.2rem; border-radius: 8px; font-size: 1rem; cursor: pointer; margin-top: 1rem; width: 100%; }
        button:hover { background: #0b5ed7; }
        .alert { background: rgba(220, 53, 69, .1); border: 1px solid rgba(220, 53, 69, .35); padding: 0.9rem 1.15rem; border-radius: 8px; color: #b02a37; margin-top: 1rem; }
        .alert.success { background: rgba(25, 135, 84, .1); border-color: rgba(25, 135, 84, .35); color: #146c43; }
        .muted { color: #777; font-size: 0.85rem; }
        form { margin-top: 1rem; }
    </style>
</head>
<body>
    <h1>MCP setup</h1>
    <p class="subtitle">Autorizar al servidor MCP local a usar tu sesión del CMS.</p>
    <?= $bodyHtml ?>
</body>
</html><?php
}

function mcp_render_confirm(object $admin, string $session, string $callback): void {
    $action = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES);
    $csrf   = mcp_csrf_token();
    $exp    = (int) ($admin->token_exp_admin ?? 0);
    $expIso = $exp ? gmdate('Y-m-d\TH:i:s\Z', $exp) : '(sin token)';

    ob_start();
    ?>
    <div class="card">
        <div class="row"><span>Admin</span><span><?= htmlspecialchars($admin->email_admin ?? '', ENT_QUOTES) ?></span></div>
        <div class="row"><span>JWT vence</span><span><?= htmlspecialchars($expIso, ENT_QUOTES) ?> UTC</span></div>
        <div class="row"><span>Sesión MCP</span><span><?= htmlspecialchars(substr($session, 0, 8), ENT_QUOTES) ?>…</span></div>
        <div class="row"><span>Callback</span><span><?= htmlspecialchars($callback, ENT_QUOTES) ?></span></div>
    </div>
    <form method="post" action="<?= $action ?>">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <input type="hidden" name="confirm_auth" value="1">
        <input type="hidden" name="session" value="<?= htmlspecialchars($session, ENT_QUOTES) ?>">
        <input type="hidden" name="callback" value="<?= htmlspecialchars($callback, ENT_QUOTES) ?>">
        <button type="submit">Autorizar MCP</button>
        <p class="muted">El callback debe apuntar a tu MCP local (verificá la URL arriba). El JWT se entrega solo si lo confirmás explícitamente.</p>
    </form>
    <?php
    mcp_render('Confirmar autorización', (string) ob_get_clean());
}

function mcp_render_login_form(string $session, string $callback, ?string $loginError): void {
    $action = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES);
    $csrf   = mcp_csrf_token();
    ob_start();
    ?>
    <p class="muted">Iniciá sesión en el CMS para autorizar el MCP local.</p>
    <?php if ($loginError): ?>
    <div class="alert"><?= htmlspecialchars($loginError, ENT_QUOTES) ?></div>
    <?php endif ?>
    <form method="post" action="<?= $action ?>" autocomplete="on">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
        <input type="hidden" name="session" value="<?= htmlspecialchars($session, ENT_QUOTES) ?>">
        <input type="hidden" name="callback" value="<?= htmlspecialchars($callback, ENT_QUOTES) ?>">

        <label for="email_admin">Email</label>
        <input id="email_admin" type="email" name="email_admin" required autofocus autocomplete="username">

        <label for="password_admin">Password</label>
        <input id="password_admin" type="password" name="password_admin" required autocomplete="current-password">

        <button type="submit">Iniciar sesión</button>
        <p class="muted">Después del login vas a confirmar la entrega del JWT en un paso extra (sin password).</p>
    </form>
    <?php
    mcp_render('Iniciar sesión', (string) ob_get_clean());
}

// --- request handling -------------------------------------------------------

$session  = (string) ($_REQUEST['session']  ?? '');
$callback = (string) ($_REQUEST['callback'] ?? '');

if (!preg_match('/^[a-f0-9]{16,64}$/', $session)) {
    mcp_render('Falta sesión MCP', '<div class="alert">Esta página solo se abre desde <code>mcp_login</code> del MCP local.</div>');
    exit;
}
if (!mcp_is_safe_callback($callback)) {
    mcp_render('Callback inválido', '<div class="alert">El parámetro <code>callback</code> debe apuntar a un host loopback. Volvé a invocar <code>mcp_login</code>.</div>');
    exit;
}

$admin = $_SESSION['admin'] ?? null;
$loginError = null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Branch A — POST with confirm_auth=1: deliver the JWT (CSRF gated).
if ($method === 'POST' && (($_POST['confirm_auth'] ?? '') === '1')) {
    if (!is_object($admin) || empty($admin->token_admin)) {
        mcp_render('Sin sesión', '<div class="alert">No hay sesión activa. Iniciá sesión y reintentá.</div>');
        exit;
    }
    if (!mcp_check_csrf()) {
        http_response_code(403);
        mcp_render('CSRF', '<div class="alert">Token CSRF inválido. Volvé a abrir <code>mcp_login</code>.</div>');
        exit;
    }
    $delivery = mcp_deliver_jwt($admin, $session, $callback);
    mcp_rotate_csrf();
    if ($delivery['ok']) {
        mcp_render('Listo',
            '<div class="alert success"><strong>JWT entregado al MCP.</strong><br>'
            . 'Sesión activa como <code>' . htmlspecialchars($admin->email_admin ?? '', ENT_QUOTES) . '</code>. '
            . 'Volvé al cliente MCP y cerrá esta pestaña.</div>');
    } else {
        mcp_render('Error al entregar el JWT',
            '<div class="alert">No se pudo entregar el JWT al servidor MCP: '
            . htmlspecialchars($delivery['msg'], ENT_QUOTES) . '<br><br>'
            . 'Reintentá <code>mcp_login</code> desde el cliente.</div>');
    }
    exit;
}

// Branch B — POST with email/password: inline login (CSRF gated).
if ($method === 'POST' && !is_object($admin) && isset($_POST['email_admin'], $_POST['password_admin'])) {
    if (!mcp_check_csrf()) {
        http_response_code(403);
        mcp_render('CSRF', '<div class="alert">Token CSRF inválido. Recargá esta página desde el cliente MCP.</div>');
        exit;
    }
    $email = trim((string) $_POST['email_admin']);
    $password = (string) $_POST['password_admin'];
    if ($email === '' || $password === '') {
        $loginError = 'Email y password son requeridos.';
    } else {
        $logged = mcp_perform_login($email, $password);
        if ($logged) {
            $_SESSION['admin'] = $logged;
            $admin = $logged;
            mcp_rotate_csrf(); // fresh token for the confirm form
        } else {
            $loginError = 'Email o password incorrectos.';
        }
    }
}

// Render: if we have a session, show the confirm form; otherwise the login form.
if (is_object($admin) && !empty($admin->token_admin)) {
    mcp_render_confirm($admin, $session, $callback);
} else {
    mcp_render_login_form($session, $callback, $loginError);
}
