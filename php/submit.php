<?php
/**
 * submit.php — Traitement du formulaire de demande de stage
 * Compatible PHP 5.6+ (XAMPP 3.3.0)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Methode non autorisee.', null);
}

// ─── Validation des champs obligatoires ───────────────────────
$champs = array('nom_complet', 'email', 'telephone', 'ecole', 'filiere', 'niveau', 'date_souhaitee', 'duree');
$data   = array();

foreach ($champs as $champ) {
    $val = isset($_POST[$champ]) ? trim($_POST[$champ]) : '';
    if (empty($val)) {
        jsonResponse(false, 'Le champ "' . $champ . '" est obligatoire.', null);
    }
    $data[$champ] = $val;
}

if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Adresse email invalide.', null);
}

$date = DateTime::createFromFormat('Y-m-d', $data['date_souhaitee']);
if (!$date || $date <= new DateTime()) {
    jsonResponse(false, 'La date souhaitee doit etre dans le futur.', null);
}

$niveaux = array('Licence 1','Licence 2','Licence 3','Master 1','Master 2','BTS','PROBATOIRE','Doctorat','Autre');
if (!in_array($data['niveau'], $niveaux)) {
    jsonResponse(false, "Niveau d'etude invalide.", null);
}

$data['message'] = sanitize(isset($_POST['message']) ? $_POST['message'] : '');

// ─── Upload CV ────────────────────────────────────────────────
$cv_fichier = null;

if (isset($_FILES['cv']) && $_FILES['cv']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['cv'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, "Erreur lors de l'upload du CV.", null);
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        jsonResponse(false, 'Le CV depasse la taille maximale de 5 Mo.', null);
    }

    // Vérification type MIME
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, UPLOAD_TYPES)) {
        jsonResponse(false, 'Seuls les fichiers PDF sont acceptes pour le CV.', null);
    }

    $nom_unique = 'cv_' . uniqid('', true) . '.pdf';

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $nom_unique)) {
        jsonResponse(false, 'Impossible de sauvegarder le CV. Verifiez les permissions du dossier uploads/.', null);
    }

    $cv_fichier = $nom_unique;
}

// ─── Vérification doublon ─────────────────────────────────────
$db    = getDB();
$check = $db->prepare("SELECT id FROM demandes WHERE email = ? AND statut = 'en_attente'");
$check->execute(array($data['email']));
if ($check->fetch()) {
    jsonResponse(false, 'Une demande en attente existe deja pour cette adresse email.', null);
}

// ─── Insertion ───────────────────────────────────────────────
$sql  = "INSERT INTO demandes
         (nom_complet, email, telephone, ecole, filiere, niveau, date_souhaitee, duree, message, cv_fichier)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $db->prepare($sql);
$stmt->execute(array(
    sanitize($data['nom_complet']),
    $data['email'],
    sanitize($data['telephone']),
    sanitize($data['ecole']),
    sanitize($data['filiere']),
    $data['niveau'],
    $data['date_souhaitee'],
    sanitize($data['duree']),
    $data['message'],
    $cv_fichier
));

$demande_id = $db->lastInsertId();

// ─── Email de confirmation ────────────────────────────────────
$email_envoye = false;
try {
    $sujet        = 'Confirmation de votre demande de stage - ' . ENTREPRISE_NOM;
    $corps        = getEmailConfirmation($data);
    $email_envoye = envoyerEmail($data['email'], $data['nom_complet'], $sujet, $corps);

    $db->prepare("INSERT INTO notifications (demande_id, type, destinataire, sujet, corps, envoye, envoye_le)
                  VALUES (?, 'confirmation', ?, ?, ?, ?, NOW())")
       ->execute(array($demande_id, $data['email'], $sujet, $corps, $email_envoye ? 1 : 0));
} catch (Exception $e) {
    // Email non critique
}

jsonResponse(true, 'Votre demande a ete soumise avec succes ! Vous recevrez une reponse sous 5 jours ouvres.', array(
    'demande_id'   => (int)$demande_id,
    'email_envoye' => $email_envoye
));
