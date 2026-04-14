<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/paiement_modes.php';

$db = Database::getInstance();
$quittancePathColumn = Database::quittancePathColumn();
$paiementMonthColumn = Database::paiementMonthColumn();

$rows = $db->query("
    SELECT p.id, p.montant, p.$paiementMonthColumn AS mois_concerne, p.date_paiement, p.mode_paiement, p.note, p.created_at,
           l.id AS locataire_id, l.nom_complet,
           m.nom AS maison, c.numero AS chambre,
           q.id AS quittance_id, q.numero_quittance, q.$quittancePathColumn AS pdf_path
    FROM paiements p
    JOIN locataires l ON l.id = p.locataire_id
    JOIN chambres c ON c.id = l.chambre_id
    JOIN maisons m ON m.id = c.maison_id
    LEFT JOIN quittances q ON q.paiement_id = p.id
    ORDER BY p.created_at DESC
    LIMIT 300
")->fetchAll();

$page_title = 'Paiements';
require_once __DIR__ . '/../../includes/header.php';

$flash = null;
if (!empty($_GET['saved'])) {
    $flash = ['ok', 'Paiement et quittance PDF enregistrés.'];
}
?>

<?php if ($flash): ?>
<div class="mb-4 rounded-lg bg-green-50 text-green-800 text-sm px-4 py-3 border border-green-100">
    <?= htmlspecialchars($flash[1], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-semibold text-gray-800">Paiements</h1>
        <p class="text-sm text-gray-500 mt-1">Encaissement <strong class="text-gray-700">hors application</strong> : chaque saisie génère une <strong class="text-gray-700">quittance PDF</strong> (téléchargeable ci-dessous).</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="<?= BASE_URL ?>/pages/paiements/retards.php"
           class="inline-flex items-center px-4 py-2 rounded-lg border border-red-200 text-red-800 text-sm font-medium hover:bg-red-50 transition">
            Retards ce mois
        </a>
        <a href="<?= BASE_URL ?>/pages/paiements/add.php"
           class="inline-flex items-center px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">
            Enregistrer un paiement
        </a>
    </div>
</div>

<section class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <?php if (empty($rows)): ?>
    <div class="p-10 text-center text-gray-500">
        <p class="font-medium text-gray-700 mb-2">Aucun paiement enregistré.</p>
        <a href="<?= BASE_URL ?>/pages/paiements/add.php" class="text-primary text-sm hover:underline">Enregistrer un premier paiement</a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-5 py-3 font-medium">Date saisie</th>
                    <th class="px-5 py-3 font-medium">Locataire</th>
                    <th class="px-5 py-3 font-medium">Mois concerné</th>
                    <th class="px-5 py-3 font-medium text-right">Montant</th>
                    <th class="px-5 py-3 font-medium">Payé le</th>
                    <th class="px-5 py-3 font-medium">Mode</th>
                    <th class="px-5 py-3 font-medium">Quittance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($rows as $p): ?>
                <tr class="hover:bg-gray-50/80">
                    <td class="px-5 py-3 text-gray-500 whitespace-nowrap">
                        <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
                    </td>
                    <td class="px-5 py-3">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($p['nom_complet']) ?></p>
                        <p class="text-xs text-gray-400"><?= htmlspecialchars($p['maison']) ?> — Ch. <?= htmlspecialchars($p['chambre']) ?></p>
                    </td>
                    <td class="px-5 py-3 text-gray-700 whitespace-nowrap">
                        <?php
                        $mc = $p['mois_concerne'];
                        $ts = strtotime($mc . ' 12:00:00');
                        $moisFr = ['01' => 'janvier', '02' => 'février', '03' => 'mars', '04' => 'avril', '05' => 'mai', '06' => 'juin', '07' => 'juillet', '08' => 'août', '09' => 'septembre', '10' => 'octobre', '11' => 'novembre', '12' => 'décembre'];
                        $label = ($moisFr[date('m', $ts)] ?? date('m', $ts)) . ' ' . date('Y', $ts);
                        echo htmlspecialchars($label);
                        ?>
                    </td>
                    <td class="px-5 py-3 text-right font-semibold text-green-700 whitespace-nowrap">
                        <?= number_format((float) $p['montant'], 0, ',', ' ') ?> FCFA
                    </td>
                    <td class="px-5 py-3 text-gray-600 whitespace-nowrap"><?= date('d/m/Y', strtotime($p['date_paiement'])) ?></td>
                    <td class="px-5 py-3 text-gray-600"><?= htmlspecialchars(paiement_mode_label((string) $p['mode_paiement'])) ?></td>
                    <td class="px-5 py-3 text-gray-500 text-xs">
                        <?php if (!empty($p['quittance_id']) && !empty($p['pdf_path'])): ?>
                        <span class="text-gray-800"><?= htmlspecialchars((string) $p['numero_quittance']) ?></span>
                        <br><a href="<?= BASE_URL ?>/pages/quittances/download.php?id=<?= (int) $p['quittance_id'] ?>"
                               class="text-primary hover:underline">PDF</a>
                        <?php elseif (!empty($p['numero_quittance'])): ?>
                        <?= htmlspecialchars((string) $p['numero_quittance']) ?>
                        <?php else: ?>
                        —
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400 px-5 py-3 border-t border-gray-100">Les 300 derniers enregistrements.</p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
