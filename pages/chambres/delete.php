<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

$db = Database::getInstance();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header('Location: ' . BASE_URL . '/pages/chambres/index.php');
    exit;
}

$st = $db->prepare('
    SELECT c.id, c.maison_id, c.numero, m.nom AS maison_nom
    FROM chambres c
    JOIN maisons m ON m.id = c.maison_id
    WHERE c.id = ?
');
$st->execute([$id]);
$chambre = $st->fetch();
if (!$chambre) {
    header('Location: ' . BASE_URL . '/pages/chambres/index.php');
    exit;
}

$cnt = $db->prepare('SELECT COUNT(*) FROM locataires WHERE chambre_id = ?');
$cnt->execute([$id]);
$nb_loc = (int) $cnt->fetchColumn();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Session expirée. Merci de réessayer.';
    } elseif ($nb_loc > 0) {
        $error = 'Impossible de supprimer : des locataires sont liés à cette chambre (historique inclus).';
    } else {
        $db->prepare('DELETE FROM chambres WHERE id = ?')->execute([$id]);
        header('Location: ' . BASE_URL . '/pages/chambres/index.php?maison_id=' . (int) $chambre['maison_id'] . '&deleted=1');
        exit;
    }
}

$page_title = 'Supprimer une chambre';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <a href="<?= BASE_URL ?>/pages/chambres/index.php?maison_id=<?= (int) $chambre['maison_id'] ?>"
           class="text-sm text-primary hover:underline">← Retour aux chambres</a>
        <h1 class="text-2xl font-semibold text-gray-800 mt-2">Supprimer une chambre</h1>
    </div>

    <?php if ($error !== ''): ?>
    <div class="mb-4 rounded-lg bg-red-50 text-red-700 text-sm px-4 py-3 border border-red-100">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <?php if ($nb_loc > 0 && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
        <p class="text-gray-700 mb-4">
            La chambre <strong><?= htmlspecialchars($chambre['numero'], ENT_QUOTES, 'UTF-8') ?></strong>
            (<?= htmlspecialchars($chambre['maison_nom'], ENT_QUOTES, 'UTF-8') ?>) a
            <strong><?= $nb_loc ?></strong> fiche(s) locataire. Suppression impossible tant qu’une fiche existe.
        </p>
        <a href="<?= BASE_URL ?>/pages/locataires/index.php"
           class="inline-flex px-4 py-2 rounded-lg border border-gray-200 text-gray-700 text-sm hover:bg-gray-50 transition">Locataires</a>
        <?php else: ?>
        <p class="text-gray-700 mb-6">
            Confirmer la suppression de la chambre <strong><?= htmlspecialchars($chambre['numero'], ENT_QUOTES, 'UTF-8') ?></strong>
            (<?= htmlspecialchars($chambre['maison_nom'], ENT_QUOTES, 'UTF-8') ?>) ?
        </p>
        <form method="post" class="flex flex-wrap gap-3">
            <?= csrf_field() ?>
            <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition">
                Oui, supprimer
            </button>
            <a href="<?= BASE_URL ?>/pages/chambres/index.php?maison_id=<?= (int) $chambre['maison_id'] ?>"
               class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50 transition">Annuler</a>
        </form>
        <?php endif; ?>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
