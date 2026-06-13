<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Diagnostic & Test Email — MVENGINEERING</title>
  <style>
    *{box-sizing:border-box} body{font-family:Arial,sans-serif;max-width:760px;margin:40px auto;padding:0 20px;background:#f3f4f6}
    h1{color:#1a56db} h2{color:#374151;border-bottom:2px solid #e5e7eb;padding-bottom:6px;margin-top:28px}
    .ok  {background:#d1fae5;border-left:4px solid #059669;padding:10px 14px;border-radius:4px;margin:6px 0}
    .err {background:#fee2e2;border-left:4px solid #dc2626;padding:10px 14px;border-radius:4px;margin:6px 0}
    .inf {background:#dbeafe;border-left:4px solid #1a56db;padding:10px 14px;border-radius:4px;margin:6px 0}
    .warn{background:#fef3c7;border-left:4px solid #d97706;padding:10px 14px;border-radius:4px;margin:6px 0}
    code{background:#1e293b;color:#e2e8f0;padding:2px 7px;border-radius:3px;font-size:13px}
    pre {background:#1e293b;color:#e2e8f0;padding:16px;border-radius:8px;overflow-x:auto;font-size:12px;white-space:pre-wrap}
    input[type=email],input[type=text]{width:100%;padding:9px 12px;border:1.5px solid #d1d5db;border-radius:6px;font-size:14px;margin-top:6px}
    button{background:#1a56db;color:#fff;border:none;padding:11px 24px;border-radius:6px;font-size:14px;font-weight:700;cursor:pointer;margin-top:8px}
    button:hover{background:#1e3a8a}
    table{width:100%;border-collapse:collapse;font-size:13px;margin-top:8px}
    td,th{padding:8px 12px;border-bottom:1px solid #e5e7eb;text-align:left}
    th{background:#f3f4f6;font-weight:600;color:#374151}
  </style>
</head>
<body>
<h1>&#128295; Diagnostic MVENGINEERING — XAMPP 3.3.0</h1>
<div class="warn">&#9888; Supprimez ce fichier <code>test.php</code> apr&egrave;s configuration.</div>

<?php
require_once __DIR__ . '/php/config.php';
require_once __DIR__ . '/php/mailer.php';
?>

<h2>1. PHP &amp; Extensions</h2>
<?php
echo '<div class="ok">&#10003; PHP actif &mdash; Version : <strong>' . PHP_VERSION . '</strong></div>';
$exts = array('pdo','pdo_mysql','fileinfo','json','mbstring','openssl');
foreach ($exts as $ext) {
    if (extension_loaded($ext)) {
        echo "<div class='ok'>&#10003; Extension <code>$ext</code></div>";
    } else {
        echo "<div class='err'>&#10007; Extension <code>$ext</code> MANQUANTE &mdash; activez dans php.ini</div>";
    }
}
?>

<h2>2. Base de donn&eacute;es MySQL</h2>
<?php
try {
    $db = getDB();
    echo '<div class="ok">&#10003; MySQL connect&eacute; &mdash; Base : <code>' . DB_NAME . '</code></div>';
    $tables = array('utilisateurs','demandes','stagiaires','notifications','logs_admin');
    echo '<table><tr><th>Table</th><th>Enregistrements</th><th>Statut</th></tr>';
    foreach ($tables as $t) {
        $ex = $db->query("SHOW TABLES LIKE '$t'")->rowCount() > 0;
        $ct = $ex ? $db->query("SELECT COUNT(*) FROM $t")->fetchColumn() : '—';
        echo '<tr><td><code>' . $t . '</code></td><td>' . $ct . '</td>'
           . '<td>' . ($ex ? '&#10003; OK' : '&#10007; MANQUANTE') . '</td></tr>';
    }
    echo '</table>';
} catch (Exception $e) {
    echo '<div class="err">&#10007; MySQL : ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

<h2>3. Dossier uploads</h2>
<?php
$up = __DIR__ . '/uploads/';
if (!is_dir($up)) { mkdir($up,0755,true); }
echo is_dir($up)
    ? '<div class="ok">&#10003; Dossier <code>uploads/</code> pr&eacute;sent</div>'
    : '<div class="err">&#10007; Dossier <code>uploads/</code> absent</div>';
echo is_writable($up)
    ? '<div class="ok">&#10003; Dossier <code>uploads/</code> accessible en &eacute;criture</div>'
    : '<div class="err">&#10007; Dossier <code>uploads/</code> non accessible en &eacute;criture</div>';
?>

<h2>4. Configuration PHPMailer &amp; SMTP</h2>
<?php
$diag = diagnosticMailer();
echo '<table><tr><th>Param&egrave;tre</th><th>Valeur</th><th>Statut</th></tr>';
$rows = array(
    array('PHPMailer install&eacute;',  $diag['phpmailer_present'] ? 'Oui' : 'Non', $diag['phpmailer_present']),
    array('Dossier vendor/',            $diag['phpmailer_dir'],                       $diag['phpmailer_present']),
    array('SMTP configur&eacute;',      $diag['smtp_configure'] ? 'Oui' : 'Non (valeurs par d&eacute;faut)', $diag['smtp_configure']),
    array('SMTP Host',                  $diag['smtp_host'],                           !empty($diag['smtp_host'])),
    array('SMTP Port',                  $diag['smtp_port'],                           !empty($diag['smtp_port'])),
    array('SMTP User',                  $diag['smtp_user'],                           $diag['smtp_configure']),
    array('From Email',                 $diag['from_email'],                          !empty($diag['from_email'])),
    array('OpenSSL',                    $diag['openssl_available'] ? 'Oui' : 'Non',  $diag['openssl_available']),
    array('Mode d\'envoi actif',        $diag['mode'],                                true),
);
foreach ($rows as $r) {
    $icon = $r[2] ? '&#10003;' : '&#9888;';
    $cls  = $r[2] ? '' : 'style="color:#d97706"';
    echo '<tr><td>' . $r[0] . '</td><td><code ' . $cls . '>' . htmlspecialchars($r[1]) . '</code></td><td>' . $icon . '</td></tr>';
}
echo '</table>';

if (!$diag['phpmailer_present']) {
    echo '<div class="err" style="margin-top:12px">
    &#10007; <strong>PHPMailer absent.</strong><br>
    T&eacute;l&eacute;charger : <a href="https://github.com/PHPMailer/PHPMailer/releases/tag/v5.2.28" target="_blank">PHPMailer 5.2.28</a><br>
    Copier <code>class.phpmailer.php</code> + <code>class.smtp.php</code> dans <code>vendor/phpmailer/</code>
    </div>';
}
if (!$diag['smtp_configure']) {
    echo '<div class="warn" style="margin-top:12px">
    &#9888; <strong>SMTP non configur&eacute;.</strong> Modifiez <code>php/config.php</code> :<br><br>
    <code>SMTP_USER = \'votre_vraie_adresse@gmail.com\'</code><br>
    <code>SMTP_PASS = \'xxxx xxxx xxxx xxxx\'</code> (mot de passe application Gmail)<br><br>
    G&eacute;n&eacute;rer un mot de passe app : <a href="https://myaccount.google.com/apppasswords" target="_blank">myaccount.google.com/apppasswords</a>
    </div>';
}
?>

<h2>5. Test d'envoi d'email</h2>
<?php
$result_msg = '';

if (isset($_POST['test_email']) && !empty($_POST['test_email'])) {
    $dest = trim($_POST['test_email']);

    if (!filter_var($dest, FILTER_VALIDATE_EMAIL)) {
        $result_msg = '<div class="err">&#10007; Adresse email invalide.</div>';
    } else {
        $sujet = '[TEST] MVENGINEERING — Diagnostic email ' . date('d/m/Y H:i');
        $corps = "
          <h2 style='color:#1a56db'>&#10003; Test d'envoi r&eacute;ussi !</h2>
          <p>Cet email a &eacute;t&eacute; envoy&eacute; depuis votre installation MVENGINEERING.</p>
          <table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:13px'>
            <tr><td style='padding:6px;color:#6b7280'>Serveur</td><td><strong>" . htmlspecialchars(gethostname()) . "</strong></td></tr>
            <tr><td style='padding:6px;color:#6b7280'>PHP</td><td><strong>" . PHP_VERSION . "</strong></td></tr>
            <tr><td style='padding:6px;color:#6b7280'>Mode</td><td><strong>" . diagnosticMailer()['mode'] . "</strong></td></tr>
            <tr><td style='padding:6px;color:#6b7280'>Date</td><td><strong>" . date('d/m/Y H:i:s') . "</strong></td></tr>
          </table>
          <p style='color:#6b7280;font-size:12px'>Email de diagnostic &mdash; MVENGINEERING</p>
        ";
        $corps_html = emailLayout($corps);

        /* Capturer les erreurs PHP éventuelles */
        ob_start();
        $ok = envoyerEmail($dest, $dest, $sujet, $corps_html);
        $output = ob_get_clean();

        if ($ok) {
            $result_msg = '<div class="ok">&#10003; Email envoy&eacute; avec succ&egrave;s vers <strong>'
                        . htmlspecialchars($dest) . '</strong> !<br>'
                        . 'V&eacute;rifiez votre bo&icirc;te de r&eacute;ception (et les spams).</div>';
        } else {
            $result_msg = '<div class="err">&#10007; &Eacute;chec de l\'envoi vers <strong>'
                        . htmlspecialchars($dest) . '</strong>.<br>'
                        . 'Consultez <code>error_log</code> PHP pour le d&eacute;tail :<br>'
                        . '<code>C:\xampp\php\logs\php_error_log</code></div>';
            if (!empty($output)) {
                $result_msg .= '<pre>' . htmlspecialchars($output) . '</pre>';
            }
        }
    }
}

echo $result_msg;
?>

<form method="POST" style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.06);margin-top:12px">
  <label style="font-weight:600">Adresse email de test :</label>
  <input type="email" name="test_email" placeholder="votre@email.com"
         value="<?php echo isset($_POST['test_email']) ? htmlspecialchars($_POST['test_email']) : ''; ?>">
  <button type="submit">&#9993; Envoyer un email de test</button>
</form>

<h2>6. Logs PHP r&eacute;cents</h2>
<?php
$logFile = 'C:\\xampp\\php\\logs\\php_error_log';
if (!file_exists($logFile)) { $logFile = ini_get('error_log'); }
if ($logFile && file_exists($logFile)) {
    $lines   = file($logFile);
    $mailer  = array_filter($lines, function($l) { return strpos($l, 'MVENGINEERING') !== false; });
    $mailer  = array_slice(array_values($mailer), -10);
    if (count($mailer)) {
        echo '<pre>' . htmlspecialchars(implode('', $mailer)) . '</pre>';
    } else {
        echo '<div class="inf">Aucune erreur MVENGINEERING dans les logs r&eacute;cents.</div>';
    }
} else {
    echo '<div class="inf">Fichier de log introuvable. Chemin : <code>' . htmlspecialchars((string)ini_get('error_log')) . '</code></div>';
}
?>

<hr style="margin-top:32px">
<p style="color:#6b7280;font-size:13px">
  <strong>&#9888; Supprimez ce fichier apr&egrave;s configuration.</strong><br>
  <a href="index.html">&#8592; Accueil</a> &nbsp;&middot;&nbsp;
  <a href="login.html">Connexion admin</a> &nbsp;&middot;&nbsp;
  <a href="generer_hash.php">Utilitaire mot de passe</a>
</p>
</body>
</html>
