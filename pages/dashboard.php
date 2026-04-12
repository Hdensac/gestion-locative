<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$db = Database::getInstance();
require_once __DIR__ . '/../includes/sync_chambres.php';
sync_chambre_statuts($db);

// ── Stats globales ────────────────────────────────────────────
$stats = $db->query("
    SELECT
        (SELECT COUNT(*) FROM maisons)                          AS nb_maisons,
        (SELECT COUNT(*) FROM chambres)                         AS nb_chambres,
        (SELECT COUNT(*) FROM chambres WHERE statut='libre')    AS nb_libres,
        (SELECT COUNT(*) FROM chambres WHERE statut='occupée')  AS nb_occupees,
        (SELECT COUNT(*) FROM locataires WHERE actif=1)         AS nb_locataires,
        (SELECT COALESCE(SUM(loyer_mensuel),0) FROM locataires WHERE actif=1) AS loyer_total
")->fetch();

// ── Paiements du mois courant ─────────────────────────────────
$mois_courant = date('Y-m-01');
$nb_payes = $db->prepare("
    SELECT COUNT(*) FROM paiements WHERE mois_concerne = ?
");
$nb_payes->execute([$mois_courant]);
$nb_payes = (int) $nb_payes->fetchColumn();

// ── Locataires en retard ce mois ─────────────────────────────
$retards = $db->prepare("
    SELECT l.id, m.nom AS maison, c.numero AS chambre,
           l.nom_complet, l.telephone, l.loyer_mensuel
    FROM locataires l
    JOIN chambres c ON c.id = l.chambre_id
    JOIN maisons  m ON m.id = c.maison_id
    WHERE l.actif = 1
      AND l.id NOT IN (
          SELECT locataire_id FROM paiements WHERE mois_concerne = ?
      )
    ORDER BY m.nom, c.numero
");
$retards->execute([$mois_courant]);
$retards = $retards->fetchAll();

// ── Derniers paiements ────────────────────────────────────────
$derniers = $db->query("
    SELECT p.date_paiement, p.montant, p.mode_paiement,
           l.nom_complet, m.nom AS maison, c.numero AS chambre,
           q.id AS quittance_id, q.numero_quittance, q.pdf_path,
           DATE_FORMAT(p.mois_concerne, '%M %Y') AS mois
    FROM paiements p
    JOIN locataires l  ON l.id = p.locataire_id
    JOIN chambres c    ON c.id = l.chambre_id
    JOIN maisons m     ON m.id = c.maison_id
    LEFT JOIN quittances q ON q.paiement_id = p.id
    ORDER BY p.created_at DESC
    LIMIT 8
")->fetchAll();

$page_title = 'Tableau de bord';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- ── Titre ──────────────────────────────────────────────── -->
<div class="flex items-center justify-between mb-8">
    <div>
        <h1 class="text-2xl font-semibold text-gray-800">Tableau de bord</h1>
        <p class="text-sm text-gray-500 mt-0.5"><?= strftime('%B %Y') ?> — <?= date('d/m/Y') ?></p>
    </div>
    <a href="<?= BASE_URL ?>/pages/paiements/add.php"
       class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Enregistrer un paiement
    </a>
</div>

<!-- ── Cartes stats ───────────────────────────────────────── -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">

    <?php
    $cards = [
        ['Maisons',        $stats['nb_maisons'],    'text-blue-600',  'bg-blue-50',  'M3 9.75L12 3l9 6.75V21a.75.75 0 01-.75.75H15v-6H9v6H3.75A.75.75 0 013 21V9.75z'],
        ['Chambres',       $stats['nb_chambres'],   'text-purple-600','bg-purple-50','M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1'],
        ['Locataires actifs', $stats['nb_locataires'], 'text-teal-600','bg-teal-50','M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0'],
        ['Payés ce mois',  $nb_payes,               'text-green-600', 'bg-green-50', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0'],
    ];
    foreach ($cards as [$label, $val, $tc, $bg, $icon]):
    ?>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
        <div class="<?= $bg ?> rounded-lg p-2.5">
            <svg class="w-6 h-6 <?= $tc ?>" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/>
            </svg>
        </div>
        <div>
            <div class="text-2xl font-semibold text-gray-800"><?= $val ?></div>
            <div class="text-xs text-gray-500"><?= $label ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Ligne : Chambres + loyer mensuel ───────────────────── -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">

    <!-- Taux d'occupation -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h2 class="text-sm font-medium text-gray-600 mb-3">Occupation des chambres</h2>
        <?php
        $total = max(1, $stats['nb_chambres']);
        $pct   = round($stats['nb_occupees'] / $total * 100);
        ?>
        <div class="flex items-end gap-3 mb-2">
            <span class="text-3xl font-semibold text-gray-800"><?= $pct ?>%</span>
            <span class="text-sm text-gray-400 mb-1">occupées</span>
        </div>
        <div class="w-full bg-gray-100 rounded-full h-2 mb-3">
            <div class="bg-teal-500 h-2 rounded-full" style="width:<?= $pct ?>%"></div>
        </div>
        <div class="flex justify-between text-xs text-gray-400">
            <span><?= $stats['nb_occupees'] ?> occupée(s)</span>
            <span><?= $stats['nb_libres'] ?> libre(s)</span>
        </div>
    </div>

    <!-- Loyer mensuel total -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h2 class="text-sm font-medium text-gray-600 mb-3">Loyer mensuel total</h2>
        <div class="text-3xl font-semibold text-gray-800">
            <?= number_format($stats['loyer_total'], 0, ',', ' ') ?>
            <span class="text-base font-normal text-gray-400">FCFA</span>
        </div>
        <p class="text-xs text-gray-400 mt-2">Somme des loyers des locataires actifs</p>
    </div>

    <!-- Retards ce mois -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <h2 class="text-sm font-medium text-gray-600 mb-3">Retards ce mois</h2>
        <div class="flex items-end gap-3 mb-2">
            <span class="text-3xl font-semibold <?= count($retards) > 0 ? 'text-red-600' : 'text-green-600' ?>">
                <?= count($retards) ?>
            </span>
            <span class="text-sm text-gray-400 mb-1">locataire(s)</span>
        </div>
        <?php if (count($retards) === 0): ?>
        <p class="text-xs text-green-600 font-medium">Tout le monde a payé ce mois !</p>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/pages/paiements/retards.php" class="text-xs text-red-500 hover:underline">
            Voir la liste →
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ── Bas de page : retards + derniers paiements ─────────── -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    <!-- Locataires en retard -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Locataires en retard ce mois</h2>
            <?php if (count($retards) > 0): ?>
            <span class="bg-red-100 text-red-700 text-xs px-2 py-0.5 rounded-full font-medium">
                <?= count($retards) ?> en retard
            </span>
            <?php endif; ?>
        </div>

        <?php if (empty($retards)): ?>
        <div class="px-5 py-8 text-center text-sm text-gray-400">
            Aucun retard ce mois — Bravo !
        </div>
        <?php else: ?>
        <ul class="divide-y divide-gray-50">
            <?php foreach ($retards as $r): ?>
            <li class="flex items-center justify-between px-5 py-3">
                <div>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($r['nom_complet']) ?></p>
                    <p class="text-xs text-gray-400"><?= htmlspecialchars($r['maison']) ?> — Chambre <?= htmlspecialchars($r['chambre']) ?></p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-red-600">
                        <?= number_format($r['loyer_mensuel'], 0, ',', ' ') ?> FCFA
                    </p>
                    <a href="<?= BASE_URL ?>/pages/paiements/add.php?locataire=<?= (int) ($r['id'] ?? 0) ?>"
                       class="text-xs text-primary hover:underline">Enregistrer →</a>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <!-- Derniers paiements -->
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700">Derniers paiements</h2>
            <a href="<?= BASE_URL ?>/pages/paiements/index.php" class="text-xs text-primary hover:underline">Tout voir →</a>
        </div>

        <?php if (empty($derniers)): ?>
        <div class="px-5 py-8 text-center text-sm text-gray-400">
            Aucun paiement enregistré pour l'instant.
        </div>
        <?php else: ?>
        <ul class="divide-y divide-gray-50">
            <?php foreach ($derniers as $p): ?>
            <li class="flex items-center justify-between px-5 py-3">
                <div>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($p['nom_complet']) ?></p>
                    <p class="text-xs text-gray-400">
                        <?= htmlspecialchars($p['maison']) ?> · Ch.<?= htmlspecialchars($p['chambre']) ?>
                        · <?= htmlspecialchars($p['mois']) ?>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-green-600">
                        <?= number_format($p['montant'], 0, ',', ' ') ?> FCFA
                    </p>
                    <p class="text-xs text-gray-400"><?= date('d/m/Y', strtotime($p['date_paiement'])) ?></p>
                    <?php if (!empty($p['quittance_id']) && !empty($p['pdf_path'])): ?>
                    <a href="<?= BASE_URL ?>/pages/quittances/download.php?id=<?= (int) $p['quittance_id'] ?>"
                       class="text-xs text-primary hover:underline">PDF</a>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
