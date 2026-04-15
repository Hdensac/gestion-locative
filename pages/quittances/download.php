<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Debugging for Railway 502/500 errors
if (isset($_GET['debug'])) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

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

// Assurer que le dossier existe
if (!is_dir(PDF_DIR)) {
    @mkdir(PDF_DIR, 0777, true);
}

// PDF_DIR est défini dans config.php comme __DIR__ . '/../quittances/'
$base = realpath(PDF_DIR);
if ($base === false) {
    // Si realpath échoue (dossier absent), on essaie avec PDF_DIR directement
    $base = rtrim(PDF_DIR, DIRECTORY_SEPARATOR . '/');
    if (!is_dir($base)) {
        http_response_code(500);
        exit('Erreur de configuration : répertoire de quittances introuvable ou inaccessible : ' . PDF_DIR);
    }
}

$basename = basename((string) $row['pdf_path']);
if ($basename === '' || str_contains($basename, '..')) {
    http_response_code(400);
    exit('Nom de fichier invalide.');
}

$full = $base . DIRECTORY_SEPARATOR . $basename;
if (!is_file($full)) {
    // Tenter de régénérer le fichier s'il est absent
    require_once __DIR__ . '/../../includes/quittance_service.php';
    try {
        if (quittance_regenerer_pdf($db, $id)) {
            // Re-vérifier après régénération
            if (is_file($full)) {
                // Succès de la régénération
            } else {
                http_response_code(404);
                exit('Fichier absent sur le serveur et la régénération a échoué (' . htmlspecialchars($full) . ').');
            }
        } else {
            http_response_code(404);
            exit('Fichier absent sur le serveur et impossible de le régénérer (' . htmlspecialchars($full) . ').');
        }
    } catch (Exception $e) {
        http_response_code(404);
        exit('Fichier absent sur le serveur et erreur lors de la régénération : ' . htmlspecialchars($e->getMessage()));
    }
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
$monthCol = Database::paiementMonthColumn();
$locataireInfo = $db->prepare("
    SELECT l.nom_complet, p.$monthCol AS date_concerne
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
    
    // Extraction du mois et de l'année depuis date_concerne
    $dateVal = (string)$locataire['date_concerne'];
    $ts = strtotime($dateVal);
    if ($ts === false) {
        $mois = (int)date('m');
        $annee = (int)date('Y');
    } else {
        $mois = (int)date('m', $ts);
        $annee = (int)date('Y', $ts);
    }

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
