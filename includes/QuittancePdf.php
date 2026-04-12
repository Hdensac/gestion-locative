<?php
declare(strict_types=1);

/**
 * PDF quittance loyer (FPDF) — texte converti en ISO-8859-1 pour les polices core.
 */
class QuittancePdf extends FPDF
{
    /** @param array<string, string> $data */
    public function build(array $data): void
    {
        $this->SetMargins(18, 18, 18);
        $this->AddPage();
        $this->SetFont('Helvetica', 'B', 14);
        $this->Cell(0, 10, self::t('QUITTANCE DE LOYER'), 0, 1, 'C');
        $this->Ln(4);

        $this->SetFont('Helvetica', '', 10);
        $this->SetFillColor(240, 244, 250);
        $this->Cell(0, 8, self::t('N° ') . self::t($data['numero_quittance']), 0, 1, 'L', true);
        $this->Cell(0, 6, self::t('Date d\'émission : ') . self::t($data['date_emission_fr']), 0, 1, 'L');
        $this->Ln(6);

        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(0, 6, self::t('Bailleur'), 0, 1);
        $this->SetFont('Helvetica', '', 10);
        $this->MultiCell(0, 5, self::t($data['bailleur_bloc']), 0, 'L');
        $this->Ln(4);

        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(0, 6, self::t('Locataire'), 0, 1);
        $this->SetFont('Helvetica', '', 10);
        $this->MultiCell(0, 5, self::t($data['locataire_bloc']), 0, 'L');
        $this->Ln(4);

        $this->SetFont('Helvetica', 'B', 10);
        $this->Cell(0, 6, self::t('Logement'), 0, 1);
        $this->SetFont('Helvetica', '', 10);
        $this->MultiCell(0, 5, self::t($data['logement_bloc']), 0, 'L');
        $this->Ln(8);

        $this->SetFont('Helvetica', 'B', 11);
        $this->Cell(90, 8, self::t('Mois concerné'), 1, 0, 'L');
        $this->Cell(0, 8, self::t($data['mois_libelle']), 1, 1, 'L');
        $this->SetFont('Helvetica', 'B', 11);
        $this->Cell(90, 8, self::t('Montant payé'), 1, 0, 'L');
        $this->SetTextColor(0, 110, 70);
        $this->Cell(0, 8, self::t($data['montant_fr']), 1, 1, 'L');
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Helvetica', '', 10);
        $this->Cell(90, 8, self::t('Mode de paiement'), 1, 0, 'L');
        $this->Cell(0, 8, self::t($data['mode_libelle']), 1, 1, 'L');
        $this->Cell(90, 8, self::t('Date du paiement'), 1, 0, 'L');
        $this->Cell(0, 8, self::t($data['date_paiement_fr']), 1, 1, 'L');

        if (($data['note'] ?? '') !== '') {
            $this->Ln(6);
            $this->SetFont('Helvetica', 'I', 9);
            $this->MultiCell(0, 4, self::t('Note : ' . $data['note']), 0, 'L');
        }

        $this->Ln(14);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(80, 80, 80);
        $this->MultiCell(0, 4, self::t(
            'Document généré par ' . (defined('APP_NAME') ? APP_NAME : 'Gestion locative')
            . '. Conservez ce reçu comme preuve de paiement du loyer indiqué.'
        ), 0, 'C');
    }

    private static function t(string $utf8): string
    {
        if ($utf8 === '') {
            return '';
        }
        $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $utf8);
        return $out !== false ? $out : $utf8;
    }
}
