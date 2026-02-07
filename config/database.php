<?php
/**
 * Configuración de Base de Datos
 * Sistema de Gestión Financiera Personal - Adaptado para Easypanel
 */
 // Configuración del Idioma para fechas
$db->exec("SET lc_time_names = 'es_ES'");

// Configuración de base de datos usando Variables de Entorno de Easypanel
define('DB_HOST', getenv('DB_HOST') ?: 'basededatos'); // Usa 'basededatos' por defecto
define('DB_USER', getenv('DB_USER') ?: 'root');        // Usa el usuario configurado
define('DB_PASS', getenv('DB_PASSWORD') ?: '');        // Usa la contraseña 'Latreguaep28'
define('DB_NAME', getenv('DB_NAME') ?: 'misfinanzas'); // Usa el nombre de la BD
define('DB_CHARSET', 'utf8mb4');

// Configuración de sesión
define('SESSION_TIMEOUT', 3600); // 1 hora en segundos

// Configuración de seguridad
define('HASH_COST', 12);
define('PASSWORD_MIN_LENGTH', 8);

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            // Nota: Eliminamos el puerto :3306 del host ya que Docker lo gestiona internamente
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch(PDOException $e) {
            error_log("Error de conexión: " . $e->getMessage());
            // En entorno de prueba, puedes descomentar la siguiente línea para ver el error real:
            // die("Error: " . $e->getMessage()); 
            die("Error al conectar con la base de datos. Por favor, contacte al administrador.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    private function __clone() {}
    
    public function __wakeup() {
        throw new Exception("No se puede deserializar un singleton.");
    }
}


/**
 * Clase de Utilidades de Seguridad
 */
class Security {
    
    /**
     * Sanitizar entrada de datos
     */
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
    
    /**
     * Validar correo electrónico
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Hash de contraseña
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
    }
    
    /**
     * Verificar contraseña
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generar token CSRF
     */
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verificar token CSRF
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Obtener IP del usuario
     */
    public static function getUserIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    /**
     * Obtener User Agent
     */
    public static function getUserAgent() {
        return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
    }
}

/**
 * Iniciar sesión segura
 */
function iniciarSesion() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 0); // Cambiar a 1 si usa HTTPS
        session_start();
        
        // Verificar timeout de sesión
        if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
            session_unset();
            session_destroy();
            session_start();
        }
        $_SESSION['LAST_ACTIVITY'] = time();
        
        // Regenerar ID de sesión periódicamente
        if (!isset($_SESSION['CREATED'])) {
            $_SESSION['CREATED'] = time();
        } elseif (time() - $_SESSION['CREATED'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['CREATED'] = time();
        }
    }
}

/**
 * Verificar si el usuario está autenticado
 */
function verificarAutenticacion() {
    if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_email'])) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Función para logging de errores y actividades
 */
function registrarLog($tipo, $descripcion, $id_usuario = null) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO log_actividades (id_usuario, tipo_actividad, descripcion, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $id_usuario,
            $tipo,
            $descripcion,
            Security::getUserIP(),
            Security::getUserAgent()
        ]);
    } catch(Exception $e) {
        error_log("Error al registrar log: " . $e->getMessage());
    }
}
?>