<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = Database::getInstance();
require_once __DIR__ . '/../../includes/sync_chambres.php';
sync_chambre_statuts($db);

$filtre = $_GET['filtre'] ?? 'tous';
$where = $filtre === 'actifs' ? 'WHERE l.actif = 1' : '';

$sql = "
    SELECT l.id, l.nom_complet, l.telephone, l.email, l.loyer_mensuel, l.date_entree, l.date_sortie, l.actif,
           c.id AS chambre_id, c.numero AS chambre_numero, m.nom AS maison_nom
    FROM locataires l
    JOIN chambres c ON c.id = l.chambre_id
    JOIN maisons m ON m.id = c.maison_id
    $where
    ORDER BY l.actif DESC, m.nom, c.numero, l.nom_complet
";
$rows = $db->query($sql)->fetchAll();

$page_title = 'Locataires';
require_once __DIR__ . '/../../includes/header.php';

$flash = null;
if (!empty($_GET['created'])) {
    $flash = ['ok', 'Locataire enregistré.'];
} elseif (!empty($_GET['updated'])) {
    $flash = ['ok', 'Fiche mise à jour.'];
} elseif (!empty($_GET['quitte'])) {
    $flash = ['ok', 'Fin de bail enregistrée (historique conservé).'];
} elseif (!empty($_GET['need_chambre'])) {
    $flash = ['warn', 'Aucune chambre libre : ajoutez des chambres ou terminez un bail pour libérer une chambre.'];
} elseif (!empty($_GET['need_locataire_paiement'])) {
    $flash = ['warn', 'Aucun locataire actif : affectez un locataire à une chambre avant d’enregistrer un paiement.'];
}
?>

<?php if (!empty($flash) && $flash[0] === 'warn'): ?>
<div class="mb-4 rounded-lg bg-amber-50 text-amber-900 text-sm px-4 py-3 border border-amber-100">
    <?= htmlspecialchars($flash[1], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<?php if (!empty($flash) && $flash[0] === 'ok'): ?>
<div class="mb-4 rounded-lg bg-green-50 text-green-800 text-sm px-4 py-3 border border-green-100">
    <?= htmlspecialchars($flash[1], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-semibold text-gray-800">Locataires</h1>
        <p class="text-sm text-gray-500 mt-1">Une chambre ne peut avoir qu’<strong class="text-gray-700">un seul locataire actif</strong> à la fois. Les anciens baux restent en base pour l’historique.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <div class="flex rounded-lg border border-gray-200 bg-white p-0.5 text-xs">
            <a href="<?= BASE_URL ?>/pages/locataires/index.php?filtre=tous"
               class="px-3 py-1.5 rounded-md <?= $filtre === 'tous' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-50' ?>">Tous</a>
            <a href="<?= BASE_URL ?>/pages/locataires/index.php?filtre=actifs"
               class="px-3 py-1.5 rounded-md <?= $filtre === 'actifs' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-50' ?>">Actifs seulement</a>
        </div>
        <a href="<?= BASE_URL ?>/pages/locataires/add.php"
           class="inline-flex items-center px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">
            Affecter un locataire
        </a>
    </div>
</div>

<section class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <?php if (empty($rows)): ?>
    <div class="p-10 text-center text-gray-500">
        <p class="font-medium text-gray-700 mb-2">Aucun locataire<?= $filtre === 'actifs' ? ' actif' : '' ?>.</p>
        <a href="<?= BASE_URL ?>/pages/locataires/add.php" class="text-primary text-sm hover:underline">Affecter un locataire à une chambre</a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-5 py-3 font-medium">Locataire</th>
                    <th class="px-5 py-3 font-medium">Chambre</th>
                    <th class="px-5 py-3 font-medium text-right">Loyer</th>
                    <th class="px-5 py-3 font-medium">Entrée</th>
                    <th class="px-5 py-3 font-medium">Statut</th>
                    <th class="px-5 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($rows as $r): ?>
                <tr class="hover:bg-gray-50/80 <?= empty($r['actif']) ? 'opacity-75' : '' ?>">
                    <td class="px-5 py-3">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($r['nom_complet']) ?></p>
                        <p class="text-xs text-gray-400">
                            <?php if (!empty($r['telephone'])): ?><?= htmlspecialchars($r['telephone']) ?><?php endif; ?>
                            <?php if (!empty($r['telephone']) && !empty($r['email'])): ?> · <?php endif; ?>
                            <?php if (!empty($r['email'])): ?><?= htmlspecialchars($r['email']) ?><?php endif; ?>
                        </p>
                    </td>
                    <td class="px-5 py-3 text-gray-700">
                        <?= htmlspecialchars($r['maison_nom']) ?> — <span class="font-medium"><?= htmlspecialchars($r['chambre_numero']) ?></span>
                    </td>
                    <td class="px-5 py-3 text-right font-medium text-gray-800">
                        <?= number_format((float) $r['loyer_mensuel'], 0, ',', ' ') ?> <span class="text-gray-400 text-xs">FCFA</span>
                    </td>
                    <td class="px-5 py-3 text-gray-600 whitespace-nowrap">
                        <?= date('d/m/Y', strtotime($r['date_entree'])) ?>
                        <?php if (empty($r['actif']) && !empty($r['date_sortie'])): ?>
                        <br><span class="text-xs text-gray-400">Sortie <?= date('d/m/Y', strtotime($r['date_sortie'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3">
                        <?php if (!empty($r['actif'])): ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-800">Actif</span>
                        <?php else: ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">Ancien bail</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        <a href="<?= BASE_URL ?>/pages/locataires/edit.php?id=<?= (int) $r['id'] ?>"
                           class="text-gray-600 hover:underline mr-2">Modifier</a>
                        <?php if (!empty($r['actif'])): ?>
                        <a href="<?= BASE_URL ?>/pages/locataires/quitter.php?id=<?= (int) $r['id'] ?>"
                           class="text-amber-700 hover:underline">Fin de bail</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
