<?php
/**
 * mcp-setup.php — single-screen MCP authorization window.
 *
 * Flow:
 *   - Local MCP server triggers `mcp_login` → user is sent here with `session`
 *     and `callback` URL params.
 *   - If the admin already has a CMS session, we deliver the existing JWT to
 *     the loopback callback immediately and show "done".
 *   - Otherwise we render an inline login form. A successful submit creates
 *     the CMS session AND delivers the freshly-issued JWT in the same request.
 *   - In every error path the page stays on the same URL so the user can retry
 *     without breaking the session/callback contract.
 */

define('DIR', __DIR__);

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error_log');

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

/**
 * POST credentials to the framework's login endpoint. Returns the populated
 * admin record on success (same shape the CMS would store in $_SESSION) or
 * null on failure. The framework enforces rate limits and bcrypt verification
 * internally.
 */
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

/**
 * POST the JWT envelope to the local MCP server's loopback callback.
 * Returns [ok=>bool, msg=>string].
 */
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
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 460px; margin: 4rem auto; padding: 0 1.5rem; }
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

// --- request handling -------------------------------------------------------

$session  = $_REQUEST['session']  ?? '';
$callback = $_REQUEST['callback'] ?? '';

if (!preg_match('/^[a-f0-9]{16,64}$/', (string) $session)) {
    mcp_render('Falta sesión MCP', '<div class="alert">Esta página solo se abre desde el comando <code>mcp_login</code> del MCP local.</div>');
    exit;
}
if (!mcp_is_safe_callback($callback)) {
    mcp_render('Callback inválido', '<div class="alert">El parámetro <code>callback</code> debe apuntar a un host loopback. Volvé a invocar <code>mcp_login</code>.</div>');
    exit;
}

$admin = $_SESSION['admin'] ?? null;
$loginError = null;

// Login submit — only when no session yet.
if (!is_object($admin) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = trim((string) ($_POST['email_admin'] ?? ''));
    $password = (string) ($_POST['password_admin'] ?? '');
    if ($email === '' || $password === '') {
        $loginError = 'Email y password son requeridos.';
    } else {
        $logged = mcp_perform_login($email, $password);
        if ($logged) {
            $_SESSION['admin'] = $logged;
            $admin = $logged;
        } else {
            $loginError = 'Email o password incorrectos.';
        }
    }
}

// Already authenticated (either from a previous CMS session or from the
// inline login above) → deliver the JWT immediately and close the flow.
if (is_object($admin) && !empty($admin->token_admin)) {
    $delivery = mcp_deliver_jwt($admin, $session, $callback);
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

// No session → render inline login form.
$action = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES);

ob_start();
?>
<p class="muted">Iniciá sesión en el CMS para que el MCP local pueda usar tu JWT.</p>

<?php if ($loginError): ?>
<div class="alert"><?= htmlspecialchars($loginError, ENT_QUOTES) ?></div>
<?php endif ?>

<form method="post" action="<?= $action ?>" autocomplete="on">
    <label for="email_admin">Email</label>
    <input id="email_admin" type="email" name="email_admin" required autofocus autocomplete="username">

    <label for="password_admin">Password</label>
    <input id="password_admin" type="password" name="password_admin" required autocomplete="current-password">

    <button type="submit">Iniciar sesión y autorizar MCP</button>
    <p class="muted">Al iniciar sesión, el JWT vigente se entrega al servidor MCP local automáticamente. No queda guardado en este servidor.</p>
</form>
<?php
mcp_render('Iniciar sesión', (string) ob_get_clean());
