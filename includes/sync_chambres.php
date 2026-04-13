<?php
declare(strict_types=1);

/** Aligne chambres.statut sur la présence d'un locataire actif (CDC: automatique). */
function sync_chambre_statuts(PDO $db): void
{
    try {
        $db->exec(
            "UPDATE chambres c SET c.statut = IF(
                EXISTS(SELECT 1 FROM locataires l WHERE l.chambre_id = c.id AND l.actif = 1),
                'occupée',
                'libre'
            )"
        );
    } catch (PDOException $e) {
        // Schéma pas encore prêt: la vérification globale de Database
        // affichera un message d'initialisation plus explicite.
    }
}