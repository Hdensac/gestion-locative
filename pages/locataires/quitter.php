<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

$db = Database::getInstance();
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header('Location: ' . BASE_URL . '/pages/locataires/index.php');
    exit;
}

$st = $db->prepare('
    SELECT l.id, l.nom_complet, l.date_entree, l.actif, c.numero AS chambre_numero, m.nom AS maison_nom
    FROM locataires l
    JOIN chambres c ON c.id = l.chambre_id
    JOIN maisons m ON m.id = c.maison_id
    WHERE l.id = ?
');
$st->execute([$id]);
$row = $st->fetch();
if (!$row || empty($row['actif'])) {
    header('Location: ' . BASE_URL . '/pages/locataires/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Session expirée. Merci de réessayer.';
    } else {
        $date_sortie = trim((string) ($_POST['date_sortie'] ?? ''));
        if ($date_sortie === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_sortie)) {
            $error = 'Date de sortie invalide.';
        } elseif ($date_sortie < $row['date_entree']) {
            $error = 'La date de sortie ne peut être avant la date d’entrée.';
        } else {
            $up = $db->prepare('UPDATE locataires SET actif = 0, date_sortie = ? WHERE id = ? AND actif = 1');
            $up->execute([$date_sortie, $id]);
            require_once __DIR__ . '/../../includes/sync_chambres.php';
            sync_chambre_statuts($db);
            header('Location: ' . BASE_URL . '/pages/locataires/index.php?quitte=1');
            exit;
        }
    }
}

$default_sortie = $_POST['date_sortie'] ?? date('Y-m-d');

$page_title = 'Fin de bail';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-lg mx-auto">
    <div class="mb-6">
        <a href="<?= BASE_URL ?>/pages/locataires/edit.php?id=<?= $id ?>" class="text-sm text-primary hover:underline">← Retour à la fiche</a>
        <h1 class="text-2xl font-semibold text-gray-800 mt-2">Fin de bail</h1>
    </div>

    <?php if ($error !== ''): ?>
    <div class="mb-4 rounded-lg bg-red-50 text-red-700 text-sm px-4 py-3 border border-red-100">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        <p class="text-sm text-gray-600">
            <strong class="text-gray-800"><?= htmlspecialchars($row['nom_complet'], ENT_QUOTES, 'UTF-8') ?></strong><br>
            <?= htmlspecialchars($row['maison_nom'], ENT_QUOTES, 'UTF-8') ?> — Chambre <?= htmlspecialchars($row['chambre_numero'], ENT_QUOTES, 'UTF-8') ?><br>
            Entrée le <?= date('d/m/Y', strtotime($row['date_entree'])) ?>
        </p>
        <p class="text-sm text-gray-500 border-t border-gray-100 pt-4">
            Le locataire passera en <strong>ancien bail</strong> : la fiche et l’historique des paiements restent en base ; la chambre redeviendra libre pour une nouvelle affectation.
        </p>

        <form method="post" class="space-y-4 pt-2">
            <?= csrf_field() ?>
            <div>
                <label for="date_sortie" class="block text-sm font-medium text-gray-700 mb-1">Date de sortie <span class="text-red-500">*</span></label>
                <input type="date" name="date_sortie" id="date_sortie" required
                       value="<?= htmlspecialchars($default_sortie, ENT_QUOTES, 'UTF-8') ?>"
                       min="<?= htmlspecialchars($row['date_entree'], ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="px-4 py-2 rounded-lg bg-amber-600 text-white text-sm font-medium hover:bg-amber-700 transition">
                    Valider la fin de bail
                </button>
                <a href="<?= BASE_URL ?>/pages/locataires/edit.php?id=<?= $id ?>"
                   class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50 transition">Annuler</a>
            </div>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
