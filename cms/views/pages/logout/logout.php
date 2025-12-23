<?php 

// Log logout activity before destroying session
if (isset($_SESSION['admin']) && function_exists('logActivity')) {
	$adminId = $_SESSION['admin']->id_admin ?? null;
	if ($adminId) {
		logActivity('logout', 'admin', $adminId, 'User logout');
	}
}

session_destroy();

// Calculate project base path (remove /cms from CMS base path)
require_once __DIR__ . '/../../../controllers/template.controller.php';
$cmsBasePath = TemplateController::cmsBasePath();
$projectBasePath = str_replace('/cms', '', $cmsBasePath);

// If base path is empty, use root
if (empty($projectBasePath)) {
	$projectBasePath = '/';
} else {
	// Ensure it starts with /
	if (substr($projectBasePath, 0, 1) !== '/') {
		$projectBasePath = '/' . $projectBasePath;
	}
	// Ensure it doesn't end with /
	$projectBasePath = rtrim($projectBasePath, '/');
}

echo '<script>
window.location = "' . htmlspecialchars($projectBasePath, ENT_QUOTES, 'UTF-8') . '";
</script>';

