<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = Database::getInstance();
require_once __DIR__ . '/../../includes/sync_chambres.php';
sync_chambre_statuts($db);

$rows = $db->query("
    SELECT m.id, m.nom, m.adresse,
           COUNT(c.id) AS nb_chambres,
           COALESCE(SUM(c.statut = 'libre'), 0) AS nb_libres,
           COALESCE(SUM(c.statut = 'occupée'), 0) AS nb_occupees
    FROM maisons m
    LEFT JOIN chambres c ON c.maison_id = m.id
    GROUP BY m.id, m.nom, m.adresse
    ORDER BY m.nom
")->fetchAll();

$page_title = 'Maisons';
require_once __DIR__ . '/../../includes/header.php';

$flash = null;
if (!empty($_GET['created'])) {
    $flash = ['ok', 'Maison créée avec succès.'];
} elseif (!empty($_GET['updated'])) {
    $flash = ['ok', 'Maison mise à jour.'];
} elseif (!empty($_GET['deleted'])) {
    $flash = ['ok', 'Maison supprimée.'];
}
?>

<?php if ($flash): ?>
<div class="mb-4 rounded-lg bg-green-50 text-green-800 text-sm px-4 py-3 border border-green-100">
    <?= htmlspecialchars($flash[1], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-semibold text-gray-800">Maisons</h1>
        <p class="text-sm text-gray-500 mt-1">Gérez vos biens et accédez aux chambres par maison.</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="<?= BASE_URL ?>/pages/chambres/index.php"
           class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-200 text-gray-700 text-sm font-medium hover:bg-gray-50 transition">
            Toutes les chambres
        </a>
        <a href="<?= BASE_URL ?>/pages/maisons/add.php"
           class="inline-flex items-center px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">
            Ajouter une maison
        </a>
    </div>
</div>

<section class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <?php if (empty($rows)): ?>
    <div class="p-10 text-center text-gray-500">
        <p class="font-medium text-gray-700 mb-2">Aucune maison pour l’instant.</p>
        <a href="<?= BASE_URL ?>/pages/maisons/add.php" class="text-primary text-sm hover:underline">Créer la première maison</a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <th class="px-5 py-3 font-medium">Nom</th>
                    <th class="px-5 py-3 font-medium">Adresse</th>
                    <th class="px-5 py-3 font-medium text-center">Chambres</th>
                    <th class="px-5 py-3 font-medium text-center">Libres / occupées</th>
                    <th class="px-5 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($rows as $m): ?>
                <tr class="hover:bg-gray-50/80">
                    <td class="px-5 py-3 font-medium text-gray-800"><?= htmlspecialchars($m['nom']) ?></td>
                    <td class="px-5 py-3 text-gray-600 max-w-xs truncate" title="<?= htmlspecialchars((string) $m['adresse']) ?>">
                        <?= htmlspecialchars((string) $m['adresse']) ?: '—' ?>
                    </td>
                    <td class="px-5 py-3 text-center text-gray-700"><?= (int) $m['nb_chambres'] ?></td>
                    <td class="px-5 py-3 text-center text-gray-600">
                        <span class="text-teal-600 font-medium"><?= (int) $m['nb_libres'] ?></span>
                        <span class="text-gray-300 mx-1">/</span>
                        <span class="text-purple-600 font-medium"><?= (int) $m['nb_occupees'] ?></span>
                    </td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        <a href="<?= BASE_URL ?>/pages/chambres/index.php?maison_id=<?= (int) $m['id'] ?>"
                           class="text-primary hover:underline mr-3">Chambres</a>
                        <a href="<?= BASE_URL ?>/pages/maisons/edit.php?id=<?= (int) $m['id'] ?>"
                           class="text-gray-600 hover:underline mr-3">Modifier</a>
                        <a href="<?= BASE_URL ?>/pages/maisons/delete.php?id=<?= (int) $m['id'] ?>"
                           class="text-red-600 hover:underline">Supprimer</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
