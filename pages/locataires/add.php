<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';

$db = Database::getInstance();

$chambres_libres = $db->query("
    SELECT c.id, c.numero, m.nom AS maison_nom
    FROM chambres c
    JOIN maisons m ON m.id = c.maison_id
    WHERE NOT EXISTS (
        SELECT 1 FROM locataires l WHERE l.chambre_id = c.id AND l.actif = 1
    )
    ORDER BY m.nom, c.numero
")->fetchAll();

$ids_libres = array_map('intval', array_column($chambres_libres, 'id'));
$pref_chambre = isset($_GET['chambre_id']) ? (int) $_GET['chambre_id'] : 0;
if ($pref_chambre > 0 && !in_array($pref_chambre, $ids_libres, true)) {
    $pref_chambre = 0;
}

if (empty($chambres_libres)) {
    header('Location: ' . BASE_URL . '/pages/locataires/index.php?need_chambre=1');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expirée. Merci de réessayer.';
    } else {
        $chambre_id = (int) ($_POST['chambre_id'] ?? 0);
        $nom_complet = trim((string) ($_POST['nom_complet'] ?? ''));
        $telephone = trim((string) ($_POST['telephone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $loyer_raw = trim((string) ($_POST['loyer_mensuel'] ?? ''));
        $date_entree = trim((string) ($_POST['date_entree'] ?? ''));

        if ($chambre_id < 1) {
            $errors[] = 'Choisissez une chambre.';
        }
        if ($nom_complet === '' || mb_strlen($nom_complet) > 150) {
            $errors[] = 'Nom complet obligatoire (150 caractères max).';
        }
        if (mb_strlen($telephone) > 25) {
            $errors[] = 'Téléphone trop long.';
        }
        if (mb_strlen($email) > 150) {
            $errors[] = 'E-mail trop long.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail invalide.';
        }

        $loyer_norm = str_replace([' ', ','], ['', '.'], $loyer_raw);
        if ($loyer_norm === '' || !is_numeric($loyer_norm) || (float) $loyer_norm < 0) {
            $errors[] = 'Indiquez un loyer mensuel valide.';
        }
        $loyer_mensuel = round((float) $loyer_norm, 2);

        if ($date_entree === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_entree)) {
            $errors[] = 'Date d’entrée invalide.';
        }

        if (empty($errors)) {
            $chk = $db->prepare('SELECT id FROM locataires WHERE chambre_id = ? AND actif = 1');
            $chk->execute([$chambre_id]);
            if ($chk->fetch()) {
                $errors[] = 'Cette chambre a déjà un locataire actif.';
            }
        }

        $ids_ok = array_column($chambres_libres, 'id');
        if (empty($errors) && !in_array($chambre_id, array_map('intval', $ids_ok), true)) {
            $errors[] = 'Chambre non disponible.';
        }

        if (empty($errors)) {
            $ins = $db->prepare('
                INSERT INTO locataires (chambre_id, nom_complet, telephone, email, loyer_mensuel, date_entree, actif)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ');
            $ins->execute([
                $chambre_id,
                $nom_complet,
                $telephone !== '' ? $telephone : null,
                $email !== '' ? $email : null,
                $loyer_mensuel,
                $date_entree,
            ]);
            require_once __DIR__ . '/../../includes/sync_chambres.php';
            sync_chambre_statuts($db);
            header('Location: ' . BASE_URL . '/pages/locataires/index.php?created=1');
            exit;
        }
    }
} elseif ($pref_chambre > 0) {
    $_POST['chambre_id'] = (string) $pref_chambre;
}

$page_title = 'Affecter un locataire';
require_once __DIR__ . '/../../includes/header.php';

$sel_chambre = (int) ($_POST['chambre_id'] ?? ($pref_chambre > 0 ? $pref_chambre : 0));
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="<?= BASE_URL ?>/pages/locataires/index.php" class="text-sm text-primary hover:underline">← Retour aux locataires</a>
        <h1 class="text-2xl font-semibold text-gray-800 mt-2">Affecter un locataire</h1>
        <p class="text-sm text-gray-500 mt-1">Chambres listées : sans locataire actif uniquement.</p>
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
                <label for="chambre_id" class="block text-sm font-medium text-gray-700 mb-1">Chambre <span class="text-red-500">*</span></label>
                <select name="chambre_id" id="chambre_id" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none bg-white">
                    <option value="">— Choisir —</option>
                    <?php foreach ($chambres_libres as $ch): ?>
                    <option value="<?= (int) $ch['id'] ?>" <?= $sel_chambre === (int) $ch['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ch['maison_nom'], ENT_QUOTES, 'UTF-8') ?> — Ch. <?= htmlspecialchars($ch['numero'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="nom_complet" class="block text-sm font-medium text-gray-700 mb-1">Nom complet <span class="text-red-500">*</span></label>
                <input type="text" name="nom_complet" id="nom_complet" required maxlength="150"
                       value="<?= htmlspecialchars($_POST['nom_complet'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="telephone" class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                    <input type="text" name="telephone" id="telephone" maxlength="25"
                           value="<?= htmlspecialchars($_POST['telephone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                    <input type="email" name="email" id="email" maxlength="150"
                           value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="loyer_mensuel" class="block text-sm font-medium text-gray-700 mb-1">Loyer mensuel (FCFA) <span class="text-red-500">*</span></label>
                    <input type="text" name="loyer_mensuel" id="loyer_mensuel" required inputmode="decimal"
                           placeholder="ex. 75000"
                           value="<?= htmlspecialchars($_POST['loyer_mensuel'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                </div>
                <div>
                    <label for="date_entree" class="block text-sm font-medium text-gray-700 mb-1">Date d’entrée <span class="text-red-500">*</span></label>
                    <input type="date" name="date_entree" id="date_entree" required
                           value="<?= htmlspecialchars($_POST['date_entree'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">Enregistrer</button>
                <a href="<?= BASE_URL ?>/pages/locataires/index.php" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50 transition">Annuler</a>
            </div>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
