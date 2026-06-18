<?php
/**
 * System Health — AJAX endpoint.
 *
 * Diagnoses (and best-effort repairs) the directories the application needs to
 * write to, so a non-technical user can fix most permission issues from the
 * browser. See core/permissions.php for the constraints (PHP cannot chown).
 *
 * Actions (POST):
 *   check   diagnose without changing anything
 *   fix     attempt a best-effort repair, then diagnose
 */

define('SESSION_INIT_INCLUDED', true);
require_once __DIR__ . '/session-init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!SessionController::validateCsrfRequest()) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Repairing/inspecting server directories is an admin-level action.
$role = $_SESSION['admin']->rol_admin ?? '';
if (!in_array($role, ['superadmin', 'admin'], true)) {
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../core/permissions.php';

$action = $_POST['action'] ?? 'check';
$fix    = ($action === 'fix');

$results = Permissions::diagnose($fix);

$allOk = true;
foreach ($results as $r) {
    if (empty($r['writable'])) {
        $allOk = false;
        break;
    }
}

echo json_encode([
    'success' => true,
    'allOk'   => $allOk,
    'webUser' => Permissions::webUser(),
    'results' => $results,
]);
