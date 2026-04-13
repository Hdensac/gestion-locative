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

$base = realpath(PDF_DIR);
if ($base === false) {
    http_response_code(500);
    exit('Configuration PDF incorrecte.');
}

$basename = basename((string) $row['pdf_path']);
if ($basename === '' || str_contains($basename, '..')) {
    http_response_code(400);
    exit('Nom de fichier invalide.');
}

$full = realpath($base . DIRECTORY_SEPARATOR . $basename);
if ($full === false || !str_starts_with($full, $base) || !is_file($full)) {
    http_response_code(404);
    exit('Fichier absent sur le serveur.');
}

$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', (string) $row['numero_quittance']) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . (string) filesize($full));
readfile($full);
exit;
