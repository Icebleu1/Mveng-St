<?php
/**
 * admin_rh.php — API espace Admin/RH étendu
 * Actions : get_stagiaires_complet | evaluer_stagiaire | get_rapports_stagiaire
 *           noter_rapport | get_profils_utilisateurs | toggle_utilisateur
 *           creer_compte_stagiaire | get_evaluations_stagiaire
 */
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

requireAdmin();

$body   = file_get_contents('php://input');
$json   = json_decode($body, true);
$input  = is_array($json) ? $json : $_POST;
$action = inputGet($input, 'action', '');

switch ($action) {

    /* ── Liste stagiaires avec infos complètes ── */
    case 'get_stagiaires_complet':
        $db   = getDB();
        $stmt = $db->query("
            SELECT s.*,
                   u.email as compte_email, u.actif as compte_actif,
                   d.message as lettre_motivation, d.cv_fichier,
                   (SELECT COUNT(*) FROM rapports r WHERE r.stagiaire_id=s.id) as nb_rapports,
                   (SELECT COUNT(*) FROM evaluations e WHERE e.stagiaire_id=s.id) as nb_evaluations,
                   (SELECT ROUND(AVG((e.note_travail+e.note_ponctualite+e.note_initiative+e.note_communication+e.note_technique)/5.0*4),1)
                    FROM evaluations e WHERE e.stagiaire_id=s.id) as moyenne_eval
            FROM stagiaires s
            LEFT JOIN utilisateurs u ON s.utilisateur_id = u.id
            LEFT JOIN demandes d     ON s.demande_id = d.id
            ORDER BY s.ajoute_le DESC
        ");
        jsonResponse(true, '', array('stagiaires' => $stmt->fetchAll()));
        break;

    /* ── Évaluer un stagiaire ── */
    case 'evaluer_stagiaire':
        $stagId  = (int)inputGet($input, 'stagiaire_id', 0);
        $semaine = (int)inputGet($input, 'semaine', 1);
        if (!$stagId) { jsonResponse(false, 'ID stagiaire requis.', null); }

        $notes = array(
            'note_travail'        => min(5, max(0, (int)inputGet($input, 'note_travail', 0))),
            'note_ponctualite'    => min(5, max(0, (int)inputGet($input, 'note_ponctualite', 0))),
            'note_initiative'     => min(5, max(0, (int)inputGet($input, 'note_initiative', 0))),
            'note_communication'  => min(5, max(0, (int)inputGet($input, 'note_communication', 0))),
            'note_technique'      => min(5, max(0, (int)inputGet($input, 'note_technique', 0))),
        );
        $commentaire = sanitize(inputGet($input, 'commentaire', ''));

        $db  = getDB();
        $chk = $db->prepare("SELECT id FROM evaluations WHERE stagiaire_id=? AND semaine=?");
        $chk->execute(array($stagId, $semaine));
        $existing = $chk->fetch();

        if ($existing) {
            $db->prepare("UPDATE evaluations SET note_travail=?,note_ponctualite=?,note_initiative=?,note_communication=?,note_technique=?,commentaire=?,evaluateur_id=? WHERE id=?")
               ->execute(array($notes['note_travail'],$notes['note_ponctualite'],$notes['note_initiative'],$notes['note_communication'],$notes['note_technique'],$commentaire,$_SESSION['user_id'],$existing['id']));
        } else {
            $db->prepare("INSERT INTO evaluations (stagiaire_id,evaluateur_id,semaine,note_travail,note_ponctualite,note_initiative,note_communication,note_technique,commentaire) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute(array($stagId,$_SESSION['user_id'],$semaine,$notes['note_travail'],$notes['note_ponctualite'],$notes['note_initiative'],$notes['note_communication'],$notes['note_technique'],$commentaire));
        }

        // Mettre à jour la note finale du stagiaire
        $db->prepare("UPDATE stagiaires SET note_finale=(SELECT ROUND(AVG((e.note_travail+e.note_ponctualite+e.note_initiative+e.note_communication+e.note_technique)/5.0*4),2) FROM evaluations e WHERE e.stagiaire_id=?) WHERE id=?")
           ->execute(array($stagId, $stagId));

        logAction('EVALUER_STAGIAIRE', 'Stagiaire #' . $stagId . ' semaine ' . $semaine);
        jsonResponse(true, 'Évaluation enregistrée.', null);
        break;

    /* ── Rapports d'un stagiaire ── */
    case 'get_rapports_stagiaire':
        $stagId = (int)inputGet($input, 'stagiaire_id', 0);
        if (!$stagId) { jsonResponse(false, 'ID requis.', null); }
        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM rapports WHERE stagiaire_id=? ORDER BY semaine ASC");
        $stmt->execute(array($stagId));
        jsonResponse(true, '', array('rapports' => $stmt->fetchAll()));
        break;

    /* ── Donner un avis sur un rapport ── */
    case 'noter_rapport':
        $rapportId  = (int)inputGet($input, 'rapport_id', 0);
        $statut     = inputGet($input, 'statut', 'lu');
        $commentaire = sanitize(inputGet($input, 'commentaire_rh', ''));
        if (!$rapportId) { jsonResponse(false, 'ID rapport requis.', null); }
        $statuts = array('lu', 'valide', 'a_revoir');
        if (!in_array($statut, $statuts)) { jsonResponse(false, 'Statut invalide.', null); }

        $db = getDB();
        $db->prepare("UPDATE rapports SET statut=?, commentaire_rh=?, lu_le=NOW() WHERE id=?")
           ->execute(array($statut, $commentaire, $rapportId));
        logAction('NOTER_RAPPORT', 'Rapport #' . $rapportId . ' → ' . $statut);
        jsonResponse(true, 'Rapport mis à jour.', null);
        break;

    /* ── Liste de tous les utilisateurs ── */
    case 'get_profils_utilisateurs':
        $db   = getDB();
        $stmt = $db->query("
            SELECT u.id, u.nom, u.email, u.role, u.actif, u.cree_le, u.dernier_login,
                   s.id as stagiaire_id, s.statut as statut_stage
            FROM utilisateurs u
            LEFT JOIN stagiaires s ON s.utilisateur_id = u.id
            ORDER BY u.role ASC, u.nom ASC
        ");
        jsonResponse(true, '', array('utilisateurs' => $stmt->fetchAll()));
        break;

    /* ── Activer / désactiver un compte ── */
    case 'toggle_utilisateur':
        $uid = (int)inputGet($input, 'user_id', 0);
        if (!$uid || $uid === $_SESSION['user_id']) { jsonResponse(false, 'Action impossible.', null); }
        $db = getDB();
        $db->prepare("UPDATE utilisateurs SET actif = 1 - actif WHERE id=?")->execute(array($uid));
        logAction('TOGGLE_USER', 'User #' . $uid);
        jsonResponse(true, 'Compte mis à jour.', null);
        break;

    /* ── Créer un compte stagiaire après acceptation ── */
    case 'creer_compte_stagiaire':
        $demandeId = (int)inputGet($input, 'demande_id', 0);
        if (!$demandeId) { jsonResponse(false, 'ID demande requis.', null); }

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM demandes WHERE id=? AND statut='accepte'");
        $stmt->execute(array($demandeId));
        $dem  = $stmt->fetch();
        if (!$dem) { jsonResponse(false, 'Demande introuvable ou non acceptée.', null); }

        // Vérifier si un compte existe déjà
        $chk = $db->prepare("SELECT id FROM utilisateurs WHERE email=?");
        $chk->execute(array($dem['email']));
        if ($chk->fetch()) { jsonResponse(false, 'Un compte existe déjà pour cet email.', null); }

        // Mot de passe temporaire
        $mdpTemp  = 'Stage' . date('Y') . '!';
        $hashMdp  = password_hash($mdpTemp, PASSWORD_BCRYPT, array('cost' => 10));

        $db->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe, role) VALUES (?,?,?,'stagiaire')")
           ->execute(array($dem['nom_complet'], $dem['email'], $hashMdp));
        $userId = $db->lastInsertId();

        // Lier le compte à la demande et au stagiaire
        $db->prepare("UPDATE demandes SET utilisateur_id=? WHERE id=?")->execute(array($userId, $demandeId));
        $db->prepare("UPDATE stagiaires SET utilisateur_id=? WHERE demande_id=?")->execute(array($userId, $demandeId));

        logAction('CREER_COMPTE_STAGIAIRE', 'Demande #' . $demandeId . ' → User #' . $userId);
        jsonResponse(true, 'Compte créé.', array(
            'email' => $dem['email'],
            'mdp_temporaire' => $mdpTemp
        ));
        break;

    /* ── Évaluations d'un stagiaire ── */
    case 'get_evaluations_stagiaire':
        $stagId = (int)inputGet($input, 'stagiaire_id', 0);
        if (!$stagId) { jsonResponse(false, 'ID requis.', null); }
        $db   = getDB();
        $stmt = $db->prepare("SELECT e.*, u.nom as evaluateur_nom FROM evaluations e LEFT JOIN utilisateurs u ON e.evaluateur_id=u.id WHERE e.stagiaire_id=? ORDER BY e.semaine ASC");
        $stmt->execute(array($stagId));
        jsonResponse(true, '', array('evaluations' => $stmt->fetchAll()));
        break;

    /* ── Mettre à jour les notes/appréciations d'un stagiaire ── */
    case 'update_stagiaire':
        $stagId      = (int)inputGet($input, 'stagiaire_id', 0);
        $maitre      = sanitize(inputGet($input, 'maitre_stage', ''));
        $appreciation = sanitize(inputGet($input, 'appreciation', ''));
        $notes_int   = sanitize(inputGet($input, 'notes_internes', ''));
        $statut      = inputGet($input, 'statut', '');
        if (!$stagId) { jsonResponse(false, 'ID requis.', null); }
        $statuts = array('actif', 'termine', 'suspendu');
        if ($statut && !in_array($statut, $statuts)) { jsonResponse(false, 'Statut invalide.', null); }

        $db = getDB();
        $sets = array('maitre_stage=?', 'appreciation=?', 'notes_internes=?');
        $params = array($maitre, $appreciation, $notes_int);
        if ($statut) { $sets[] = 'statut=?'; $params[] = $statut; }
        $params[] = $stagId;
        $db->prepare("UPDATE stagiaires SET " . implode(',', $sets) . " WHERE id=?")->execute($params);
        logAction('UPDATE_STAGIAIRE', 'Stagiaire #' . $stagId);
        jsonResponse(true, 'Profil stagiaire mis à jour.', null);
        break;

    default:
        jsonResponse(false, 'Action inconnue : ' . htmlspecialchars($action), null);
}
