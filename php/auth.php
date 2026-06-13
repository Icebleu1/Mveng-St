<?php
/**
 * auth.php — Authentification unifiée (admin + stagiaire)
 * Compatible PHP 5.6+
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$body   = file_get_contents('php://input');
$json   = json_decode($body, true);
$input  = is_array($json) ? $json : $_POST;
$action = '';
if (isset($input['action']) && $input['action'] !== '') {
    $action = $input['action'];
} elseif (isset($_GET['action'])) {
    $action = $_GET['action'];
}

switch ($action) {

    /* ── LOGIN ADMIN ── */
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(false,'Méthode invalide.',null); }
        $email = isset($input['email'])        ? trim($input['email']) : '';
        $mdp   = isset($input['mot_de_passe']) ? $input['mot_de_passe'] : '';
        if (!$email || !$mdp) { jsonResponse(false,'Email et mot de passe requis.',null); }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { jsonResponse(false,'Email invalide.',null); }

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE email=? AND actif=1 AND role IN ('admin','super_admin') LIMIT 1");
        $stmt->execute(array($email));
        $user = $stmt->fetch();

        if (!$user || !password_verify($mdp, $user['mot_de_passe'])) {
            logAction('LOGIN_ADMIN_ECHEC', $email);
            sleep(1);
            jsonResponse(false, 'Identifiants incorrects.', null);
        }
        session_regenerate_id(true);
        $_SESSION['user_id']       = (int)$user['id'];
        $_SESSION['user_nom']      = $user['nom'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['last_activity'] = time();
        $db->prepare("UPDATE utilisateurs SET dernier_login=NOW() WHERE id=?")->execute(array($user['id']));
        logAction('LOGIN_ADMIN_OK', $user['email']);
        jsonResponse(true, 'Connexion réussie.', array('user' => array('id' => $user['id'], 'nom' => $user['nom'], 'role' => $user['role'])));
        break;

    /* ── LOGIN STAGIAIRE ── */
    case 'login_stagiaire':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(false,'Méthode invalide.',null); }
        $email = isset($input['email'])        ? trim($input['email']) : '';
        $mdp   = isset($input['mot_de_passe']) ? $input['mot_de_passe'] : '';
        if (!$email || !$mdp) { jsonResponse(false,'Email et mot de passe requis.',null); }

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM utilisateurs WHERE email=? AND actif=1 AND role='stagiaire' LIMIT 1");
        $stmt->execute(array($email));
        $user = $stmt->fetch();

        if (!$user || !password_verify($mdp, $user['mot_de_passe'])) {
            sleep(1);
            jsonResponse(false, 'Identifiants incorrects.', null);
        }
        // Vérifier que le stagiaire a bien un stage actif
        $stag = $db->prepare("SELECT s.* FROM stagiaires s WHERE s.utilisateur_id=? LIMIT 1");
        $stag->execute(array($user['id']));
        $stagInfo = $stag->fetch();

        session_regenerate_id(true);
        $_SESSION['user_id']        = (int)$user['id'];
        $_SESSION['user_nom']       = $user['nom'];
        $_SESSION['user_email']     = $user['email'];
        $_SESSION['role']           = 'stagiaire';
        $_SESSION['stagiaire_id']   = $stagInfo ? (int)$stagInfo['id'] : null;
        $_SESSION['last_activity']  = time();
        $db->prepare("UPDATE utilisateurs SET dernier_login=NOW() WHERE id=?")->execute(array($user['id']));
        jsonResponse(true, 'Connexion réussie.', array(
            'user' => array('id' => $user['id'], 'nom' => $user['nom'], 'role' => 'stagiaire'),
            'stagiaire_id' => $_SESSION['stagiaire_id']
        ));
        break;

    /* ── LOGOUT ── */
    case 'logout':
        logAction('LOGOUT', isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '');
        session_destroy();
        jsonResponse(true, 'Déconnecté.', null);
        break;

    /* ── CHECK SESSION ── */
    case 'check':
        if (isConnected()) {
            jsonResponse(true, 'Connecté.', array('user' => array(
                'id'           => $_SESSION['user_id'],
                'nom'          => $_SESSION['user_nom'],
                'email'        => $_SESSION['user_email'],
                'role'         => $_SESSION['role'],
                'stagiaire_id' => isset($_SESSION['stagiaire_id']) ? $_SESSION['stagiaire_id'] : null,
            )));
        } else {
            jsonResponse(false, 'Non connecté.', null);
        }
        break;

    /* ── IDENTIFIANTS PAR DÉFAUT ── */
    case 'get_defaults':
        $db   = getDB();
        $stmt = $db->prepare("SELECT email FROM utilisateurs WHERE actif=1 AND role IN ('admin','super_admin') ORDER BY id ASC LIMIT 1");
        $stmt->execute();
        $user = $stmt->fetch();
        jsonResponse(true, '', array('defaults' => array(
            'email'        => $user ? $user['email'] : 'admin@mvengineering.cm',
            'mot_de_passe' => 'Admin1234'
        )));
        break;

    default:
        jsonResponse(false, 'Action inconnue.', null);
}
