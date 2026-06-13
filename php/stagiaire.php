<?php
/**
 * stagiaire.php — API espace stagiaire
 * Compatible PHP 5.6+
 * Actions : get_mon_profil | get_mes_rapports | soumettre_rapport | get_mes_evaluations
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

requireStagiaire();

$body   = file_get_contents('php://input');
$json   = json_decode($body, true);
$input  = is_array($json) ? $json : $_POST;
$action = inputGet($input, 'action', '');
$stagId = isset($_SESSION['stagiaire_id']) ? (int)$_SESSION['stagiaire_id'] : 0;

if (!$stagId && $action !== 'get_mon_profil') {
    jsonResponse(false, 'Aucun stage actif associé à ce compte.', null);
}

switch ($action) {

    /* ── Profil + infos stage ── */
    case 'get_mon_profil':
        $db = getDB();
        // Récupérer le stagiaire lié à ce compte
        $stmt = $db->prepare("
            SELECT s.*, d.message as lettre_motivation, d.cv_fichier,
                   d.soumis_le as date_candidature
            FROM stagiaires s
            JOIN demandes d ON s.demande_id = d.id
            WHERE s.utilisateur_id = ?
            LIMIT 1
        ");
        $stmt->execute(array($_SESSION['user_id']));
        $stag = $stmt->fetch();
        if (!$stag) { jsonResponse(false, 'Profil introuvable.', null); }

        // Calculer progression (jours écoulés / durée totale)
        $debut = strtotime($stag['date_debut']);
        $fin   = $stag['date_fin'] ? strtotime($stag['date_fin']) : strtotime($stag['date_debut'] . ' +3 months');
        $now   = time();
        $total = $fin - $debut;
        $prog  = $total > 0 ? min(100, max(0, round(($now - $debut) / $total * 100))) : 0;

        jsonResponse(true, '', array('profil' => $stag, 'progression' => $prog));
        break;

    /* ── Mes rapports soumis ── */
    case 'get_mes_rapports':
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM rapports WHERE stagiaire_id=? ORDER BY semaine ASC");
        $stmt->execute(array($stagId));
        jsonResponse(true, '', array('rapports' => $stmt->fetchAll()));
        break;

    /* ── Soumettre un rapport hebdomadaire ── */
    case 'soumettre_rapport':
        $titre   = sanitize(inputGet($input, 'titre', ''));
        $contenu = sanitize(inputGet($input, 'contenu', ''));
        $semaine = (int)inputGet($input, 'semaine', 1);

        if (empty($titre) || empty($contenu)) {
            jsonResponse(false, 'Titre et contenu requis.', null);
        }
        if ($semaine < 1 || $semaine > 52) {
            jsonResponse(false, 'Numéro de semaine invalide.', null);
        }

        $db = getDB();

        // Vérifier doublon
        $chk = $db->prepare("SELECT id FROM rapports WHERE stagiaire_id=? AND semaine=?");
        $chk->execute(array($stagId, $semaine));
        if ($chk->fetch()) {
            jsonResponse(false, 'Un rapport pour la semaine ' . $semaine . ' existe déjà.', null);
        }

        // Upload fichier optionnel
        $fichier = null;
        if (isset($_FILES['fichier']) && $_FILES['fichier']['error'] === UPLOAD_ERR_OK) {
            $file  = $_FILES['fichier'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($file['tmp_name']);
            if (in_array($mime, UPLOAD_TYPES) && $file['size'] <= UPLOAD_MAX_SIZE) {
                if (!is_dir(UPLOAD_RAPPORTS_DIR)) { mkdir(UPLOAD_RAPPORTS_DIR, 0755, true); }
                $nom     = 'rapport_s' . $semaine . '_stag' . $stagId . '_' . uniqid('', true) . '.pdf';
                if (move_uploaded_file($file['tmp_name'], UPLOAD_RAPPORTS_DIR . $nom)) {
                    $fichier = $nom;
                }
            }
        }

        $db->prepare("INSERT INTO rapports (stagiaire_id, semaine, titre, contenu, fichier) VALUES (?,?,?,?,?)")
           ->execute(array($stagId, $semaine, $titre, $contenu, $fichier));

        jsonResponse(true, 'Rapport soumis avec succès.', null);
        break;

    /* ── Mes évaluations ── */
    case 'get_mes_evaluations':
        $db   = getDB();
        $stmt = $db->prepare("
            SELECT e.*, u.nom as evaluateur_nom
            FROM evaluations e
            LEFT JOIN utilisateurs u ON e.evaluateur_id = u.id
            WHERE e.stagiaire_id = ?
            ORDER BY e.semaine ASC
        ");
        $stmt->execute(array($stagId));
        jsonResponse(true, '', array('evaluations' => $stmt->fetchAll()));
        break;

    default:
        jsonResponse(false, 'Action inconnue.', null);
}
