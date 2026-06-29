<?php

/**
 * RBAC Manager Controller
 * Manages roles, permissions, and admin-role assignments
 */

require_once __DIR__ . '/../../../cms/controllers/install.controller.php';

class RBACManagerController {

    private $link;

    public function __construct() {
        $this->link = InstallController::connect();
        $this->ensureTableExists();
    }

    // =========================================================
    // Static permission check — called from template.php
    // =========================================================

    /**
     * Check if the current session admin can access a page.
     * Only applies when the admin has id_role_admin set.
     * Returns null when RBAC is not applicable (fallback to legacy logic).
     *
     * @param  string $pageUrl
     * @return bool|null  true/false if RBAC applies, null if not applicable
     */
    public static function canAccessPage($pageUrl) {
        if (!isset($_SESSION['admin']) || !is_object($_SESSION['admin'])) {
            return null;
        }

        $admin = $_SESSION['admin'];

        // Superadmin and admin bypass all checks
        if (in_array($admin->rol_admin ?? '', ['superadmin', 'admin'])) {
            return null; // Let legacy logic handle it
        }

        // Only apply RBAC when the admin has a role assigned
        if (empty($admin->id_role_admin)) {
            return null; // Let legacy logic handle it
        }

        $permissions = self::getPermissionsFromSession();
        if ($permissions === null) {
            // Load permissions into session cache
            $permissions = self::loadPermissionsIntoSession((int)$admin->id_role_admin);
        }

        if ($permissions === null) {
            return false;
        }

        return isset($permissions[$pageUrl]) && !empty($permissions[$pageUrl]['read']);
    }

    /**
     * Check if the current session admin can perform an action on a page.
     * Actions: read, create, update, delete
     *
     * @param  string $action   read|create|update|delete
     * @param  string $pageUrl
     * @return bool
     */
    public static function can($action, $pageUrl) {
        if (!isset($_SESSION['admin']) || !is_object($_SESSION['admin'])) {
            return false;
        }

        $admin = $_SESSION['admin'];

        // Superadmin and admin can do everything
        if (in_array($admin->rol_admin ?? '', ['superadmin', 'admin'])) {
            return true;
        }

        if (empty($admin->id_role_admin)) {
            // Legacy editor: only check read (page-level)
            if ($action === 'read') {
                $perms = $_SESSION['admin']->permissions_admin ?? '';
                $decoded = json_decode(urldecode($perms), true);
                return isset($decoded[$pageUrl]) && $decoded[$pageUrl] === 'on';
            }
            return true; // Legacy editors had full CRUD on accessible pages
        }

        $permissions = self::getPermissionsFromSession();
        if ($permissions === null) {
            $permissions = self::loadPermissionsIntoSession((int)$admin->id_role_admin);
        }

        if ($permissions === null) {
            return false;
        }

        return isset($permissions[$pageUrl][$action]) && !empty($permissions[$pageUrl][$action]);
    }

    // =========================================================
    // Internal helpers
    // =========================================================

    private static function getPermissionsFromSession() {
        return $_SESSION['_rbac_permissions'] ?? null;
    }

    private static function loadPermissionsIntoSession($roleId) {
        try {
            require_once __DIR__ . '/../../../api/models/connection.php';
            $link = Connection::connect();

            $stmt = $link->prepare("SELECT permissions_role FROM roles WHERE id_role = :id LIMIT 1");
            $stmt->execute([':id' => $roleId]);
            $row = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$row || empty($row->permissions_role)) {
                $_SESSION['_rbac_permissions'] = [];
                return [];
            }

            $permissions = json_decode($row->permissions_role, true) ?? [];
            $_SESSION['_rbac_permissions'] = $permissions;
            return $permissions;
        } catch (Exception $e) {
            error_log("RBACManager: Failed to load permissions - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Invalidate the RBAC permissions cache in the current session.
     * Call this after updating a role's permissions or changing an admin's role.
     */
    public static function clearSessionCache() {
        unset($_SESSION['_rbac_permissions']);
    }

    // =========================================================
    // Role CRUD
    // =========================================================

    public function getRoles() {
        try {
            $stmt = $this->link->query(
                "SELECT id_role, name_role, description_role, date_created_role FROM roles ORDER BY name_role"
            );
            return ['success' => true, 'roles' => $stmt->fetchAll(PDO::FETCH_OBJ)];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getRole($roleId) {
        try {
            $stmt = $this->link->prepare("SELECT * FROM roles WHERE id_role = :id LIMIT 1");
            $stmt->execute([':id' => (int)$roleId]);
            $role = $stmt->fetch(PDO::FETCH_OBJ);

            if (!$role) {
                return ['success' => false, 'error' => 'Role not found'];
            }

            $role->permissions = $role->permissions_role
                ? (json_decode($role->permissions_role, true) ?? [])
                : [];

            return ['success' => true, 'role' => $role];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function saveRole($data) {
        try {
            $name        = trim($data['name_role'] ?? '');
            $description = trim($data['description_role'] ?? '');
            $permissions = $data['permissions_role'] ?? '{}';
            $roleId      = isset($data['id_role']) ? (int)$data['id_role'] : 0;

            if (empty($name)) {
                return ['success' => false, 'error' => 'Role name is required'];
            }

            // Validate JSON
            $decoded = json_decode($permissions, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'error' => 'Invalid permissions format'];
            }

            if ($roleId > 0) {
                // Update
                $stmt = $this->link->prepare(
                    "UPDATE roles SET
                        name_role        = :name,
                        description_role = :description,
                        permissions_role = :permissions
                    WHERE id_role = :id"
                );
                $stmt->execute([
                    ':name'        => $name,
                    ':description' => $description,
                    ':permissions' => json_encode($decoded),
                    ':id'          => $roleId,
                ]);
            } else {
                // Insert
                $stmt = $this->link->prepare(
                    "INSERT INTO roles (name_role, description_role, permissions_role, date_created_role)
                     VALUES (:name, :description, :permissions, :date)"
                );
                $stmt->execute([
                    ':name'        => $name,
                    ':description' => $description,
                    ':permissions' => json_encode($decoded),
                    ':date'        => date('Y-m-d'),
                ]);
                $roleId = (int)$this->link->lastInsertId();
            }

            // Invalidate session cache for all users with this role
            // (Next request will reload permissions)
            return ['success' => true, 'id_role' => $roleId];
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                return ['success' => false, 'error' => 'A role with that name already exists'];
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function deleteRole($roleId) {
        try {
            $roleId = (int)$roleId;

            // Check if role is assigned to any admin
            $stmt = $this->link->prepare(
                "SELECT COUNT(*) FROM admins WHERE id_role_admin = :id"
            );
            $stmt->execute([':id' => $roleId]);
            $count = (int)$stmt->fetchColumn();

            if ($count > 0) {
                return [
                    'success' => false,
                    'error'   => "Cannot delete: {$count} admin(s) are using this role. Reassign them first.",
                ];
            }

            $stmt = $this->link->prepare("DELETE FROM roles WHERE id_role = :id");
            $stmt->execute([':id' => $roleId]);

            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================
    // Pages (for permission matrix)
    // =========================================================

    public function getPages() {
        try {
            // Pages reserved for superadmin/admin only are not assignable to roles
            // (e.g. the visual page builder "web-pages").
            $stmt = $this->link->query(
                "SELECT id_page, title_page, url_page, icon_page, type_page
                 FROM pages
                 WHERE type_page IN ('modules', 'custom')
                   AND url_page NOT IN ('web-pages')
                 ORDER BY order_page ASC"
            );
            return ['success' => true, 'pages' => $stmt->fetchAll(PDO::FETCH_OBJ)];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================
    // Admin-role assignments
    // =========================================================

    public function getAdmins() {
        try {
            $stmt = $this->link->query(
                "SELECT a.id_admin, a.email_admin, a.rol_admin, a.status_admin,
                        a.id_role_admin, r.name_role
                 FROM admins a
                 LEFT JOIN roles r ON r.id_role = a.id_role_admin
                 ORDER BY a.email_admin"
            );
            return ['success' => true, 'admins' => $stmt->fetchAll(PDO::FETCH_OBJ)];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function assignRole($adminId, $roleId) {
        try {
            $adminId = (int)$adminId;
            $roleId  = $roleId !== '' && $roleId !== null ? (int)$roleId : null;

            if ($roleId !== null) {
                // Verify role exists
                $stmt = $this->link->prepare("SELECT id_role FROM roles WHERE id_role = :id LIMIT 1");
                $stmt->execute([':id' => $roleId]);
                if (!$stmt->fetch()) {
                    return ['success' => false, 'error' => 'Role not found'];
                }
            }

            $stmt = $this->link->prepare(
                "UPDATE admins SET id_role_admin = :role_id WHERE id_admin = :admin_id"
            );
            $stmt->execute([
                ':role_id'  => $roleId,
                ':admin_id' => $adminId,
            ]);

            // Clear RBAC session cache if the updated admin is currently logged in
            if (
                isset($_SESSION['admin']->id_admin) &&
                (int)$_SESSION['admin']->id_admin === $adminId
            ) {
                self::clearSessionCache();
                $_SESSION['admin']->id_role_admin = $roleId;
            }

            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================
    // Auto-setup: ensure table exists
    // =========================================================

    private function ensureTableExists() {
        try {
            $stmt = $this->link->query("SHOW TABLES LIKE 'roles'");
            if ($stmt->rowCount() === 0) {
                $this->link->exec("
                    CREATE TABLE `roles` (
                        `id_role`           INT          NOT NULL AUTO_INCREMENT,
                        `name_role`         VARCHAR(100) NOT NULL,
                        `description_role`  VARCHAR(255) NULL DEFAULT NULL,
                        `permissions_role`  TEXT         NULL DEFAULT NULL,
                        `date_created_role` DATE         NULL DEFAULT NULL,
                        PRIMARY KEY (`id_role`),
                        UNIQUE KEY `unique_name_role` (`name_role`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
            }

            // Ensure id_role_admin column exists on admins
            $cols = $this->link->query("SHOW COLUMNS FROM admins LIKE 'id_role_admin'")->rowCount();
            if ($cols === 0) {
                $this->link->exec(
                    "ALTER TABLE admins ADD COLUMN `id_role_admin` INT NULL DEFAULT NULL"
                );
            }
        } catch (PDOException $e) {
            error_log("RBACManager: Error ensuring tables exist - " . $e->getMessage());
        }
    }
}
