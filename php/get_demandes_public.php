<?php
/**
 * get_demandes_public.php — Données publiques sans authentification
 * Compatible PHP 5.6+ (XAMPP 3.3.0)
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$page   = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$limit  = 15;
$offset = ($page - 1) * $limit;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$db     = getDB();
$where  = array();
$params = array();

if ($search) {
    $where[]  = '(nom_complet LIKE ? OR ecole LIKE ? OR filiere LIKE ?)';
    $s        = '%' . $search . '%';
    $params   = array($s, $s, $s);
}

$clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

$cnt = $db->prepare("SELECT COUNT(*) FROM demandes " . $clause);
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();

$sql  = "SELECT id, nom_complet, ecole, filiere, niveau, date_souhaitee, duree, statut, soumis_le
         FROM demandes " . $clause . "
         ORDER BY soumis_le DESC
         LIMIT " . $limit . " OFFSET " . $offset;
$stmt = $db->prepare($sql);
$stmt->execute($params);

jsonResponse(true, '', array(
    'demandes' => $stmt->fetchAll(),
    'total'    => $total,
    'pages'    => (int)ceil($total / $limit),
    'page'     => $page
));
