<?php
/**
 * get_cv.php — Téléchargement sécurisé d'un CV
 * Compatible PHP 5.6+ (XAMPP 3.3.0)
 */

require_once __DIR__ . '/config.php';

requireAdmin();

$fichier = isset($_GET['fichier']) ? basename($_GET['fichier']) : '';

if (!$fichier || !preg_match('/^cv_[a-f0-9_.]+\.pdf$/i', $fichier)) {
    http_response_code(400);
    die('Fichier invalide.');
}

$path = UPLOAD_DIR . $fichier;

if (!file_exists($path)) {
    http_response_code(404);
    die('Fichier introuvable.');
}

$real_path   = realpath($path);
$real_upload = realpath(UPLOAD_DIR);

if ($real_path === false || strpos($real_path, $real_upload) !== 0) {
    http_response_code(403);
    die('Acces refuse.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $fichier . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=0');
readfile($path);
exit;
