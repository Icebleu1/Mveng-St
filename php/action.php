<?php
/**
 * action.php — Actions sur les demandes
 * Compatible PHP 5.6+ (XAMPP 3.3.0)
 * Actions publiques : get_demandes | get_stats | get_stagiaires
 * Actions admin     : accepter | refuser | supprimer
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/mailer.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$body  = file_get_contents('php://input');
$json  = json_decode($body, true);
$input = is_array($json) ? $json : $_POST;

$action = isset($input['action']) ? $input['action'] : '';

// Actions accessibles sans connexion
$PUBLIC_ACTIONS = array('get_demandes', 'get_stats', 'get_stagiaires');
if (!in_array($action, $PUBLIC_ACTIONS)) {
    requireAdmin();
}

switch ($action) {

    // ─── Récupérer toutes les demandes ─────────────────────────
    case 'get_demandes':
        $db     = getDB();
        $statut = isset($input['statut']) ? $input['statut'] : '';
        $search = isset($input['search']) ? $input['search'] : '';
        $page   = max(1, (int)(isset($input['page']) ? $input['page'] : 1));
        $limit  = 15;
        $offset = ($page - 1) * $limit;

        $where  = array();
        $params = array();

        if ($statut && in_array($statut, array('en_attente','accepte','refuse'))) {
            $where[]  = 'd.statut = ?';
            $params[] = $statut;
        }
        if ($search) {
            $where[] = '(d.nom_complet LIKE ? OR d.email LIKE ? OR d.ecole LIKE ? OR d.filiere LIKE ?)';
            $s       = '%' . $search . '%';
            $params  = array_merge($params, array($s, $s, $s, $s));
        }

        $clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $cnt = $db->prepare("SELECT COUNT(*) FROM demandes d " . $clause);
        $cnt->execute($params);
        $total = (int)$cnt->fetchColumn();

        $sql  = "SELECT d.*, u.nom as traite_par_nom
                 FROM demandes d
                 LEFT JOIN utilisateurs u ON d.traite_par = u.id
                 " . $clause . "
                 ORDER BY d.soumis_le DESC
                 LIMIT " . $limit . " OFFSET " . $offset;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $demandes = $stmt->fetchAll();

        jsonResponse(true, '', array(
            'demandes' => $demandes,
            'total'    => $total,
            'pages'    => (int)ceil($total / $limit),
            'page'     => $page
        ));
        break;

    // ─── Accepter une demande ──────────────────────────────────
    case 'accepter':
        $id = (int)(isset($input['id']) ? $input['id'] : 0);
        if (!$id) jsonResponse(false, 'ID invalide.', null);

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM demandes WHERE id = ?");
        $stmt->execute(array($id));
        $demande = $stmt->fetch();

        if (!$demande) jsonResponse(false, 'Demande introuvable.', null);
        if ($demande['statut'] !== 'en_attente') jsonResponse(false, 'Cette demande a deja ete traitee.', null);

        $db->prepare("UPDATE demandes SET statut='accepte', traite_le=NOW(), traite_par=? WHERE id=?")
           ->execute(array($_SESSION['user_id'], $id));

        preg_match('/(\d+)/', $demande['duree'], $m);
        $mois    = isset($m[1]) ? (int)$m[1] : 3;
        $dateFin = date('Y-m-d', strtotime($demande['date_souhaitee'] . ' +' . $mois . ' months'));

        $db->prepare("INSERT INTO stagiaires
                      (demande_id, nom_complet, email, telephone, ecole, filiere, date_debut, duree, date_fin, statut)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif')")
           ->execute(array(
               $id, $demande['nom_complet'], $demande['email'], $demande['telephone'],
               $demande['ecole'], $demande['filiere'], $demande['date_souhaitee'],
               $demande['duree'], $dateFin
           ));

        $sujet  = 'Felicitations ! Votre demande de stage a ete acceptee - ' . ENTREPRISE_NOM;
        $corps  = getEmailAcceptation($demande);
        $envoye = envoyerEmail($demande['email'], $demande['nom_complet'], $sujet, $corps);

        $db->prepare("INSERT INTO notifications (demande_id, type, destinataire, sujet, corps, envoye, envoye_le)
                      VALUES (?, 'acceptation', ?, ?, ?, ?, NOW())")
           ->execute(array($id, $demande['email'], $sujet, $corps, $envoye ? 1 : 0));

        logAction('ACCEPTER_DEMANDE', 'Demande #' . $id . ' - ' . $demande['nom_complet']);
        jsonResponse(true, 'Demande acceptee. Le candidat a ete notifie.', array('email_envoye' => $envoye));
        break;

    // ─── Refuser une demande ───────────────────────────────────
    case 'refuser':
        $id    = (int)(isset($input['id']) ? $input['id'] : 0);
        $motif = sanitize(isset($input['motif']) ? $input['motif'] : '');
        if (!$id) jsonResponse(false, 'ID invalide.', null);

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM demandes WHERE id = ?");
        $stmt->execute(array($id));
        $demande = $stmt->fetch();

        if (!$demande) jsonResponse(false, 'Demande introuvable.', null);
        if ($demande['statut'] !== 'en_attente') jsonResponse(false, 'Cette demande a deja ete traitee.', null);

        $db->prepare("UPDATE demandes SET statut='refuse', traite_le=NOW(), traite_par=? WHERE id=?")
           ->execute(array($_SESSION['user_id'], $id));

        $sujet  = 'Reponse a votre demande de stage - ' . ENTREPRISE_NOM;
        $corps  = getEmailRefus($demande, $motif);
        $envoye = envoyerEmail($demande['email'], $demande['nom_complet'], $sujet, $corps);

        $db->prepare("INSERT INTO notifications (demande_id, type, destinataire, sujet, corps, envoye, envoye_le)
                      VALUES (?, 'refus', ?, ?, ?, ?, NOW())")
           ->execute(array($id, $demande['email'], $sujet, $corps, $envoye ? 1 : 0));

        logAction('REFUSER_DEMANDE', 'Demande #' . $id . ' - ' . $demande['nom_complet']);
        jsonResponse(true, 'Demande refusee. Le candidat a ete notifie.', array('email_envoye' => $envoye));
        break;

    // ─── Supprimer une demande ─────────────────────────────────
    case 'supprimer':
        $id = (int)(isset($input['id']) ? $input['id'] : 0);
        if (!$id) jsonResponse(false, 'ID invalide.', null);

        $db   = getDB();
        $stmt = $db->prepare("SELECT cv_fichier, nom_complet FROM demandes WHERE id = ?");
        $stmt->execute(array($id));
        $demande = $stmt->fetch();

        if (!$demande) jsonResponse(false, 'Demande introuvable.', null);

        if ($demande['cv_fichier'] && file_exists(UPLOAD_DIR . $demande['cv_fichier'])) {
            unlink(UPLOAD_DIR . $demande['cv_fichier']);
        }

        $db->prepare("DELETE FROM demandes WHERE id = ?")->execute(array($id));
        logAction('SUPPRIMER_DEMANDE', 'Demande #' . $id . ' - ' . $demande['nom_complet']);
        jsonResponse(true, 'Demande supprimee avec succes.', null);
        break;

    // ─── Statistiques ──────────────────────────────────────────
    case 'get_stats':
        $db = getDB();

        $stats = array(
            'total'             => (int)$db->query("SELECT COUNT(*) FROM demandes")->fetchColumn(),
            'en_attente'        => (int)$db->query("SELECT COUNT(*) FROM demandes WHERE statut='en_attente'")->fetchColumn(),
            'acceptees'         => (int)$db->query("SELECT COUNT(*) FROM demandes WHERE statut='accepte'")->fetchColumn(),
            'refusees'          => (int)$db->query("SELECT COUNT(*) FROM demandes WHERE statut='refuse'")->fetchColumn(),
            'stagiaires_actifs' => (int)$db->query("SELECT COUNT(*) FROM stagiaires WHERE statut='actif'")->fetchColumn(),
        );

        $evolution = $db->query("
            SELECT DATE_FORMAT(soumis_le,'%Y-%m') as mois, COUNT(*) as total
            FROM demandes
            WHERE soumis_le >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY mois ORDER BY mois
        ")->fetchAll();

        $filieres = $db->query("
            SELECT filiere, COUNT(*) as total
            FROM demandes GROUP BY filiere ORDER BY total DESC LIMIT 8
        ")->fetchAll();

        jsonResponse(true, '', array(
            'stats'     => $stats,
            'evolution' => $evolution,
            'filieres'  => $filieres
        ));
        break;

    // ─── Liste stagiaires ──────────────────────────────────────
    case 'get_stagiaires':
        $db   = getDB();
        $stmt = $db->query("SELECT * FROM stagiaires ORDER BY date_debut DESC");
        jsonResponse(true, '', array('stagiaires' => $stmt->fetchAll()));
        break;

    default:
        jsonResponse(false, 'Action non reconnue.', null);
}
