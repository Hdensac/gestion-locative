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
// Détection du schéma : supporte les proxys qui envoient "X-Forwarded-Proto".
$rawHost = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
$host = preg_replace('/:\d+$/', '', $rawHost) ?: '';

// Empêche les hôtes invalides (ex: "login.php") qui cassent les redirections absolues.
if (
    $host === ''
    || (
        $host !== 'localhost'
        && !filter_var($host, FILTER_VALIDATE_IP)
        && !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $host)
    )
) {
    $serverName = trim((string) ($_SERVER['SERVER_NAME'] ?? ''));
    $host = $serverName !== '' ? $serverName : 'localhost';
}

$xfp = strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['HTTP_X_FORWARDED_SCHEME'] ?? '');
$scheme = 'http';
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $scheme = 'https';
} elseif ($xfp === 'https' || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')) {
    $scheme = 'https';
}

// Dossier de stockage des quittances PDF
// Normalise et expose le chemin de base (sans scheme/host) pour générer des liens relatifs.
if ($basePath !== '' && ($basePath[0] ?? '') !== '/') {
    $basePath = '/' . ltrim($basePath, '/');
}
define('BASE_PATH', $basePath ?: '');

// Pour l'application interne, on force une base relative afin d'éviter
// les redirections vers des domaines invalides (ex: //pages/...).
define('BASE_URL', BASE_PATH);

define('PDF_DIR', __DIR__ . '/../quittances/');
define('PDF_URL', BASE_PATH . '/quittances/');

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
