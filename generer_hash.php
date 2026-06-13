<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Réinitialisation admin — MVENGINEERING</title>
  <style>
    body { font-family: Arial, sans-serif; max-width: 680px; margin: 40px auto; padding: 0 20px; background: #f3f4f6; }
    h1   { color: #1a56db; }
    h2   { color: #374151; border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; margin-top: 28px; }
    .ok  { background: #d1fae5; border-left: 4px solid #059669; padding: 12px 16px; border-radius: 4px; margin: 8px 0; }
    .err { background: #fee2e2; border-left: 4px solid #dc2626; padding: 12px 16px; border-radius: 4px; margin: 8px 0; }
    .inf { background: #dbeafe; border-left: 4px solid #1a56db; padding: 12px 16px; border-radius: 4px; margin: 8px 0; }
    .warn{ background: #fef3c7; border-left: 4px solid #d97706; padding: 12px 16px; border-radius: 4px; margin: 8px 0; }
    code { background: #1e293b; color: #e2e8f0; padding: 3px 8px; border-radius: 4px; font-size: 14px; word-break: break-all; }
    input[type=text], input[type=password] {
      width: 100%; padding: 10px; border: 1.5px solid #d1d5db;
      border-radius: 6px; font-size: 15px; box-sizing: border-box; margin-top: 6px;
    }
    button {
      background: #1a56db; color: #fff; border: none; padding: 12px 28px;
      border-radius: 6px; font-size: 15px; font-weight: 700; cursor: pointer; margin-top: 10px;
    }
    button:hover { background: #1e3a8a; }
    .box { background: #fff; border-radius: 10px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,.08); margin-bottom: 20px; }
    .hash-display { font-size: 12px; word-break: break-all; margin-top: 8px; }
  </style>
</head>
<body>

<h1>&#128273; Utilitaire admin — MVENGINEERING</h1>
<div class="warn">
  &#9888; Ce fichier est un outil de configuration. <strong>Supprimez-le après utilisation.</strong>
</div>

<?php
require_once __DIR__ . '/php/config.php';

// ─── ÉTAPE 1 : Vérifier la connexion et l'état de la table ───
?>

<h2>1. État de la base de données</h2>
<?php
try {
    $db = getDB();
    echo '<div class="ok">&#10003; MySQL connecté — base <strong>' . DB_NAME . '</strong></div>';

    // Vérifier la table utilisateurs
    $stmt = $db->query("SELECT id, nom, email, role, actif, LENGTH(mot_de_passe) as hash_len FROM utilisateurs");
    $users = $stmt->fetchAll();

    if (count($users) === 0) {
        echo '<div class="err">&#10007; La table <strong>utilisateurs</strong> est VIDE.<br>
        Importez d\'abord <code>sql/database.sql</code> dans phpMyAdmin.</div>';
    } else {
        echo '<div class="ok">&#10003; ' . count($users) . ' utilisateur(s) trouvé(s)</div>';
        echo '<table style="width:100%;border-collapse:collapse;margin-top:10px;font-size:13px">';
        echo '<tr style="background:#f3f4f6"><th style="padding:8px;text-align:left">ID</th><th>Nom</th><th>Email</th><th>Rôle</th><th>Actif</th><th>Hash len</th><th>Hash OK?</th></tr>';
        foreach ($users as $u) {
            $hash_ok = ($u['hash_len'] == 60) ? '✅ 60' : '❌ ' . $u['hash_len'];
            echo '<tr style="border-bottom:1px solid #e5e7eb">';
            echo '<td style="padding:8px">' . $u['id'] . '</td>';
            echo '<td style="padding:8px">' . htmlspecialchars($u['nom']) . '</td>';
            echo '<td style="padding:8px">' . htmlspecialchars($u['email']) . '</td>';
            echo '<td style="padding:8px">' . htmlspecialchars($u['role']) . '</td>';
            echo '<td style="padding:8px">' . ($u['actif'] ? '✅' : '❌') . '</td>';
            echo '<td style="padding:8px">' . $hash_ok . '</td>';
            // Vérifier password_verify avec Admin1234
            $stmt2 = $db->prepare("SELECT mot_de_passe FROM utilisateurs WHERE id = ?");
            $stmt2->execute(array($u['id']));
            $row = $stmt2->fetch();
            $verify = password_verify('Admin1234', $row['mot_de_passe']) ? '✅ Admin1234' : '❌ Admin1234';
            echo '<td style="padding:8px">' . $verify . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
} catch (Exception $e) {
    echo '<div class="err">&#10007; Erreur MySQL : ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<div class="inf">Vérifiez DB_USER / DB_PASS dans php/config.php</div>';
}
?>

<h2>2. Générer un nouveau hash de mot de passe</h2>
<div class="box">
<?php
if (isset($_POST['gen_password']) && !empty($_POST['gen_password'])) {
    $pwd  = $_POST['gen_password'];
    $hash = password_hash($pwd, PASSWORD_BCRYPT, array('cost' => 10));
    echo '<div class="ok">&#10003; Hash généré pour : <strong>' . htmlspecialchars($pwd) . '</strong></div>';
    echo '<p style="margin:12px 0 4px"><strong>Votre hash (copiez-le) :</strong></p>';
    echo '<code class="hash-display">' . htmlspecialchars($hash) . '</code>';
    echo '<p style="font-size:13px;color:#6b7280;margin-top:8px">Longueur : ' . strlen($hash) . ' caractères</p>';
    
    // Vérification immédiate
    if (password_verify($pwd, $hash)) {
        echo '<div class="ok" style="margin-top:8px">&#10003; Vérification : le hash est valide !</div>';
    }
}
?>
  <form method="POST">
    <label style="font-weight:600">Entrez un mot de passe pour générer son hash bcrypt :</label>
    <input type="text" name="gen_password" placeholder="Ex: MonNouveauMotDePasse123" value="<?php echo isset($_POST['gen_password']) ? htmlspecialchars($_POST['gen_password']) : ''; ?>">
    <button type="submit">&#128273; Générer le hash</button>
  </form>
</div>

<h2>3. Réinitialiser le mot de passe administrateur</h2>
<div class="box">
<?php
$reset_msg = '';
if (isset($_POST['reset_email']) && isset($_POST['reset_password'])) {
    $email   = trim($_POST['reset_email']);
    $new_pwd = $_POST['reset_password'];
    
    if (empty($email) || empty($new_pwd)) {
        $reset_msg = '<div class="err">Email et mot de passe requis.</div>';
    } elseif (strlen($new_pwd) < 6) {
        $reset_msg = '<div class="err">Le mot de passe doit faire au moins 6 caractères.</div>';
    } else {
        try {
            $db   = getDB();
            $hash = password_hash($new_pwd, PASSWORD_BCRYPT, array('cost' => 10));
            
            // Vérifier si l'utilisateur existe
            $check = $db->prepare("SELECT id FROM utilisateurs WHERE email = ?");
            $check->execute(array($email));
            $user = $check->fetch();
            
            if ($user) {
                // Mettre à jour
                $db->prepare("UPDATE utilisateurs SET mot_de_passe = ?, actif = 1 WHERE email = ?")
                   ->execute(array($hash, $email));
                $reset_msg = '<div class="ok">&#10003; Mot de passe mis à jour pour <strong>' . htmlspecialchars($email) . '</strong> !<br>
                              Nouveau mot de passe : <strong>' . htmlspecialchars($new_pwd) . '</strong><br>
                              <a href="login.html" style="color:#059669">&#8594; Aller à la page de connexion</a></div>';
            } else {
                // Créer le compte admin
                $db->prepare("INSERT INTO utilisateurs (nom, email, mot_de_passe, role, actif) VALUES (?, ?, ?, 'super_admin', 1)")
                   ->execute(array('Administrateur', $email, $hash));
                $reset_msg = '<div class="ok">&#10003; Compte admin créé : <strong>' . htmlspecialchars($email) . '</strong> / <strong>' . htmlspecialchars($new_pwd) . '</strong><br>
                              <a href="login.html" style="color:#059669">&#8594; Aller à la page de connexion</a></div>';
            }
        } catch (Exception $e) {
            $reset_msg = '<div class="err">&#10007; Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}
echo $reset_msg;
?>
  <form method="POST">
    <label style="font-weight:600">Email administrateur :</label>
    <input type="text" name="reset_email" placeholder="admin@mvengineering.cm" value="admin@mvengineering.cm">
    <label style="font-weight:600;margin-top:14px;display:block">Nouveau mot de passe :</label>
    <input type="text" name="reset_password" placeholder="Minimum 6 caractères" value="Admin1234">
    <button type="submit" style="background:#059669">&#128274; Réinitialiser le mot de passe</button>
  </form>
</div>

<h2>4. Test de session PHP</h2>
<?php
// Tester que la session fonctionne
$_SESSION['test_session'] = 'ok_' . time();
if (isset($_SESSION['test_session'])) {
    echo '<div class="ok">&#10003; Sessions PHP fonctionnelles — ID : <code>' . session_id() . '</code></div>';
    echo '<div class="inf">Nom de session : <code>' . session_name() . '</code><br>';
    echo 'Chemin cookie : <code>' . ini_get('session.cookie_path') . '</code></div>';
} else {
    echo '<div class="err">&#10007; Sessions PHP non fonctionnelles — vérifiez php.ini</div>';
}

// Tester password_hash
$test_hash = password_hash('test', PASSWORD_BCRYPT);
if ($test_hash && strlen($test_hash) === 60) {
    echo '<div class="ok">&#10003; password_hash() fonctionne correctement (longueur : 60)</div>';
} else {
    echo '<div class="err">&#10007; password_hash() retourne un résultat inattendu</div>';
}
?>

<hr style="margin-top:32px">
<p style="color:#6b7280;font-size:13px">
  <strong>&#9888; Supprimez ce fichier après configuration : <code>generer_hash.php</code></strong><br><br>
  <a href="index.html">&#8592; Accueil</a> &nbsp;&middot;&nbsp;
  <a href="login.html">Se connecter</a> &nbsp;&middot;&nbsp;
  <a href="test.php">Diagnostic complet</a>
</p>
</body>
</html>
