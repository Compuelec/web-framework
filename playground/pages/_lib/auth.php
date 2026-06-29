<?php
/**
 * Auth library — minimal session-based login for the contabilidad
 * playground pages.
 *
 * Sharing the auth across pages instead of duplicating the page-builder's
 * inline login (~80 lines per file) — pages just call:
 *
 *     require_once __DIR__ . '/_lib/auth.php';
 *     wpb_require_role(['contador', 'lectura', 'superadmin', 'admin']);
 *
 * Validates against the framework's `admins` table (same email/password the
 * CMS uses) so we don't maintain a parallel user list. Sessions are scoped
 * to the playground via a shared session key.
 *
 * Public API:
 *   wpb_current_user(): ?array  → ['id', 'role', 'email'] or null if not logged in
 *   wpb_require_role(array $roles): void  → renders the login form and exit()s if missing
 *   wpb_render_user_bar(): string  → small HTML strip for the page header
 *   wpb_handle_logout(): void  → called automatically; redirects ?wpb_logout=1
 *   wpb_csrf_field(): string  → renders <input type="hidden" name="_csrf_token" …>
 *   wpb_csrf_check(): void  → validates POST/PUT/DELETE; HTTP 403 + exit on failure
 */

if (defined('WPB_AUTH_LIB_LOADED')) { return; }
define('WPB_AUTH_LIB_LOADED', true);

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Shared session key — all playground pages see the same login state.
// `wpb_auth_*` was the page-scoped prefix the legacy page-builder used;
// we deliberately pick a single key for the whole playground so a contador
// logs in once and navigates freely between Dashboard, Libros, etc.
const WPB_AUTH_SESSION_KEY = 'wpb_contabilidad_user';

// Handle logout BEFORE anything else (independent of role guards).
if (isset($_GET['wpb_logout'])) {
    unset($_SESSION[WPB_AUTH_SESSION_KEY]);
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

function wpb_current_user(): ?array {
    $u = $_SESSION[WPB_AUTH_SESSION_KEY] ?? null;
    return (is_array($u) && !empty($u['id'])) ? $u : null;
}

/**
 * Validates email + password against admins. Returns the user array on
 * success, null otherwise. Uses ApiController::getByFilter so the regular
 * web/api auth path is the only DB code path here too.
 */
function wpb_login(string $email, string $password): ?array {
    require_once __DIR__ . '/../../controllers/api.controller.php';
    try {
        $resp = ApiController::getByFilter('admins', 'email_admin', trim($email));
        if (!isset($resp->status) || $resp->status != 200 || empty($resp->results)) {
            return null;
        }
        $admin = (array) $resp->results[0];
        $hash  = $admin['password_admin'] ?? '';
        if ($hash === '' || !password_verify($password, $hash)) { return null; }
        return [
            'id'    => (string)($admin['id_admin']    ?? ''),
            'role'  => (string)($admin['rol_admin']   ?? ''),
            'email' => (string)($admin['email_admin'] ?? ''),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Renders the login form + exit() — used by wpb_require_role() when the
 * visitor isn't logged in or doesn't have the right role.
 *
 * $message is shown at the top (eg. "Credenciales inválidas" or "Tu rol
 * no tiene acceso").
 */
function wpb_render_login_and_exit(string $message = '', string $messageType = 'warning'): void {
    // Page title falls back to the slug.
    $slug = basename(($_SERVER['SCRIPT_NAME'] ?? 'login'), '.php');
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión — Contabilidad</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<style>
    body { background: #f5f5f5; }
    .login-card { max-width: 380px; margin: 6rem auto; }
    .brand { text-align: center; margin-bottom: 1rem; color: #495057; }
    .hint { color: #6c757d; font-size: .8rem; }
</style>
</head>
<body>
<div class="container">
    <div class="login-card">
        <div class="brand">
            <h2 class="mb-1">Contabilidad</h2>
            <p class="small text-muted mb-0">Iniciá sesión para continuar</p>
        </div>
        <?php if ($message !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?>"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="post" class="card card-body shadow-sm">
            <input type="hidden" name="wpb_login_action" value="1">
            <div class="mb-3">
                <label class="form-label" for="wpb-email">Email</label>
                <input type="email" class="form-control" id="wpb-email" name="wpb_email" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label" for="wpb-password">Contraseña</label>
                <input type="password" class="form-control" id="wpb-password" name="wpb_password" required>
            </div>
            <button type="submit" class="btn btn-primary">Entrar</button>
            <p class="hint mt-3 mb-0">
                Usa el mismo email y contraseña que usas en el CMS administrativo.
            </p>
        </form>
    </div>
</div>
</body>
</html><?php
    exit;
}

/**
 * Guard that enforces "user must be logged in AND have one of these roles".
 * Empty $allowedRoles means "any authenticated user". Roles `superadmin`
 * and `admin` are always implicitly allowed (they bypass role gating).
 */
function wpb_require_role(array $allowedRoles): void {
    // Handle POST login submissions — must happen before the user check
    // so logging in returns the user IMMEDIATELY on the same request.
    if (($_POST['wpb_login_action'] ?? '') === '1') {
        $u = wpb_login($_POST['wpb_email'] ?? '', $_POST['wpb_password'] ?? '');
        if ($u !== null) {
            session_regenerate_id(true); // prevent session fixation
            $_SESSION[WPB_AUTH_SESSION_KEY] = $u;
            // Re-load this page without the POST so a refresh doesn't re-login.
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        wpb_render_login_and_exit('Credenciales inválidas.', 'danger');
    }

    $user = wpb_current_user();
    if ($user === null) {
        wpb_render_login_and_exit();
    }

    // Empty list = any logged-in user. Otherwise role match (admins bypass).
    $role = $user['role'] ?? '';
    $alwaysAllowed = ['superadmin', 'admin'];
    if (!empty($allowedRoles)
        && !in_array($role, $alwaysAllowed, true)
        && !in_array($role, $allowedRoles, true)) {
        wpb_render_login_and_exit(
            'Tu rol "' . htmlspecialchars($role) . '" no tiene acceso a esta página. ' .
            'Pedí a un administrador que te asigne un rol con permisos.',
            'danger'
        );
    }
}

/**
 * Renders a small bar with the current user's email + logout link. Pages
 * call this near their header. Returns the HTML so the caller decides
 * where to place it.
 */
function wpb_render_user_bar(): string {
    $u = wpb_current_user();
    if ($u === null) { return ''; }
    $email = htmlspecialchars($u['email'] ?? '');
    $role  = htmlspecialchars($u['role']  ?? '');
    $url   = strtok($_SERVER['REQUEST_URI'], '?') . '?wpb_logout=1';
    return '<div class="d-flex justify-content-end align-items-center small text-muted py-2 px-3" '
         . 'style="background:#f1f3f5;border-bottom:1px solid #dee2e6">'
         . '<span><i class="bi bi-person-circle"></i> ' . $email
         . ' <span class="badge bg-secondary ms-1">' . $role . '</span></span>'
         . '<a href="' . htmlspecialchars($url) . '" class="ms-3">Cerrar sesión</a>'
         . '</div>';
}

/* =========================================================================
   CSRF — reuse the framework's SessionController so the token is the SAME
   one the CMS already manages. That means a contador logged into the CMS
   in another tab shares the token, and any future page (form or AJAX)
   that needs it can call SessionController::getCsrfToken() / validateCsrfRequest()
   without us re-implementing anything.
   ========================================================================= */

if (!class_exists('SessionController', false)) {
    require_once __DIR__ . '/../../../cms/controllers/session.controller.php';
}

/**
 * Returns the hidden input for embedding in a <form>. Always include this
 * inside POST forms that mutate state.
 */
function wpb_csrf_field(): string {
    $token = SessionController::getCsrfToken();
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
}

/**
 * Validates the current request's CSRF token. Safe (GET/HEAD/OPTIONS) requests
 * are exempt. On failure, emits 403 + a friendly HTML page and exit()s — we
 * don't want to let a CSRF'd POST silently produce a comprobante.
 *
 * Call this at the top of any page that handles a state-changing POST,
 * AFTER wpb_require_role() so the user context is set.
 */
function wpb_csrf_check(): void {
    if (SessionController::validateCsrfRequest()) { return; }

    http_response_code(403);
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Token inválido — Contabilidad</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
<div class="container py-5">
    <div class="alert alert-danger">
        <h4 class="alert-heading">Token de seguridad inválido o expirado</h4>
        <p>
            La solicitud no pudo verificarse. Esto suele pasar si la página
            estuvo abierta mucho tiempo o si el envío vino desde otro sitio.
        </p>
        <p class="mb-0">
            <a href="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?'), ENT_QUOTES) ?>" class="btn btn-primary">Volver e intentar de nuevo</a>
        </p>
    </div>
</div>
</body>
</html><?php
    exit;
}
