<?php 

/**
 * Session Controller
 * Manages unique sessions per domain and user
 * Prevents conflicts when the same app is open in different domains or with different users
 */

class SessionController {

    /**
     * Initialize unique session based on domain and user
     * 
     * @param string|null $userId User ID (optional, obtained from session if exists)
     * @param string|null $userToken User token (optional, obtained from session if exists)
     * @return string Generated unique session ID
     */
    public static function startUniqueSession($userId = null, $userToken = null) {

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        
        if ($userId === null && isset($_SESSION['admin']) && is_object($_SESSION['admin'])) {
            $userId = $_SESSION['admin']->id_admin ?? null;
        }
        
        if ($userToken === null && isset($_SESSION['admin']) && is_object($_SESSION['admin'])) {
            $userToken = $_SESSION['admin']->token_admin ?? null;
        }
        
        $domain = self::getDomain();
        $sessionId = self::generateSessionId($domain, $userId, $userToken);
        
        $_SESSION['_unique_session_info'] = [
            'domain' => $domain,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'created_at' => time()
        ];
        
        return $sessionId;
    }
    
    private static function getBaseDomain() {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = preg_replace('/:\d+$/', '', $host);
        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host);
        return $host;
    }
    
    private static function getCmsBasePath() {
        return '/';
    }
    
    /**
     * Get current domain (host only, no path)
     * 
     * @return string Normalized domain
     */
    public static function getDomain() {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = preg_replace('/:\d+$/', '', $host);
        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host);
        return $host;
    }
    
    private static function generateSessionId($domain, $userId = null, $userToken = null) {
        if ($userId !== null && $userToken !== null) {
            return hash('sha256', $domain . $userId . $userToken . session_id());
        }
        return bin2hex(random_bytes(16));
    }
    
    /**
     * Validate that current session belongs to correct domain and user
     * 
     * @return bool True if session is valid
     */
    public static function validateSession() {
        if (!isset($_SESSION['_unique_session_info'])) {
            if (isset($_SESSION['admin']) && is_object($_SESSION['admin'])) {
                $userId = $_SESSION['admin']->id_admin ?? null;
                $userToken = $_SESSION['admin']->token_admin ?? null;
                $domain = self::getDomain();
                $sessionId = self::generateSessionId($domain, $userId, $userToken);
                
                $_SESSION['_unique_session_info'] = [
                    'domain' => $domain,
                    'user_id' => $userId,
                    'session_id' => $sessionId,
                    'created_at' => time()
                ];
                
                return true;
            }
            return true;
        }
        
        $sessionInfo = $_SESSION['_unique_session_info'];
        $currentDomain = self::getDomain();
        
        if ($sessionInfo['domain'] !== $currentDomain) {
            $sessionDomainBase = preg_replace('#/.*$#', '', $sessionInfo['domain']);
            $currentDomainBase = preg_replace('#/.*$#', '', $currentDomain);
            
            if ($sessionDomainBase === $currentDomainBase && 
                ($sessionDomainBase === 'localhost' || $sessionDomainBase === '127.0.0.1')) {
                $sessionInfo['domain'] = $currentDomain;
                $_SESSION['_unique_session_info'] = $sessionInfo;
                return true;
            }
            
            return false;
        }
        
        if (isset($_SESSION['admin']) && is_object($_SESSION['admin'])) {
            $currentUserId = $_SESSION['admin']->id_admin ?? null;
            
            if ($sessionInfo['user_id'] !== null && $sessionInfo['user_id'] != $currentUserId) {
                return false;
            }
            
            if ($sessionInfo['user_id'] === null && $currentUserId !== null) {
                $sessionInfo['user_id'] = $currentUserId;
                $userToken = $_SESSION['admin']->token_admin ?? null;
                $sessionInfo['session_id'] = self::generateSessionId($currentDomain, $currentUserId, $userToken);
                $_SESSION['_unique_session_info'] = $sessionInfo;
            }
        }
        
        return true;
    }
    
    /**
     * Destroy unique session
     */
    public static function destroyUniqueSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            session_destroy();
        }
    }
    
    /**
     * Get current unique session info
     *
     * @return array|null Session info or null if not exists
     */
    public static function getSessionInfo() {
        return $_SESSION['_unique_session_info'] ?? null;
    }

    /**
     * Return the CSRF token for the current session, generating one if absent.
     */
    public static function getCsrfToken() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Validate a CSRF token submitted with a form.
     *
     * @param string $token Token from $_POST['_csrf_token']
     * @return bool
     */
    public static function validateCsrfToken($token) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $stored = $_SESSION['_csrf_token'] ?? '';
        return !empty($stored) && hash_equals($stored, (string)$token);
    }

    /**
     * Validate the CSRF token for the current request.
     *
     * Safe HTTP methods (GET/HEAD/OPTIONS) do not mutate state and are
     * exempt. For state-changing methods the token is read from the
     * X-CSRF-Token header (sent by the global AJAX interceptor) or from
     * the _csrf_token POST field (classic form submissions).
     *
     * @return bool
     */
    public static function validateCsrfRequest() {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf_token'] ?? '');
        return self::validateCsrfToken($token);
    }
}

