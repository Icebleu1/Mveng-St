<?php
/**
 * config.php — Configuration centrale MVENGINEERING v2
 * Compatible PHP 5.6+ (XAMPP 3.3.0)
 */

// ─── Connexion MySQL ──────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'gestion_stagiaires');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8');

// ─── Entreprise ───────────────────────────────────────────────
define('ENTREPRISE_NOM',     'MVENGINEERING');
define('ENTREPRISE_EMAIL',   'contact@mvengineering.cm');
define('ENTREPRISE_TEL',     '+237 222 345 678');
define('ENTREPRISE_ADRESSE', 'Yaoundé, Cameroun');
define('ENTREPRISE_SLOGAN',  "Solutions d'ingénierie innovantes");

// ─── SMTP ─────────────────────────────────────────────────────
define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);
define('SMTP_USER',       'stagemv2k26@gmail.com');  // ← À MODIFIER
define('SMTP_PASS',       'vlxz qlwb ggoj wadr');     // ← Mot de passe app Gmail
define('SMTP_FROM_NAME',  ENTREPRISE_NOM);
define('SMTP_FROM_EMAIL', ENTREPRISE_EMAIL);

// ─── Upload ───────────────────────────────────────────────────
define('UPLOAD_DIR',         dirname(__DIR__) . '/uploads/');
define('UPLOAD_RAPPORTS_DIR',dirname(__DIR__) . '/uploads/rapports/');
define('UPLOAD_MAX_SIZE',    5242880);
define('UPLOAD_TYPES',       array('application/pdf'));

// ─── Sécurité ─────────────────────────────────────────────────
define('SESSION_DUREE', 3600);
define('APP_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost')
    . rtrim(dirname(dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']))), '/'));

// ─── Session ──────────────────────────────────────────────────
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_path', '/');
    ini_set('session.use_only_cookies', 1);
    session_name('MVENG_SESSION');
    session_start();
}

// ─── PDO ──────────────────────────────────────────────────────
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ));
        } catch (PDOException $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            die(json_encode(array('success' => false, 'message' => 'Erreur BDD.')));
        }
    }
    return $pdo;
}

// ─── Helpers rôles ────────────────────────────────────────────
function isConnected() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], array('admin','super_admin'));
}

function isStagiaire() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'stagiaire';
}

function requireAdmin() {
    if (!isAdmin()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(array('success' => false, 'message' => 'Accès refusé.', 'redirect' => 'login.html'));
        exit;
    }
    _refreshSession();
}

function requireStagiaire() {
    if (!isConnected()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(array('success' => false, 'message' => 'Non connecté.', 'redirect' => 'login-stagiaire.html'));
        exit;
    }
    _refreshSession();
}

function _refreshSession() {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_DUREE) {
        session_destroy();
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(array('success' => false, 'message' => 'Session expirée.', 'redirect' => 'login.html?timeout=1'));
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// ─── Utilitaires ──────────────────────────────────────────────
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($success, $message, $data) {
    if ($data === null) { $data = array(); }
    header('Content-Type: application/json');
    echo json_encode(array_merge(array('success' => $success, 'message' => $message), $data));
    exit;
}

function logAction($action, $details) {
    if ($details === null) { $details = ''; }
    try {
        $db = getDB();
        $db->prepare("INSERT INTO logs_admin (user_id, action, details, ip) VALUES (?,?,?,?)")
           ->execute(array(
               isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null,
               $action, $details,
               isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown'
           ));
    } catch (Exception $e) {}
}

function inputGet($input, $key, $default) {
    return isset($input[$key]) ? $input[$key] : $default;
}
