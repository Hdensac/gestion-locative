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
    SELECT c.id, c.maison_id, c.numero, c.statut, m.nom AS maison_nom
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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expirée. Merci de réessayer.';
    } else {
        $numero = trim((string) ($_POST['numero'] ?? ''));

        if ($numero === '') {
            $errors[] = 'Le numéro de chambre est obligatoire.';
        } elseif (mb_strlen($numero) > 20) {
            $errors[] = 'Le numéro est trop long (20 caractères max).';
        }

        if (empty($errors)) {
            $dup = $db->prepare('SELECT id FROM chambres WHERE maison_id = ? AND numero = ? AND id <> ?');
            $dup->execute([(int) $chambre['maison_id'], $numero, $id]);
            if ($dup->fetch()) {
                $errors[] = 'Ce numéro existe déjà pour cette maison.';
            }
        }

        if (empty($errors)) {
            $up = $db->prepare('UPDATE chambres SET numero = ? WHERE id = ?');
            $up->execute([$numero, $id]);
            header('Location: ' . BASE_URL . '/pages/chambres/index.php?maison_id=' . (int) $chambre['maison_id'] . '&updated=1');
            exit;
        }
    }
} else {
    $_POST['numero'] = $chambre['numero'];
}

$page_title = 'Modifier une chambre';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="<?= BASE_URL ?>/pages/chambres/index.php?maison_id=<?= (int) $chambre['maison_id'] ?>"
           class="text-sm text-primary hover:underline">← Retour aux chambres</a>
        <h1 class="text-2xl font-semibold text-gray-800 mt-2">Modifier la chambre</h1>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="mb-4 rounded-lg bg-red-50 text-red-700 text-sm px-4 py-3 border border-red-100">
        <?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        <div class="text-sm text-gray-600">
            <span class="text-gray-500">Maison :</span>
            <strong class="text-gray-800"><?= htmlspecialchars($chambre['maison_nom'], ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <div class="text-sm text-gray-600">
            <span class="text-gray-500">Statut actuel :</span>
            <?php if ($chambre['statut'] === 'occupée'): ?>
            <span class="text-purple-700 font-medium">Occupée</span>
            <?php else: ?>
            <span class="text-teal-700 font-medium">Libre</span>
            <?php endif; ?>
            <span class="text-gray-400">(mis à jour automatiquement)</span>
        </div>

        <form method="post" class="space-y-5 pt-2 border-t border-gray-100">
            <?= csrf_field() ?>
            <div>
                <label for="numero" class="block text-sm font-medium text-gray-700 mb-1">Numéro <span class="text-red-500">*</span></label>
                <input type="text" name="numero" id="numero" required maxlength="20"
                       value="<?= htmlspecialchars((string) ($_POST['numero'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">
                    Enregistrer
                </button>
                <a href="<?= BASE_URL ?>/pages/chambres/index.php?maison_id=<?= (int) $chambre['maison_id'] ?>"
                   class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50 transition">Annuler</a>
            </div>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
