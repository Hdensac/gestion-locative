<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

$db = Database::getInstance();

$maisons = $db->query('SELECT id, nom FROM maisons ORDER BY nom')->fetchAll();
if (empty($maisons)) {
    header('Location: ' . (BASE_PATH ?: '') . '/pages/maisons/add.php?need_maison=1');
    exit;
}

$pref_maison = isset($_GET['maison_id']) ? (int) $_GET['maison_id'] : 0;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expirée. Merci de réessayer.';
    } else {
        $maison_id = (int) ($_POST['maison_id'] ?? 0);
        $numero = trim((string) ($_POST['numero'] ?? ''));

        if ($maison_id < 1) {
            $errors[] = 'Choisissez une maison.';
        } else {
            $chk = $db->prepare('SELECT id FROM maisons WHERE id = ?');
            $chk->execute([$maison_id]);
            if (!$chk->fetch()) {
                $errors[] = 'Maison invalide.';
            }
        }

        if ($numero === '') {
            $errors[] = 'Le numéro de chambre est obligatoire.';
        } elseif (mb_strlen($numero) > 20) {
            $errors[] = 'Le numéro est trop long (20 caractères max).';
        }

        if (empty($errors)) {
            $dup = $db->prepare('SELECT id FROM chambres WHERE maison_id = ? AND numero = ?');
            $dup->execute([$maison_id, $numero]);
            if ($dup->fetch()) {
                $errors[] = 'Ce numéro existe déjà pour cette maison.';
            }
        }

        if (empty($errors)) {
            $ins = $db->prepare("INSERT INTO chambres (maison_id, numero, statut) VALUES (?, ?, 'libre')");
            $ins->execute([$maison_id, $numero]);
            header('Location: ' . (BASE_PATH ?: '') . '/pages/chambres/index.php?maison_id=' . $maison_id . '&created=1');
            exit;
        }
    }
} elseif ($pref_maison > 0) {
    $_POST['maison_id'] = (string) $pref_maison;
}

$page_title = 'Ajouter une chambre';
require_once __DIR__ . '/../../includes/header.php';

$sel_maison = (int) ($_POST['maison_id'] ?? ($pref_maison > 0 ? $pref_maison : 0));
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="<?= BASE_PATH ?>/pages/chambres/index.php<?= $pref_maison > 0 ? '?maison_id=' . $pref_maison : '' ?>"
           class="text-sm text-primary hover:underline">← Retour aux chambres</a>
        <h1 class="text-2xl font-semibold text-gray-800 mt-2">Ajouter une chambre</h1>
        <p class="text-sm text-gray-500 mt-1">Le statut (libre / occupée) est calculé automatiquement selon les locataires actifs.</p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="mb-4 rounded-lg bg-red-50 text-red-700 text-sm px-4 py-3 border border-red-100">
        <?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <form method="post" class="space-y-5">
            <?= csrf_field() ?>
            <div>
                <label for="maison_id" class="block text-sm font-medium text-gray-700 mb-1">Maison <span class="text-red-500">*</span></label>
                <select name="maison_id" id="maison_id" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none bg-white">
                    <option value="">— Choisir —</option>
                    <?php foreach ($maisons as $m): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= $sel_maison === (int) $m['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($m['nom'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="numero" class="block text-sm font-medium text-gray-700 mb-1">Numéro <span class="text-red-500">*</span></label>
                <input type="text" name="numero" id="numero" required maxlength="20"
                       placeholder="ex. C1, A12"
                       value="<?= htmlspecialchars($_POST['numero'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                <p class="text-xs text-gray-400 mt-1">Unique pour la maison choisie.</p>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">
                    Enregistrer
                </button>
                     <a href="<?= BASE_PATH ?>/pages/chambres/index.php<?= $pref_maison > 0 ? '?maison_id=' . $pref_maison : '' ?>"
                   class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50 transition">Annuler</a>
            </div>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
