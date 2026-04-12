<?php
define('APP_NAME', 'Gestion Locative');
define('APP_VERSION', '1.0');

// Détecte automatiquement l'URL de base à partir de la racine réelle du projet.
$projectRoot = str_replace('\\', '/', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
$documentRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$basePath = '';

if ($documentRoot !== '' && str_starts_with($projectRoot, $documentRoot)) {
    $basePath = substr($projectRoot, strlen($documentRoot));
}

$basePath = rtrim($basePath, '/');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

define('BASE_URL', $scheme . '://' . $host . ($basePath ?: ''));

// Dossier de stockage des quittances PDF
define('PDF_DIR', __DIR__ . '/../quittances/');
define('PDF_URL', BASE_URL . '/quittances/');

// SMTP (à configurer plus tard pour l'envoi de mails)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'ton@email.com');
define('SMTP_PASS', 'ton_mot_de_passe');
define('SMTP_FROM', 'ton@email.com');
define('SMTP_FROM_NAME', APP_NAME);

// WhatsApp CallMeBot (à configurer plus tard)
define('WA_API_KEY', 'ta_cle_callmebot');
define('WA_PHONE', '+22960000000'); // ton numéro admin

// Infos bailleur (apparaissent sur les quittances)
define('BAILLEUR_NOM', 'Nom du Propriétaire');
define('BAILLEUR_ADRESSE', 'Cotonou, Bénin');
define('BAILLEUR_TEL', '+229 00 00 00 00');

if (is_file(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}
