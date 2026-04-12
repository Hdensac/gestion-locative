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
    SELECT l.*, c.numero AS chambre_numero, m.nom AS maison_nom
    FROM locataires l
    JOIN chambres c ON c.id = l.chambre_id
    JOIN maisons m ON m.id = c.maison_id
    WHERE l.id = ?
');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
    header('Location: ' . BASE_URL . '/pages/locataires/index.php');
    exit;
}

$actif = !empty($row['actif']);

$chambres_choix = [];
if ($actif) {
    $qc = $db->prepare("
        SELECT c.id, c.numero, m.nom AS maison_nom
        FROM chambres c
        JOIN maisons m ON m.id = c.maison_id
        WHERE c.id = ?
           OR NOT EXISTS (
                SELECT 1 FROM locataires l
                WHERE l.chambre_id = c.id AND l.actif = 1 AND l.id <> ?
            )
        ORDER BY m.nom, c.numero
    ");
    $qc->execute([(int) $row['chambre_id'], $id]);
    $chambres_choix = $qc->fetchAll();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expirée. Merci de réessayer.';
    } else {
        $nom_complet = trim((string) ($_POST['nom_complet'] ?? ''));
        $telephone = trim((string) ($_POST['telephone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $loyer_raw = trim((string) ($_POST['loyer_mensuel'] ?? ''));
        $date_entree = trim((string) ($_POST['date_entree'] ?? ''));
        $date_sortie = trim((string) ($_POST['date_sortie'] ?? ''));

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
            $errors[] = 'Loyer mensuel invalide.';
        }
        $loyer_mensuel = round((float) $loyer_norm, 2);

        if ($date_entree === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_entree)) {
            $errors[] = 'Date d’entrée invalide.';
        }

        $date_sortie_sql = null;
        if ($date_sortie !== '') {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_sortie)) {
                $errors[] = 'Date de sortie invalide.';
            } elseif ($date_sortie < $date_entree) {
                $errors[] = 'La date de sortie ne peut précéder l’entrée.';
            } else {
                $date_sortie_sql = $date_sortie;
            }
        }

        $chambre_id = (int) $row['chambre_id'];
        if ($actif) {
            $chambre_id = (int) ($_POST['chambre_id'] ?? 0);
            if ($chambre_id < 1) {
                $errors[] = 'Choisissez une chambre.';
            }
            $ids_ok = array_map('intval', array_column($chambres_choix, 'id'));
            if (empty($errors) && !in_array($chambre_id, $ids_ok, true)) {
                $errors[] = 'Chambre non disponible.';
            }
            if (empty($errors)) {
                $chk = $db->prepare('SELECT id FROM locataires WHERE chambre_id = ? AND actif = 1 AND id <> ?');
                $chk->execute([$chambre_id, $id]);
                if ($chk->fetch()) {
                    $errors[] = 'Cette chambre a déjà un locataire actif.';
                }
            }
        }

        if (empty($errors)) {
            if ($actif) {
                $up = $db->prepare('
                    UPDATE locataires SET chambre_id = ?, nom_complet = ?, telephone = ?, email = ?,
                    loyer_mensuel = ?, date_entree = ?
                    WHERE id = ?
                ');
                $up->execute([
                    $chambre_id,
                    $nom_complet,
                    $telephone !== '' ? $telephone : null,
                    $email !== '' ? $email : null,
                    $loyer_mensuel,
                    $date_entree,
                    $id,
                ]);
            } else {
                $up = $db->prepare('
                    UPDATE locataires SET nom_complet = ?, telephone = ?, email = ?,
                    loyer_mensuel = ?, date_entree = ?, date_sortie = ?
                    WHERE id = ?
                ');
                $up->execute([
                    $nom_complet,
                    $telephone !== '' ? $telephone : null,
                    $email !== '' ? $email : null,
                    $loyer_mensuel,
                    $date_entree,
                    $date_sortie_sql,
                    $id,
                ]);
            }
            require_once __DIR__ . '/../../includes/sync_chambres.php';
            sync_chambre_statuts($db);
            header('Location: ' . BASE_URL . '/pages/locataires/index.php?updated=1');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_POST = [
        'chambre_id' => (string) $row['chambre_id'],
        'nom_complet' => $row['nom_complet'],
        'telephone' => (string) ($row['telephone'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'loyer_mensuel' => (string) $row['loyer_mensuel'],
        'date_entree' => $row['date_entree'],
        'date_sortie' => (string) ($row['date_sortie'] ?? ''),
    ];
}

$page_title = 'Modifier un locataire';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="<?= BASE_URL ?>/pages/locataires/index.php" class="text-sm text-primary hover:underline">← Retour aux locataires</a>
        <h1 class="text-2xl font-semibold text-gray-800 mt-2">Modifier le locataire</h1>
        <?php if (!$actif): ?>
        <p class="text-sm text-amber-800 mt-2 rounded-lg bg-amber-50 border border-amber-100 px-3 py-2">
            Bail terminé — la chambre n’est plus modifiable ici ; tu peux corriger les infos ou les dates pour l’historique.
        </p>
        <?php endif; ?>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="mb-4 rounded-lg bg-red-50 text-red-700 text-sm px-4 py-3 border border-red-100">
        <?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
        <form method="post" class="space-y-5">
            <?= csrf_field() ?>

            <?php if ($actif): ?>
            <div>
                <label for="chambre_id" class="block text-sm font-medium text-gray-700 mb-1">Chambre <span class="text-red-500">*</span></label>
                <select name="chambre_id" id="chambre_id" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none bg-white">
                    <?php foreach ($chambres_choix as $ch): ?>
                    <option value="<?= (int) $ch['id'] ?>" <?= (int) ($_POST['chambre_id'] ?? 0) === (int) $ch['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ch['maison_nom'], ENT_QUOTES, 'UTF-8') ?> — Ch. <?= htmlspecialchars($ch['numero'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="text-sm text-gray-600 border border-gray-100 rounded-lg px-3 py-2 bg-gray-50">
                <span class="text-gray-500">Chambre :</span>
                <strong><?= htmlspecialchars($row['maison_nom'], ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($row['chambre_numero'], ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <?php endif; ?>

            <div>
                <label for="nom_complet" class="block text-sm font-medium text-gray-700 mb-1">Nom complet <span class="text-red-500">*</span></label>
                <input type="text" name="nom_complet" id="nom_complet" required maxlength="150"
                       value="<?= htmlspecialchars((string) ($_POST['nom_complet'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="telephone" class="block text-sm font-medium text-gray-700 mb-1">Téléphone</label>
                    <input type="text" name="telephone" id="telephone" maxlength="25"
                           value="<?= htmlspecialchars((string) ($_POST['telephone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
                    <input type="email" name="email" id="email" maxlength="150"
                           value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="loyer_mensuel" class="block text-sm font-medium text-gray-700 mb-1">Loyer mensuel (FCFA) <span class="text-red-500">*</span></label>
                    <input type="text" name="loyer_mensuel" id="loyer_mensuel" required inputmode="decimal"
                           value="<?= htmlspecialchars((string) ($_POST['loyer_mensuel'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                </div>
                <div>
                    <label for="date_entree" class="block text-sm font-medium text-gray-700 mb-1">Date d’entrée <span class="text-red-500">*</span></label>
                    <input type="date" name="date_entree" id="date_entree" required
                           value="<?= htmlspecialchars((string) ($_POST['date_entree'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                </div>
            </div>
            <?php if (!$actif): ?>
            <div>
                <label for="date_sortie" class="block text-sm font-medium text-gray-700 mb-1">Date de sortie (historique)</label>
                <input type="date" name="date_sortie" id="date_sortie"
                       value="<?= htmlspecialchars((string) ($_POST['date_sortie'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
            </div>
            <?php endif; ?>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">Enregistrer</button>
                <a href="<?= BASE_URL ?>/pages/locataires/index.php" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50 transition">Annuler</a>
                <?php if ($actif): ?>
                <a href="<?= BASE_URL ?>/pages/locataires/quitter.php?id=<?= $id ?>"
                   class="px-4 py-2 rounded-lg border border-amber-200 text-amber-800 text-sm hover:bg-amber-50 transition ml-auto">Enregistrer fin de bail</a>
                <?php endif; ?>
            </div>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
