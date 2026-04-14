<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    http_response_code(404);
    exit('Quittance introuvable.');
}

require_once __DIR__ . '/../../config/database.php';
$db = Database::getInstance();
$quittancePathColumn = Database::quittancePathColumn();

$st = $db->prepare("
    SELECT q.$quittancePathColumn AS pdf_path, q.numero_quittance
    FROM quittances q
    WHERE q.id = ? AND q.$quittancePathColumn IS NOT NULL AND q.$quittancePathColumn != ''
");
$st->execute([$id]);
$row = $st->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    exit('Fichier non disponible.');
}

// Vérification que le répertoire PDF_DIR existe
if (!is_dir(PDF_DIR)) {
    http_response_code(500);
    exit('Répertoire de quittances non configuré ou inaccessible.');
}

$base = realpath(PDF_DIR);
if ($base === false) {
    http_response_code(500);
    exit('Erreur de configuration : impossible d\'accéder au répertoire de quittances.');
}

$basename = basename((string) $row['pdf_path']);
if ($basename === '' || str_contains($basename, '..')) {
    http_response_code(400);
    exit('Nom de fichier invalide.');
}

$full = realpath($base . DIRECTORY_SEPARATOR . $basename);
if ($full === false || !str_starts_with($full, $base) || !is_file($full)) {
    http_response_code(404);
    exit('Fichier absent sur le serveur ou chemin invalide.');
}

// Vérification de la taille du fichier pour éviter les timeouts
$fileSize = filesize($full);
if ($fileSize === false) {
    http_response_code(500);
    exit('Erreur lors de la lecture du fichier.');
}

// Limite de taille pour éviter les timeouts (10MB)
if ($fileSize > 10 * 1024 * 1024) {
    http_response_code(413);
    exit('Fichier trop volumineux pour le téléchargement.');
}

// Récupérer les informations du locataire pour un nom de fichier plus logique
$paiementMonthColumn = Database::paiementMonthColumn();
$locataireInfo = $db->prepare("
    SELECT l.nom_complet, p.$paiementMonthColumn AS mois_concerne
    FROM quittances q
    JOIN paiements p ON p.id = q.paiement_id
    JOIN locataires l ON l.id = p.locataire_id
    WHERE q.id = ?
");
$locataireInfo->execute([$id]);
$locataire = $locataireInfo->fetch(PDO::FETCH_ASSOC);

if ($locataire) {
    // Générer un nom de fichier plus logique : nom_locataire_mois_annee.pdf
    $nomPropre = preg_replace('/[^a-zA-Z0-9]/', '_', (string) $locataire['nom_complet']);
    // Extraire le mois et l'année de la colonne mois_concerne (format YYYY-MM-DD ou YYYY-MM)
    $dateParts = explode('-', (string) $locataire['mois_concerne']);
    $annee = isset($dateParts[0]) ? (int)$dateParts[0] : (int)date('Y');
    $mois = isset($dateParts[1]) ? (int)$dateParts[1] : (int)date('m');
    $moisNom = date('F', mktime(0, 0, 0, $mois, 1));
    $filename = $nomPropre . '_' . $moisNom . '_' . $annee . '.pdf';
} else {
    // Fallback au numéro de quittance si on ne trouve pas le locataire
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $row['numero_quittance']) . '.pdf';
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . (string) filesize($full));
readfile($full);
exit;
