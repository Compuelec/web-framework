<?php

/**
 * Activity Logs Controller
 * 
 * Handles activity logging functionality for tracking user actions
 */

class ActivityLogsController {

	/**
	 * Ensure table exists (auto-create if needed)
	 * 
	 * @return bool True if table exists or was created successfully
	 */
	private static function ensureTableExists() {
		try {
			$config = self::getConfig();
			$dbConfig = $config['database'] ?? [];
			
			// Validate required database configuration
			if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
				error_log("Ensure activity_logs table error: Database configuration is missing");
				return false;
			}
			
			$link = new PDO(
				"mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['name'],
				$dbConfig['user'],
				$dbConfig['pass']
			);
			
			$link->exec("set names " . ($dbConfig['charset'] ?? 'utf8mb4'));
			
			// Check if table exists
			$database = $dbConfig['name'];
			$checkTable = $link->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$database' AND table_name = 'activity_logs'")->fetchColumn();
			
			if ($checkTable == 0) {
				// Table doesn't exist, create it
				$sql = "CREATE TABLE activity_logs ( 
					id_log INT NOT NULL AUTO_INCREMENT,
					action_log TEXT NULL DEFAULT NULL,
					entity_log TEXT NULL DEFAULT NULL,
					entity_id_log INT NULL DEFAULT NULL,
					description_log TEXT NULL DEFAULT NULL,
					admin_id_log INT NULL DEFAULT NULL,
					ip_address_log TEXT NULL DEFAULT NULL,
					user_agent_log TEXT NULL DEFAULT NULL,
					date_created_log DATETIME NULL DEFAULT NULL,
					PRIMARY KEY (id_log),
					INDEX idx_admin_id (admin_id_log),
					INDEX idx_entity (entity_log(50)),
					INDEX idx_action (action_log(50)),
					INDEX idx_date_created (date_created_log)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
				
				$link->exec($sql);
			}
			
			return true;
		} catch (PDOException $e) {
			error_log("Ensure activity_logs table error: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Log an activity
	 * 
	 * @param string $action Action performed (e.g., 'login', 'create', 'update', 'delete')
	 * @param string $entity Entity affected (e.g., 'admin', 'page', 'module')
	 * @param int|null $entityId ID of the affected entity
	 * @param string|null $description Additional description
	 * @param int|null $adminId ID of the admin performing the action (null for system actions)
	 * @return bool True on success, false on failure
	 */
	public static function log($action, $entity, $entityId = null, $description = null, $adminId = null) {
		// Ensure session is started
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
		
		// Ensure table exists before logging
		self::ensureTableExists();
		
		try {
			$config = self::getConfig();
			$dbConfig = $config['database'] ?? [];
			
			// Validate required database configuration
			if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
				error_log("Activity log error: Database configuration is missing");
				return false;
			}
			
			$link = new PDO(
				"mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['name'],
				$dbConfig['user'],
				$dbConfig['pass']
			);
			
			$link->exec("set names " . ($dbConfig['charset'] ?? 'utf8mb4'));
			
			// Get admin ID from session if not provided
			if ($adminId === null && isset($_SESSION['admin'])) {
				$adminId = $_SESSION['admin']->id_admin ?? null;
			}
			
			// Get IP address
			$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
			
			// Get user agent
			$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
			
			$sql = "INSERT INTO activity_logs (
				action_log,
				entity_log,
				entity_id_log,
				description_log,
				admin_id_log,
				ip_address_log,
				user_agent_log,
				date_created_log
			) VALUES (
				:action,
				:entity,
				:entityId,
				:description,
				:adminId,
				:ipAddress,
				:userAgent,
				:dateCreated
			)";
			
			$stmt = $link->prepare($sql);
			
			$stmt->bindValue(':action', $action, PDO::PARAM_STR);
			$stmt->bindValue(':entity', $entity, PDO::PARAM_STR);
			$stmt->bindValue(':entityId', $entityId, PDO::PARAM_INT);
			$stmt->bindValue(':description', $description, PDO::PARAM_STR);
			$stmt->bindValue(':adminId', $adminId, PDO::PARAM_INT);
			$stmt->bindValue(':ipAddress', $ipAddress, PDO::PARAM_STR);
			$stmt->bindValue(':userAgent', $userAgent, PDO::PARAM_STR);
			$stmt->bindValue(':dateCreated', date('Y-m-d H:i:s'), PDO::PARAM_STR);
			
			return $stmt->execute();
			
		} catch (PDOException $e) {
			// Silently fail - don't break the application if logging fails
			error_log("Activity log error: " . $e->getMessage());
			return false;
		}
	}
	
	/**
	 * Get activity logs
	 * 
	 * @param array $filters Optional filters (admin_id, entity, action, date_from, date_to)
	 * @param int $limit Number of records to return
	 * @param int $offset Offset for pagination
	 * @return array Array of log records
	 */
	public static function getLogs($filters = [], $limit = 50, $offset = 0) {
		// Ensure table exists before getting logs
		self::ensureTableExists();
		
		try {
			$config = self::getConfig();
			$dbConfig = $config['database'] ?? [];
			
			// Validate required database configuration
			if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
				error_log("Activity log error: Database configuration is missing");
				return false;
			}
			
			$link = new PDO(
				"mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['name'],
				$dbConfig['user'],
				$dbConfig['pass']
			);
			
			$link->exec("set names " . ($dbConfig['charset'] ?? 'utf8mb4'));
			
			$sql = "SELECT 
				al.*,
				a.email_admin,
				a.title_admin
			FROM activity_logs al
			LEFT JOIN admins a ON al.admin_id_log = a.id_admin
			WHERE 1=1";
			
			$params = [];
			
			if (isset($filters['admin_id'])) {
				$sql .= " AND al.admin_id_log = :admin_id";
				$params[':admin_id'] = $filters['admin_id'];
			}
			
			if (isset($filters['entity'])) {
				$sql .= " AND al.entity_log = :entity";
				$params[':entity'] = $filters['entity'];
			}
			
			if (isset($filters['action'])) {
				$sql .= " AND al.action_log = :action";
				$params[':action'] = $filters['action'];
			}
			
			if (isset($filters['date_from'])) {
				$sql .= " AND al.date_created_log >= :date_from";
				$params[':date_from'] = $filters['date_from'];
			}
			
			if (isset($filters['date_to'])) {
				$sql .= " AND al.date_created_log <= :date_to";
				$params[':date_to'] = $filters['date_to'];
			}
			
			$sql .= " ORDER BY al.date_created_log DESC LIMIT :limit OFFSET :offset";
			
			$stmt = $link->prepare($sql);
			
			foreach ($params as $key => $value) {
				$stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
			
			$stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
			$stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
			
			$stmt->execute();
			
			$results = $stmt->fetchAll(PDO::FETCH_OBJ);
			return is_array($results) ? $results : [];
			
		} catch (PDOException $e) {
			error_log("Get activity logs error: " . $e->getMessage());
			error_log("SQL: " . $sql);
			error_log("Params: " . print_r($params, true));
			return [];
		} catch (Exception $e) {
			error_log("Get activity logs general error: " . $e->getMessage());
			return [];
		}
	}
	
	/**
	 * Get total count of logs with filters
	 * 
	 * @param array $filters Optional filters (admin_id, entity, action, date_from, date_to)
	 * @return int Total count of logs
	 */
	public static function getLogsCount($filters = []) {
		// Ensure table exists before counting
		self::ensureTableExists();
		
		try {
			$config = self::getConfig();
			$dbConfig = $config['database'] ?? [];
			
			// Validate required database configuration
			if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
				error_log("Activity log error: Database configuration is missing");
				return 0;
			}
			
			$link = new PDO(
				"mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['name'],
				$dbConfig['user'],
				$dbConfig['pass']
			);
			
			$link->exec("set names " . ($dbConfig['charset'] ?? 'utf8mb4'));
			
			$sql = "SELECT COUNT(*) as total
			FROM activity_logs al
			WHERE 1=1";
			
			$params = [];
			
			if (isset($filters['admin_id'])) {
				$sql .= " AND al.admin_id_log = :admin_id";
				$params[':admin_id'] = $filters['admin_id'];
			}
			
			if (isset($filters['entity'])) {
				$sql .= " AND al.entity_log = :entity";
				$params[':entity'] = $filters['entity'];
			}
			
			if (isset($filters['action'])) {
				$sql .= " AND al.action_log = :action";
				$params[':action'] = $filters['action'];
			}
			
			if (isset($filters['date_from'])) {
				$sql .= " AND al.date_created_log >= :date_from";
				$params[':date_from'] = $filters['date_from'];
			}
			
			if (isset($filters['date_to'])) {
				$sql .= " AND al.date_created_log <= :date_to";
				$params[':date_to'] = $filters['date_to'];
			}
			
			$stmt = $link->prepare($sql);
			
			foreach ($params as $key => $value) {
				$stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
			}
			
			$stmt->execute();
			$result = $stmt->fetch(PDO::FETCH_OBJ);
			
			if ($result && isset($result->total)) {
				return (int)$result->total;
			}
			
			return 0;
			
		} catch (PDOException $e) {
			error_log("Get activity logs count error: " . $e->getMessage());
			error_log("SQL: " . $sql);
			error_log("Params: " . print_r($params, true));
			return 0;
		} catch (Exception $e) {
			error_log("Get activity logs count general error: " . $e->getMessage());
			return 0;
		}
	}
	
	/**
	 * Clear all activity logs
	 * 
	 * @return bool True on success, false on failure
	 */
	public static function clearLogs() {
		try {
			$config = self::getConfig();
			$dbConfig = $config['database'] ?? [];
			
			// Validate required database configuration
			if (empty($dbConfig['host']) || empty($dbConfig['name']) || !isset($dbConfig['user']) || !isset($dbConfig['pass'])) {
				error_log("Activity log error: Database configuration is missing");
				return false;
			}
			
			$link = new PDO(
				"mysql:host=" . $dbConfig['host'] . ";dbname=" . $dbConfig['name'],
				$dbConfig['user'],
				$dbConfig['pass']
			);
			
			$link->exec("set names " . ($dbConfig['charset'] ?? 'utf8mb4'));
			
			$sql = "TRUNCATE TABLE activity_logs";
			$stmt = $link->prepare($sql);
			
			return $stmt->execute();
			
		} catch (PDOException $e) {
			error_log("Clear activity logs error: " . $e->getMessage());
			return false;
		}
	}
	
	/**
	 * Get configuration
	 * 
	 * @return array Configuration array
	 */
	private static function getConfig() {
		// Try CMS config first
		$configPath = __DIR__ . '/../config.php';
		if (file_exists($configPath)) {
			$config = require $configPath;
			if (is_array($config)) {
				return $config;
			}
		}
		$examplePath = __DIR__ . '/../config.example.php';
		if (file_exists($examplePath)) {
			$config = require $examplePath;
			if (is_array($config)) {
				return $config;
			}
		}
		// Fallback to API config
		$apiConfigPath = __DIR__ . '/../../api/config.php';
		if (file_exists($apiConfigPath)) {
			$config = require $apiConfigPath;
			if (is_array($config)) {
				return $config;
			}
		}
		// No configuration found - return empty array
		return [];
	}
}

