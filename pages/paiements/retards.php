<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = Database::getInstance();
$paiementMonthColumn = Database::paiementMonthColumn();

$mois_courant = date('Y-m-01');
$moisFr = ['01' => 'janvier', '02' => 'février', '03' => 'mars', '04' => 'avril', '05' => 'mai', '06' => 'juin', '07' => 'juillet', '08' => 'août', '09' => 'septembre', '10' => 'octobre', '11' => 'novembre', '12' => 'décembre'];
$titre_mois = ($moisFr[date('m', strtotime($mois_courant))] ?? date('m')) . ' ' . date('Y', strtotime($mois_courant));

$retards = $db->prepare("
    SELECT l.id, m.nom AS maison, c.numero AS chambre,
           l.nom_complet, l.telephone, l.loyer_mensuel
    FROM locataires l
    JOIN chambres c ON c.id = l.chambre_id
    JOIN maisons  m ON m.id = c.maison_id
    WHERE l.actif = 1
      AND l.id NOT IN (
          SELECT locataire_id FROM paiements WHERE $paiementMonthColumn = ?
      )
    ORDER BY m.nom, c.numero
");
$retards->execute([$mois_courant]);
$retards = $retards->fetchAll();

$page_title = 'Retards de paiement';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-semibold text-gray-800">Retards ce mois</h1>
        <p class="text-sm text-gray-500 mt-1">Locataires <strong class="text-gray-700">actifs</strong> sans enregistrement de paiement pour <strong class="text-gray-800"><?= htmlspecialchars($titre_mois) ?></strong>.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="<?= BASE_URL ?>/pages/paiements/add.php"
           class="inline-flex items-center px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">
            Enregistrer un paiement
        </a>
        <a href="<?= BASE_URL ?>/pages/paiements/index.php"
           class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-200 text-gray-700 text-sm hover:bg-gray-50 transition">Historique</a>
    </div>
</div>

<section class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <?php if (empty($retards)): ?>
    <div class="p-10 text-center">
        <p class="text-green-700 font-medium mb-1">Aucun retard pour ce mois.</p>
        <p class="text-sm text-gray-500">Tous les locataires actifs ont un paiement enregistré pour <?= htmlspecialchars($titre_mois) ?>.</p>
    </div>
    <?php else: ?>
    <div class="px-5 py-3 border-b border-gray-100 bg-red-50">
        <span class="text-sm font-medium text-red-800"><?= count($retards) ?> locataire(s) en retard</span>
    </div>
    <ul class="divide-y divide-gray-100">
        <?php foreach ($retards as $r): ?>
        <li class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-4 hover:bg-gray-50/80">
            <div>
                <p class="font-medium text-gray-800"><?= htmlspecialchars($r['nom_complet']) ?></p>
                <p class="text-sm text-gray-500"><?= htmlspecialchars($r['maison']) ?> — Chambre <?= htmlspecialchars($r['chambre']) ?></p>
                <?php if (!empty($r['telephone'])): ?>
                <p class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($r['telephone']) ?></p>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-4 shrink-0">
                <p class="text-sm font-semibold text-red-600">
                    <?= number_format((float) $r['loyer_mensuel'], 0, ',', ' ') ?> FCFA
                </p>
                <a href="<?= BASE_URL ?>/pages/paiements/add.php?locataire=<?= (int) $r['id'] ?>"
                   class="inline-flex px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-medium hover:bg-primary-dark transition">Enregistrer</a>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
