<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

$db = Database::getInstance();
require_once __DIR__ . '/../../includes/sync_chambres.php';
sync_chambre_statuts($db);

$maison_id = isset($_GET['maison_id']) ? (int) $_GET['maison_id'] : 0;
$maison_nom = null;

if ($maison_id > 0) {
    $mst = $db->prepare('SELECT id, nom FROM maisons WHERE id = ?');
    $mst->execute([$maison_id]);
    $mrow = $mst->fetch();
    if (!$mrow) {
        header('Location: ' . BASE_URL . '/pages/chambres/index.php');
        exit;
    }
    $maison_nom = $mrow['nom'];
}

if ($maison_id > 0) {
    $st = $db->prepare("
        SELECT c.id, c.numero, c.statut, m.id AS maison_id, m.nom AS maison_nom
        FROM chambres c
        JOIN maisons m ON m.id = c.maison_id
        WHERE c.maison_id = ?
        ORDER BY c.numero
    ");
    $st->execute([$maison_id]);
} else {
    $st = $db->query("
        SELECT c.id, c.numero, c.statut, m.id AS maison_id, m.nom AS maison_nom
        FROM chambres c
        JOIN maisons m ON m.id = c.maison_id
        ORDER BY m.nom, c.numero
    ");
}
$rows = $st->fetchAll();

$page_title = 'Chambres';
require_once __DIR__ . '/../../includes/header.php';

$flash = null;
if (!empty($_GET['created'])) {
    $flash = ['ok', 'Chambre ajoutée.'];
} elseif (!empty($_GET['updated'])) {
    $flash = ['ok', 'Chambre mise à jour.'];
} elseif (!empty($_GET['deleted'])) {
    $flash = ['ok', 'Chambre supprimée.'];
}
?>

<?php if ($flash): ?>
<div class="mb-4 rounded-lg bg-green-50 text-green-800 text-sm px-4 py-3 border border-green-100">
    <?= htmlspecialchars($flash[1], ENT_QUOTES, 'UTF-8') ?>
</div>
<?php endif; ?>

<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="text-2xl font-semibold text-gray-800">Chambres</h1>
        <?php if ($maison_nom !== null): ?>
        <p class="text-sm text-gray-500 mt-1">
            Maison : <strong><?= htmlspecialchars($maison_nom, ENT_QUOTES, 'UTF-8') ?></strong>
            — <a href="<?= BASE_URL ?>/pages/maisons/index.php" class="text-primary hover:underline">Toutes les maisons</a>
        </p>
        <?php else: ?>
        <p class="text-sm text-gray-500 mt-1">Toutes les chambres, toutes maisons confondues.</p>
        <?php endif; ?>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="<?= BASE_URL ?>/pages/maisons/index.php"
           class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-200 text-gray-700 text-sm font-medium hover:bg-gray-50 transition">
            Maisons
        </a>
        <a href="<?= BASE_URL ?>/pages/chambres/add.php<?= $maison_id > 0 ? '?maison_id=' . $maison_id : '' ?>"
           class="inline-flex items-center px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">
            Ajouter une chambre
        </a>
    </div>
</div>

<section class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
    <?php if (empty($rows)): ?>
    <div class="p-10 text-center text-gray-500">
        <p class="font-medium text-gray-700 mb-2">Aucune chambre<?= $maison_id > 0 ? ' pour cette maison' : '' ?>.</p>
        <a href="<?= BASE_URL ?>/pages/chambres/add.php<?= $maison_id > 0 ? '?maison_id=' . $maison_id : '' ?>"
           class="text-primary text-sm hover:underline">Ajouter une chambre</a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 text-left">
                <tr>
                    <?php if ($maison_id <= 0): ?>
                    <th class="px-5 py-3 font-medium">Maison</th>
                    <?php endif; ?>
                    <th class="px-5 py-3 font-medium">Numéro</th>
                    <th class="px-5 py-3 font-medium">Statut</th>
                    <th class="px-5 py-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($rows as $c): ?>
                <tr class="hover:bg-gray-50/80">
                    <?php if ($maison_id <= 0): ?>
                    <td class="px-5 py-3">
                        <a href="<?= BASE_URL ?>/pages/chambres/index.php?maison_id=<?= (int) $c['maison_id'] ?>"
                           class="text-primary hover:underline"><?= htmlspecialchars($c['maison_nom']) ?></a>
                    </td>
                    <?php endif; ?>
                    <td class="px-5 py-3 font-medium text-gray-800"><?= htmlspecialchars($c['numero']) ?></td>
                    <td class="px-5 py-3">
                        <?php if ($c['statut'] === 'occupée'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">Occupée</span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-teal-100 text-teal-800">Libre</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-right whitespace-nowrap">
                        <?php if ($c['statut'] !== 'occupée'): ?>
                        <a href="<?= BASE_URL ?>/pages/locataires/add.php?chambre_id=<?= (int) $c['id'] ?>"
                           class="text-teal-700 hover:underline mr-3">Locataire</a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/pages/chambres/edit.php?id=<?= (int) $c['id'] ?>"
                           class="text-gray-600 hover:underline mr-3">Modifier</a>
                        <a href="<?= BASE_URL ?>/pages/chambres/delete.php?id=<?= (int) $c['id'] ?>"
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
