# MVENGINEERING — Gestion des Stagiaires
## Version compatible XAMPP 3.3.0 (PHP 5.6 / MySQL 5.6)

---

## ⚙️ Compatibilité

| Composant  | Version XAMPP 3.3.0 | Statut |
|------------|---------------------|--------|
| PHP        | 5.6.x               | ✅ Compatible |
| MySQL      | 5.6.x               | ✅ Compatible |
| Apache     | 2.4.x               | ✅ Compatible |
| PHPMailer  | **5.2.28** (pas v6) | ✅ Requis |

> ⚠️ PHPMailer v6+ requiert PHP 7.0. Utilisez **PHPMailer 5.2.28** pour PHP 5.6.

---

## 📁 Structure du projet

```
stagiaires/
├── index.html           ← Accueil vitrine + tableau public
├── demande.html         ← Formulaire de candidature
├── reception.html       ← Gestion des demandes reçues
├── admin.html           ← Dashboard administrateur
├── login.html           ← Connexion admin
├── test.php             ← Diagnostic (supprimer en production)
│
├── css/
│   ├── style.css
│   └── home.css
│
├── js/
│   ├── app.js           ← JavaScript global (API chemin absolu)
│   └── home.js
│
├── php/
│   ├── config.php       ← Configuration BDD, SMTP, constantes
│   ├── submit.php       ← Soumission formulaire
│   ├── action.php       ← Actions admin (stats, accepter, refuser…)
│   ├── auth.php         ← Login / logout / check session
│   ├── mailer.php       ← Envoi emails (PHPMailer 5.x / mail natif)
│   ├── get_cv.php       ← Téléchargement sécurisé CV
│   └── get_demandes_public.php
│
├── sql/
│   └── database.sql     ← Script BDD (MySQL 5.6 compatible)
│
├── uploads/             ← CVs uploadés
│   └── .htaccess
│
└── vendor/              ← PHPMailer 5.2.28 (à installer)
    └── phpmailer/
        ├── class.phpmailer.php
        └── class.smtp.php
```

---

## 🚀 Installation étape par étape

### 1. Préparer XAMPP 3.3.0
- Ouvrir le panneau XAMPP
- Démarrer **Apache** et **MySQL**

### 2. Copier le projet
```
Copier le dossier stagiaires/ dans :
C:\xampp\htdocs\stagiaires\
```

### 3. Créer la base de données
1. Ouvrir : http://localhost/phpmyadmin
2. Cliquer **Importer**
3. Sélectionner `sql/database.sql`
4. Cliquer **Exécuter**

### 4. Configurer php/config.php
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestion_stagiaires');
define('DB_USER', 'root');
define('DB_PASS', '');          // vide par défaut sur XAMPP
define('APP_URL', 'http://localhost/stagiaires');
```

### 5. Installer PHPMailer 5.2.28

> ⚠️ N'installez PAS PHPMailer v6 — elle requiert PHP 7.0+

**Téléchargement :**
https://github.com/PHPMailer/PHPMailer/releases/tag/v5.2.28

**Installation :**
1. Télécharger le ZIP et extraire
2. Copier **uniquement** ces 2 fichiers dans `vendor/phpmailer/` :
   - `class.phpmailer.php`
   - `class.smtp.php`

### 6. Configurer l'envoi d'email (Gmail)
Dans `php/config.php` :
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'votre_email@gmail.com');
define('SMTP_PASS', 'mot_de_passe_application_gmail');
```

> Sans PHPMailer, l'application fonctionne en mode dégradé (mail() natif PHP).
> Les emails peuvent ne pas partir selon la configuration de votre serveur local.

### 7. Vérifier l'installation
Ouvrir : **http://localhost/stagiaires/test.php**

Ce fichier vérifie automatiquement :
- La version PHP
- Les extensions requises
- La connexion MySQL
- Les tables de la BDD
- Le dossier uploads
- L'API JSON

---

## 🔑 Compte administrateur

| Champ | Valeur |
|-------|--------|
| URL   | http://localhost/stagiaires/login.html |
| Email | admin@mvengineering.cm |
| Mot de passe | Admin1234 |

> Changez ce mot de passe après la première connexion.

**Générer un nouveau hash :**
```php
<?php echo password_hash('NouveauMotDePasse', PASSWORD_BCRYPT); ?>
```

---

## ⚠️ Modifications PHP 5.6 vs versions récentes

Cette version a été adaptée pour PHP 5.6 :

| Supprimé (PHP 7+)        | Remplacé par              |
|--------------------------|---------------------------|
| `function f(): void`     | `function f()`            |
| `function f(): bool`     | `function f()`            |
| `string $param`          | `$param` (sans type)      |
| `$a ?? $b`               | `isset($a) ? $a : $b`     |
| `array(...)` PHP 5.4 ✅  | Conservé                  |
| PHPMailer v6             | PHPMailer v5.2.28         |
| `utf8mb4` charset        | `utf8` (MySQL 5.6)        |
| `ENUM` dans SQL          | `VARCHAR` (plus souple)   |

---

## 🔒 Sécurité incluse

- PDO avec requêtes préparées (anti-injection SQL)
- htmlspecialchars() sur toutes les sorties (anti-XSS)
- Vérification type MIME réel pour les uploads CV
- Sessions avec httponly + durée limitée (1h)
- Mots de passe hashés bcrypt (cost 12)
- Pause anti-brute-force (1s sur échec login)
- .htaccess dans uploads/ (bloque l'exécution PHP)

---

## 🐛 Dépannage fréquent

**"Mode démo — serveur PHP non détecté"**
→ Vérifiez que Apache est démarré dans XAMPP
→ Ouvrez http://localhost/stagiaires/test.php pour le diagnostic complet

**Erreur de connexion BDD**
→ Vérifiez que MySQL est démarré
→ Vérifiez DB_USER / DB_PASS dans config.php

**Emails non reçus**
→ Installez PHPMailer 5.2.28 (pas v6)
→ Activez la validation 2 étapes sur Gmail
→ Utilisez un mot de passe d'application Gmail

**Upload CV échoue**
→ Créez le dossier uploads/ à la racine du projet
→ Vérifiez upload_max_filesize dans php.ini (doit être ≥ 5M)
→ Vérifiez post_max_size dans php.ini (doit être ≥ 6M)

**Erreur 500 page blanche**
→ Activez les erreurs PHP dans php.ini :
   `display_errors = On`
   `error_reporting = E_ALL`

---

## 📞 Contact
MVENGINEERING — contact@mvengineering.cm
