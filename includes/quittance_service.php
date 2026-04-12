<?php
declare(strict_types=1);

require_once __DIR__ . '/QuittancePdf.php';
require_once __DIR__ . '/paiement_modes.php';

/**
 * Crée la ligne quittance + fichier PDF pour un paiement existant.
 *
 * @throws RuntimeException en cas d’échec (rollback transaction appelant recommandé)
 */
function quittance_creer_pour_paiement(PDO $db, int $paiementId): int
{
    if (!class_exists('FPDF')) {
        throw new RuntimeException('Librairie FPDF absente : exécutez « composer install » à la racine du projet.');
    }

    $st = $db->prepare('
        SELECT p.id AS paiement_id, p.montant, p.mois_concerne, p.date_paiement, p.mode_paiement, p.note,
               l.nom_complet AS loc_nom, l.telephone AS loc_tel, l.email AS loc_email,
               c.numero AS chambre_num, m.nom AS maison_nom, m.adresse AS maison_adresse
        FROM paiements p
        JOIN locataires l ON l.id = p.locataire_id
        JOIN chambres c ON c.id = l.chambre_id
        JOIN maisons m ON m.id = c.maison_id
        WHERE p.id = ?
    ');
    $st->execute([$paiementId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new RuntimeException('Paiement introuvable.');
    }

    $dup = $db->prepare('SELECT id FROM quittances WHERE paiement_id = ?');
    $dup->execute([$paiementId]);
    if ($dup->fetch()) {
        throw new RuntimeException('Une quittance existe déjà pour ce paiement.');
    }

    $year = date('Y');
    $like = 'QUIT-' . $year . '-%';
    $mx = $db->prepare('
        SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(numero_quittance, \'-\', -1) AS UNSIGNED)), 0) AS n
        FROM quittances
        WHERE numero_quittance LIKE ?
    ');
    $mx->execute([$like]);
    $next = (int) $mx->fetchColumn() + 1;
    if ($next > 9999) {
        throw new RuntimeException('Numérotation annuelle dépassée (9999).');
    }
    $numero = sprintf('QUIT-%s-%04d', $year, $next);

    $dateEmission = date('Y-m-d');
    $filename = $numero . '.pdf';
    $absPath = rtrim(PDF_DIR, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . $filename;

    if (!is_dir(PDF_DIR) || !is_writable(PDF_DIR)) {
        throw new RuntimeException('Dossier quittances non accessible en écriture.');
    }

    $moisTs = strtotime($row['mois_concerne'] . ' 12:00:00');
    $moisFr = ['01' => 'janvier', '02' => 'février', '03' => 'mars', '04' => 'avril', '05' => 'mai', '06' => 'juin', '07' => 'juillet', '08' => 'août', '09' => 'septembre', '10' => 'octobre', '11' => 'novembre', '12' => 'décembre'];
    $moisLibelle = ($moisFr[date('m', $moisTs)] ?? date('m', $moisTs)) . ' ' . date('Y', $moisTs);

    $montant = (float) $row['montant'];
    $montantFr = number_format($montant, 0, ',', ' ') . ' FCFA';

    $bailleurBloc = BAILLEUR_NOM . "\n" . BAILLEUR_ADRESSE . "\n" . BAILLEUR_TEL;
    $locBloc = $row['loc_nom'];
    if (!empty($row['loc_tel'])) {
        $locBloc .= "\nTél. " . $row['loc_tel'];
    }
    if (!empty($row['loc_email'])) {
        $locBloc .= "\n" . $row['loc_email'];
    }
    $logBloc = $row['maison_nom'] . "\n" . $row['maison_adresse'] . "\nChambre " . $row['chambre_num'];

    $data = [
        'numero_quittance'   => $numero,
        'date_emission_fr'   => date('d/m/Y', strtotime($dateEmission)),
        'bailleur_bloc'      => $bailleurBloc,
        'locataire_bloc'     => $locBloc,
        'logement_bloc'      => $logBloc,
        'mois_libelle'       => ucfirst($moisLibelle),
        'montant_fr'         => $montantFr,
        'mode_libelle'       => paiement_mode_label((string) $row['mode_paiement']),
        'date_paiement_fr'   => date('d/m/Y', strtotime($row['date_paiement'])),
        'note'               => (string) ($row['note'] ?? ''),
    ];

    $pdf = new QuittancePdf();
    $pdf->SetTitle('Quittance ' . $numero);
    $pdf->build($data);
    $pdf->Output('F', $absPath);

    if (!is_file($absPath) || filesize($absPath) < 100) {
        throw new RuntimeException('Échec de l’écriture du fichier PDF.');
    }

    $ins = $db->prepare('
        INSERT INTO quittances (paiement_id, numero_quittance, date_emission, pdf_path, envoye_mail, envoye_whatsapp)
        VALUES (?, ?, ?, ?, 0, 0)
    ');
    $ins->execute([$paiementId, $numero, $dateEmission, $filename]);

    return (int) $db->lastInsertId();
}
