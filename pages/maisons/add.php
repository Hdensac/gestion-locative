<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

$db = Database::getInstance();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expirée. Merci de réessayer.';
    } else {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $adresse = trim((string) ($_POST['adresse'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($nom === '') {
            $errors[] = 'Le nom est obligatoire.';
        } elseif (mb_strlen($nom) > 100) {
            $errors[] = 'Le nom est trop long (100 caractères max).';
        }

        if (empty($errors)) {
            // adresse souvent NOT NULL en base existante : chaîne vide plutôt que NULL
            $st = $db->prepare('INSERT INTO maisons (nom, adresse, description) VALUES (?,?,?)');
            $st->execute([$nom, $adresse, $description !== '' ? $description : null]);
            header('Location: ' . BASE_URL . '/pages/maisons/index.php?created=1');
            exit;
        }
    }
}

$page_title = 'Ajouter une maison';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="<?= BASE_URL ?>/pages/maisons/index.php" class="text-sm text-primary hover:underline">← Retour aux maisons</a>
        <h1 class="text-2xl font-semibold text-gray-800 mt-2">Ajouter une maison</h1>
    </div>

    <?php if (!empty($_GET['need_maison'])): ?>
    <div class="mb-4 rounded-lg bg-amber-50 text-amber-900 text-sm px-4 py-3 border border-amber-100">
        Créez d’abord une maison pour pouvoir ajouter des chambres.
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="mb-4 rounded-lg bg-red-50 text-red-700 text-sm px-4 py-3 border border-red-100">
        <?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <form method="post" class="space-y-5">
            <?= csrf_field() ?>
            <div>
                <label for="nom" class="block text-sm font-medium text-gray-700 mb-1">Nom <span class="text-red-500">*</span></label>
                <input type="text" name="nom" id="nom" required maxlength="100"
                       value="<?= htmlspecialchars($_POST['nom'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
            </div>
            <div>
                <label for="adresse" class="block text-sm font-medium text-gray-700 mb-1">Adresse</label>
                <textarea name="adresse" id="adresse" rows="2"
                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none"><?= htmlspecialchars($_POST['adresse'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" id="description" rows="3"
                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none"><?= htmlspecialchars($_POST['description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">
                    Enregistrer
                </button>
                <a href="<?= BASE_URL ?>/pages/maisons/index.php" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50 transition">Annuler</a>
            </div>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
