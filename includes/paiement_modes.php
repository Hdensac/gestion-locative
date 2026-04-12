<?php
declare(strict_types=1);

/** Valeurs ENUM `paiements.mode_paiement` (base existante) — clés = valeur SQL exacte. */
function paiement_modes(): array
{
    return [
        'espèces'       => 'Espèces',
        'mobile money'  => 'Mobile money',
        'virement'      => 'Virement',
        'chèque'        => 'Chèque',
        'autre'         => 'Autre',
    ];
}

function paiement_mode_label(string $key): string
{
    $m = paiement_modes();
    return $m[$key] ?? $key;
}
