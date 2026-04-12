<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

$db = Database::getInstance();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header('Location: ' . BASE_URL . '/pages/maisons/index.php');
    exit;
}

$st = $db->prepare('SELECT id, nom FROM maisons WHERE id = ?');
$st->execute([$id]);
$maison = $st->fetch();
if (!$maison) {
    header('Location: ' . BASE_URL . '/pages/maisons/index.php');
    exit;
}

$cnt = $db->prepare('SELECT COUNT(*) FROM chambres WHERE maison_id = ?');
$cnt->execute([$id]);
$nb_chambres = (int) $cnt->fetchColumn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Session expirée. Merci de réessayer.';
    } elseif ($nb_chambres > 0) {
        $error = 'Impossible de supprimer : cette maison contient encore des chambres.';
    } else {
        $db->prepare('DELETE FROM maisons WHERE id = ?')->execute([$id]);
        header('Location: ' . BASE_URL . '/pages/maisons/index.php?deleted=1');
        exit;
    }
}

$page_title = 'Supprimer une maison';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <a href="<?= BASE_URL ?>/pages/maisons/index.php" class="text-sm text-primary hover:underline">← Retour aux maisons</a>
        <h1 class="text-2xl font-semibold text-gray-800 mt-2">Supprimer une maison</h1>
    </div>

    <?php if ($error !== ''): ?>
    <div class="mb-4 rounded-lg bg-red-50 text-red-700 text-sm px-4 py-3 border border-red-100">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <?php if ($nb_chambres > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        <p class="text-gray-700 mb-4">
            La maison <strong><?= htmlspecialchars($maison['nom'], ENT_QUOTES, 'UTF-8') ?></strong> a
            <strong><?= $nb_chambres ?></strong> chambre(s). Supprimez d’abord les chambres (ou les données liées) pour pouvoir supprimer la maison.
        </p>
        <a href="<?= BASE_URL ?>/pages/chambres/index.php?maison_id=<?= $id ?>"
           class="inline-flex px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">Voir les chambres</a>
        <?php else: ?>
        <p class="text-gray-700 mb-6">
            Confirmer la suppression de <strong><?= htmlspecialchars($maison['nom'], ENT_QUOTES, 'UTF-8') ?></strong> ?
            Cette action est irréversible.
        </p>
        <form method="post" class="flex flex-wrap gap-3">
            <?= csrf_field() ?>
            <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition">
                Oui, supprimer
            </button>
            <a href="<?= BASE_URL ?>/pages/maisons/index.php" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50 transition">Annuler</a>
        </form>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
