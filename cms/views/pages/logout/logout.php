<?php 

// Log logout activity before destroying session
if (isset($_SESSION['admin']) && function_exists('logActivity')) {
	$adminId = $_SESSION['admin']->id_admin ?? null;
	if ($adminId) {
		logActivity('logout', 'admin', $adminId, 'User logout');
	}
}

session_destroy();

echo '<script>
window.location = "/";
</script>';

