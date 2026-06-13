<?php
/**
 * mailer.php — Envoi d'emails MVENGINEERING
 * Compatible PHP 5.6 + PHPMailer 5.2.28
 *
 * ══════════════════════════════════════════════════════════════
 *  INSTALLATION PHPMAILER 5.2.28 (obligatoire pour PHP 5.6)
 * ══════════════════════════════════════════════════════════════
 *  1. Télécharger : https://github.com/PHPMailer/PHPMailer/releases/tag/v5.2.28
 *  2. Extraire le ZIP
 *  3. Copier UNIQUEMENT ces 3 fichiers dans  vendor/phpmailer/ :
 *       - class.phpmailer.php
 *       - class.smtp.php
 *       - class.pop3.php  (optionnel)
 *  NE PAS utiliser PHPMailer v6+ → requiert PHP 7+ et les namespaces
 *
 * ══════════════════════════════════════════════════════════════
 *  CONFIGURATION GMAIL (dans config.php)
 * ══════════════════════════════════════════════════════════════
 *  1. Activer la validation en 2 étapes sur votre compte Gmail
 *  2. Aller sur : https://myaccount.google.com/apppasswords
 *  3. Créer un mot de passe d'application "Autre" → copier les 16 caractères
 *  4. Renseigner dans config.php :
 *       SMTP_USER = 'votre_vraie_adresse@gmail.com'
 *       SMTP_PASS = 'xxxx xxxx xxxx xxxx'  (16 caractères sans espaces)
 */

require_once __DIR__ . '/config.php';

/* ────────────────────────────────────────────────────────────
   Chargement PHPMailer 5.x
   class.smtp.php DOIT être chargé explicitement avant class.phpmailer.php
   sinon PHPMailer ne peut pas établir la connexion SMTP
   ──────────────────────────────────────────────────────────── */
$_MAILER_DIR   = dirname(__DIR__) . '/vendor/phpmailer/';
$_MAILER_MAIN  = $_MAILER_DIR . 'class.phpmailer.php';
$_MAILER_SMTP  = $_MAILER_DIR . 'class.smtp.php';
$_MAILER_READY = file_exists($_MAILER_MAIN) && file_exists($_MAILER_SMTP);

if ($_MAILER_READY) {
    require_once $_MAILER_SMTP;   /* SMTP avant PHPMailer — obligatoire */
    require_once $_MAILER_MAIN;
}

/* ────────────────────────────────────────────────────────────
   Vérification de la configuration SMTP
   ──────────────────────────────────────────────────────────── */
function smtpEstConfigure() {
    return (
        defined('SMTP_USER') &&
        SMTP_USER !== '' &&
        SMTP_USER !== 'votre_email@gmail.com' &&
        defined('SMTP_PASS') &&
        SMTP_PASS !== '' &&
        SMTP_PASS !== 'votre_mot_de_passe_application'
    );
}

/* ────────────────────────────────────────────────────────────
   ENVOI D'EMAIL — Point d'entrée principal
   Retourne true si envoyé, false sinon
   Le détail de l'erreur est écrit dans error_log PHP
   ──────────────────────────────────────────────────────────── */
function envoyerEmail($to_email, $to_nom, $sujet, $corps) {
    global $_MAILER_READY;

    /* Validation basique */
    if (empty($to_email) || !filter_var($to_email, FILTER_VALIDATE_EMAIL)) {
        error_log('[MVENGINEERING Mailer] Adresse email invalide : ' . $to_email);
        return false;
    }

    /* Mode fallback : PHPMailer absent OU SMTP non configuré */
    if (!$_MAILER_READY || !smtpEstConfigure()) {
        return _envoyerMailNatif($to_email, $to_nom, $sujet, $corps);
    }

    return _envoyerPhpMailer($to_email, $to_nom, $sujet, $corps);
}

/* ────────────────────────────────────────────────────────────
   Envoi via PHPMailer 5.x + SMTP
   ──────────────────────────────────────────────────────────── */
function _envoyerPhpMailer($to_email, $to_nom, $sujet, $corps) {
    try {
        $mail = new PHPMailer();

        /* ── Débogage (0=silencieux, 1=erreurs, 2=verbeux) ── */
        $mail->SMTPDebug = 0;   /* Mettre à 2 pour déboguer, puis remettre à 0 */

        /* ── Configuration SMTP ── */
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->Port       = (int)SMTP_PORT;

        /* Choisir TLS (587) ou SSL (465) selon le port */
        if ((int)SMTP_PORT === 465) {
            $mail->SMTPSecure = 'ssl';
        } else {
            $mail->SMTPSecure = 'tls';
        }

        /* ── Option critique sous XAMPP : désactiver la vérification SSL ──
           XAMPP utilise un certificat auto-signé qui échoue la vérification.
           En production sur un vrai serveur, supprimer ou commenter ces lignes.
           ────────────────────────────────────────────────────────────────── */
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            )
        );

        /* ── Timeout en secondes ── */
        $mail->Timeout = 15;

        /* ── Encodage ── */
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';

        /* ── Expéditeur et destinataire ── */
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to_email, $to_nom);
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);

        /* ── Contenu ── */
        $mail->isHTML(true);
        $mail->Subject = $sujet;
        $mail->Body    = $corps;
        $mail->AltBody = _htmlToText($corps);

        $ok = $mail->send();

        if (!$ok) {
            error_log('[MVENGINEERING Mailer] PHPMailer send() == false : ' . $mail->ErrorInfo);
        }

        return $ok;

    } catch (phpmailerException $e) {
        error_log('[MVENGINEERING Mailer] phpmailerException : ' . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log('[MVENGINEERING Mailer] Exception : ' . $e->getMessage());
        return false;
    }
}

/* ────────────────────────────────────────────────────────────
   Fallback : mail() natif PHP
   Fonctionne uniquement si le serveur SMTP local est configuré
   (sendmail / postfix). Sur XAMPP local, les emails n'arrivent
   généralement pas — utilisez PHPMailer + Gmail SMTP.
   ──────────────────────────────────────────────────────────── */
function _envoyerMailNatif($to_email, $to_nom, $sujet, $corps) {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . SMTP_FROM_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $sujetEncode = '=?UTF-8?B?' . base64_encode($sujet) . '?=';

    $ok = @mail($to_email, $sujetEncode, $corps, $headers);

    if (!$ok) {
        error_log('[MVENGINEERING Mailer] mail() natif échoué vers ' . $to_email);
    }

    return $ok;
}

/* ────────────────────────────────────────────────────────────
   Convertir HTML en texte brut (AltBody)
   ──────────────────────────────────────────────────────────── */
function _htmlToText($html) {
    $text = $html;
    $text = str_replace(array('<br>', '<br/>', '<br />'), "\n", $text);
    $text = str_replace(array('<p>', '</p>', '<div>', '</div>'), "\n", $text);
    $text = str_replace(array('<li>'), "- ", $text);
    $text = strip_tags($text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return trim($text);
}

/* ════════════════════════════════════════════════════════════
   TEMPLATES D'EMAILS
   ════════════════════════════════════════════════════════════ */

/* ── Confirmation de réception de candidature ── */
function getEmailConfirmation($data) {
    $nom  = htmlspecialchars($data['nom_complet'],  ENT_QUOTES, 'UTF-8');
    $ecol = htmlspecialchars($data['ecole'],         ENT_QUOTES, 'UTF-8');
    $fil  = htmlspecialchars($data['filiere'],       ENT_QUOTES, 'UTF-8');
    $date = date('d/m/Y', strtotime($data['date_souhaitee']));
    $dur  = htmlspecialchars($data['duree'],         ENT_QUOTES, 'UTF-8');

    $corps = "
      <h2 style='color:#1a56db;margin-top:0'>Demande bien re&ccedil;ue &#10003;</h2>
      <p>Bonjour <strong>" . $nom . "</strong>,</p>
      <p>Nous avons bien re&ccedil;u votre demande de stage et nous vous en remercions.</p>
      <table style='width:100%;border-collapse:collapse;margin:20px 0;font-size:14px'>
        <tr>
          <td style='padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#6b7280;width:35%'>&#201;cole / Universit&eacute;</td>
          <td style='padding:10px 12px;border-bottom:1px solid #e5e7eb'><strong>" . $ecol . "</strong></td>
        </tr>
        <tr>
          <td style='padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#6b7280'>Fili&egrave;re</td>
          <td style='padding:10px 12px;border-bottom:1px solid #e5e7eb'><strong>" . $fil . "</strong></td>
        </tr>
        <tr>
          <td style='padding:10px 12px;border-bottom:1px solid #e5e7eb;color:#6b7280'>Date souhait&eacute;e</td>
          <td style='padding:10px 12px;border-bottom:1px solid #e5e7eb'><strong>" . $date . "</strong></td>
        </tr>
        <tr>
          <td style='padding:10px 12px;color:#6b7280'>Dur&eacute;e</td>
          <td style='padding:10px 12px'><strong>" . $dur . "</strong></td>
        </tr>
      </table>
      <div style='background:#eff6ff;border-left:4px solid #1a56db;padding:14px 16px;border-radius:0 8px 8px 0;margin:20px 0'>
        <p style='margin:0;color:#1e40af'>&#128336; Notre &eacute;quipe &eacute;tudiera votre candidature et vous contactera
        <strong>sous 5 jours ouvr&eacute;s</strong>.</p>
      </div>
      <p style='color:#6b7280;font-size:13px'>Si vous n'avez pas soumis cette demande, ignorez cet email.</p>
    ";
    return emailLayout($corps);
}

/* ── Acceptation du stage ── */
function getEmailAcceptation($demande) {
    $nom  = htmlspecialchars($demande['nom_complet'],    ENT_QUOTES, 'UTF-8');
    $ecol = htmlspecialchars($demande['ecole'],           ENT_QUOTES, 'UTF-8');
    $fil  = htmlspecialchars($demande['filiere'],         ENT_QUOTES, 'UTF-8');
    $date = date('d/m/Y', strtotime($demande['date_souhaitee']));
    $dur  = htmlspecialchars($demande['duree'],           ENT_QUOTES, 'UTF-8');

    $corps = "
      <h2 style='color:#057a55;margin-top:0'>&#127881; F&eacute;licitations &mdash; Stage accept&eacute; !</h2>
      <p>Bonjour <strong>" . $nom . "</strong>,</p>
      <p>Nous avons le plaisir de vous informer que votre demande de stage a &eacute;t&eacute;
         <strong style='color:#057a55'>accept&eacute;e</strong> !</p>

      <div style='background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:20px;margin:20px 0'>
        <table style='width:100%;border-collapse:collapse;font-size:14px'>
          <tr>
            <td style='padding:6px 0;color:#166534;width:40%'>&#127979; &Eacute;cole</td>
            <td style='padding:6px 0'><strong>" . $ecol . "</strong></td>
          </tr>
          <tr>
            <td style='padding:6px 0;color:#166534'>&#127891; Fili&egrave;re</td>
            <td style='padding:6px 0'><strong>" . $fil . "</strong></td>
          </tr>
          <tr>
            <td style='padding:6px 0;color:#166534'>&#128197; D&eacute;but du stage</td>
            <td style='padding:6px 0'><strong>" . $date . "</strong></td>
          </tr>
          <tr>
            <td style='padding:6px 0;color:#166534'>&#9200; Dur&eacute;e</td>
            <td style='padding:6px 0'><strong>" . $dur . "</strong></td>
          </tr>
        </table>
      </div>

      <p>Vous serez contact&eacute;(e) prochainement avec les d&eacute;tails pratiques
         (lieu, horaires, ma&icirc;tre de stage).</p>

      <p><strong>&#128196; Documents &agrave; apporter le premier jour :</strong></p>
      <ul style='line-height:2'>
        <li>Convention de stage sign&eacute;e par votre &eacute;tablissement</li>
        <li>Pi&egrave;ce d'identit&eacute; en cours de validit&eacute;</li>
        <li>CV actualis&eacute;</li>
      </ul>

      <p style='color:#057a55;font-weight:600'>Bienvenue dans l'&eacute;quipe !</p>
    ";
    return emailLayout($corps);
}

/* ── Refus de candidature ── */
function getEmailRefus($demande, $motif) {
    $nom   = htmlspecialchars($demande['nom_complet'], ENT_QUOTES, 'UTF-8');
    $extra = '';
    if (!empty($motif)) {
        $extra = "<div style='background:#fef3c7;border-left:4px solid #d97706;padding:12px 16px;"
               . "border-radius:0 8px 8px 0;margin:16px 0'>"
               . "<p style='margin:0;color:#92400e'><strong>Motif :</strong> "
               . htmlspecialchars($motif, ENT_QUOTES, 'UTF-8') . "</p></div>";
    }

    $corps = "
      <h2 style='color:#374151;margin-top:0'>R&eacute;ponse &agrave; votre candidature</h2>
      <p>Bonjour <strong>" . $nom . "</strong>,</p>
      <p>Nous avons soigneusement &eacute;tudi&eacute; votre demande de stage et vous remercions
         de l'int&eacute;r&ecirc;t port&eacute; &agrave; notre entreprise.</p>
      <p>Apr&egrave;s examen attentif de votre dossier, nous avons le regret de vous informer
         que nous ne pouvons pas donner une suite favorable &agrave; votre candidature
         pour le moment.</p>
      " . $extra . "
      <p>Nous vous encourageons &agrave; repostuler lors d'une prochaine p&eacute;riode de
         recrutement. Nous conservons votre dossier pour d'&eacute;ventuelles
         opportunit&eacute;s futures.</p>
      <p>Nous vous souhaitons plein succ&egrave;s dans vos recherches.</p>
    ";
    return emailLayout($corps);
}

/* ── Wrapper HTML commun à tous les emails ── */
function emailLayout($contenu) {
    $nom   = defined('ENTREPRISE_NOM')     ? ENTREPRISE_NOM     : 'MVENGINEERING';
    $email = defined('ENTREPRISE_EMAIL')   ? ENTREPRISE_EMAIL   : '';
    $tel   = defined('ENTREPRISE_TEL')     ? ENTREPRISE_TEL     : '';
    $annee = date('Y');

    return "<!DOCTYPE html>
<html lang='fr'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1'>
  <title>" . htmlspecialchars($nom) . "</title>
</head>
<body style='margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif'>
<table width='100%' cellpadding='0' cellspacing='0' border='0'
       style='background:#f3f4f6;padding:40px 20px'>
  <tr><td align='center'>
    <table width='600' cellpadding='0' cellspacing='0' border='0'
           style='background:#ffffff;border-radius:12px;overflow:hidden;
                  box-shadow:0 4px 20px rgba(0,0,0,.1);max-width:600px'>

      <!-- En-tête -->
      <tr>
        <td style='background:linear-gradient(135deg,#1a56db,#1e40af);
                   padding:32px 40px;text-align:center'>
          <h1 style='color:#ffffff;margin:0;font-size:24px;font-weight:700;
                     letter-spacing:.5px'>" . htmlspecialchars($nom) . "</h1>
          <p style='color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px'>
            Gestion des stages
          </p>
        </td>
      </tr>

      <!-- Corps -->
      <tr>
        <td style='padding:36px 40px;color:#374151;line-height:1.7;font-size:15px'>
          " . $contenu . "
        </td>
      </tr>

      <!-- Pied de page -->
      <tr>
        <td style='background:#f9fafb;padding:24px 40px;
                   border-top:1px solid #e5e7eb;text-align:center'>
          <p style='margin:0;color:#6b7280;font-size:13px'>
            <strong>" . htmlspecialchars($nom) . "</strong>
            &nbsp;&middot;&nbsp;" . htmlspecialchars($email) . "
            &nbsp;&middot;&nbsp;" . htmlspecialchars($tel) . "
          </p>
          <p style='margin:6px 0 0;color:#9ca3af;font-size:11px'>
            &copy; " . $annee . " " . htmlspecialchars($nom) . " &mdash;
            Cet email a &eacute;t&eacute; envoy&eacute; automatiquement, merci de ne pas y r&eacute;pondre directement.
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>";
}

/* ════════════════════════════════════════════════════════════
   DIAGNOSTIC MAILER — utilisé par test.php
   ════════════════════════════════════════════════════════════ */
function diagnosticMailer() {
    global $_MAILER_READY, $_MAILER_DIR;
    return array(
        'phpmailer_present'  => $_MAILER_READY,
        'phpmailer_dir'      => $_MAILER_DIR,
        'smtp_configure'     => smtpEstConfigure(),
        'smtp_host'          => defined('SMTP_HOST') ? SMTP_HOST : '',
        'smtp_port'          => defined('SMTP_PORT') ? SMTP_PORT : '',
        'smtp_user'          => defined('SMTP_USER') ? SMTP_USER : '',
        'from_email'         => defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '',
        'openssl_available'  => extension_loaded('openssl'),
        'mode'               => $_MAILER_READY && smtpEstConfigure() ? 'PHPMailer SMTP' : 'mail() natif (fallback)',
    );
}
