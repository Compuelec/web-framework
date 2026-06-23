<?php

/**
 * Data Protection — PUBLIC endpoint (no admin session).
 *
 * Used by the public site: the cookie banner fetches its settings and records the
 * visitor's cookie choice; public web forms can record consent here too. Server
 * captures IP/user-agent. Writes are limited to consent logging and guarded by a
 * same-origin check.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/controllers/data-protection.controller.php';
$controller = new DataProtectionController();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'settings') {
    // Public, read-only: the banner needs the text/labels/policy link.
    $s = $controller->getSettings();
    echo json_encode([
        'success'  => true,
        'enabled'  => ($s['cookie_enabled'] ?? '1') === '1',
        'text'     => $s['cookie_text'] ?? '',
        'policy'   => $s['cookie_policy_url'] ?? '',
        'accept'   => $s['cookie_accept'] ?? 'Aceptar',
        'reject'   => $s['cookie_reject'] ?? 'Rechazar',
    ]);
    exit;
}

if ($action === 'record') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'method']);
        exit;
    }
    // Same-origin guard: if an Origin is sent, its host must match this host.
    // Strip the port from HTTP_HOST (the Origin header's host never carries one).
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $host   = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== $host) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'origin']);
        exit;
    }
    echo json_encode($controller->recordConsent([
        'subject'    => $_POST['subject'] ?? '',
        'purpose'    => $_POST['purpose'] ?? '',
        'status'     => $_POST['status'] ?? 'granted',
        'channel'    => in_array($_POST['channel'] ?? '', ['cookie_banner', 'web_form'], true) ? $_POST['channel'] : 'web_form',
        'source'     => $_POST['source'] ?? ($_SERVER['HTTP_REFERER'] ?? ''),
        'evidence'   => $_POST['evidence'] ?? '',
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]));
    exit;
}

echo json_encode(['success' => false, 'error' => 'unknown_action']);
