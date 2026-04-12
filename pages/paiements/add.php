<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/paiement_modes.php';

$db = Database::getInstance();

$locataires = $db->query("
    SELECT l.id, l.nom_complet, l.loyer_mensuel, m.nom AS maison, c.numero AS chambre
    FROM locataires l
    JOIN chambres c ON c.id = l.chambre_id
    JOIN maisons m ON m.id = c.maison_id
    WHERE l.actif = 1
    ORDER BY m.nom, c.numero, l.nom_complet
")->fetchAll();

if (empty($locataires)) {
    header('Location: ' . BASE_URL . '/pages/locataires/index.php?need_locataire_paiement=1');
    exit;
}

$pref_loc = isset($_GET['locataire']) ? (int) $_GET['locataire'] : 0;
$ids_loc = array_map('intval', array_column($locataires, 'id'));
if ($pref_loc > 0 && !in_array($pref_loc, $ids_loc, true)) {
    $pref_loc = 0;
}

$modes = paiement_modes();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'Session expirée. Merci de réessayer.';
    } else {
        $locataire_id = (int) ($_POST['locataire_id'] ?? 0);
        $mois_input = trim((string) ($_POST['mois_concerne'] ?? ''));
        $montant_raw = trim((string) ($_POST['montant'] ?? ''));
        $date_paiement = trim((string) ($_POST['date_paiement'] ?? ''));
        $mode_paiement = trim((string) ($_POST['mode_paiement'] ?? ''));
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($locataire_id < 1 || !in_array($locataire_id, $ids_loc, true)) {
            $errors[] = 'Choisissez un locataire actif.';
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $mois_input)) {
            $errors[] = 'Mois concerné invalide.';
        }
        $mois_concerne = $mois_input . '-01';

        $montant_norm = str_replace([' ', ','], ['', '.'], $montant_raw);
        if ($montant_norm === '' || !is_numeric($montant_norm) || (float) $montant_norm <= 0) {
            $errors[] = 'Montant invalide.';
        }
        $montant = round((float) $montant_norm, 2);
        if ($montant > 99999999.99) {
            $errors[] = 'Montant trop élevé.';
        }

        if ($date_paiement === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_paiement)) {
            $errors[] = 'Date de paiement invalide.';
        }

        if (!array_key_exists($mode_paiement, $modes)) {
            $errors[] = 'Mode de paiement invalide.';
        }

        if (mb_strlen($note) > 255) {
            $errors[] = 'Note trop longue (255 caractères max).';
        }

        if (empty($errors)) {
            $dup = $db->prepare('SELECT id FROM paiements WHERE locataire_id = ? AND mois_concerne = ?');
            $dup->execute([$locataire_id, $mois_concerne]);
            if ($dup->fetch()) {
                $errors[] = 'Un paiement existe déjà pour ce locataire et ce mois.';
            }
        }

        if (empty($errors)) {
            require_once __DIR__ . '/../../includes/quittance_service.php';
            try {
                $db->beginTransaction();
                $ins = $db->prepare('
                    INSERT INTO paiements (locataire_id, montant, mois_concerne, date_paiement, mode_paiement, note)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                $ins->execute([
                    $locataire_id,
                    $montant,
                    $mois_concerne,
                    $date_paiement,
                    $mode_paiement,
                    $note !== '' ? $note : null,
                ]);
                $paiementId = (int) $db->lastInsertId();
                quittance_creer_pour_paiement($db, $paiementId);
                $db->commit();
                header('Location: ' . BASE_URL . '/pages/paiements/index.php?saved=1');
                exit;
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $errors[] = 'Enregistrement impossible : ' . $e->getMessage();
            }
        }
    }
}

$default_mois = date('Y-m');
$default_date = date('Y-m-d');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_POST['locataire_id'] = $pref_loc > 0 ? (string) $pref_loc : '';
    $_POST['mois_concerne'] = $default_mois;
    $_POST['date_paiement'] = $default_date;
    $_POST['montant'] = '';
    $_POST['mode_paiement'] = 'espèces';
    $_POST['note'] = '';
} elseif ($pref_loc > 0 && empty($_POST['locataire_id'])) {
    $_POST['locataire_id'] = (string) $pref_loc;
}

$sel_loc = (int) ($_POST['locataire_id'] ?? 0);
$loyer_hint = '';
foreach ($locataires as $L) {
    if ((int) $L['id'] === $sel_loc) {
        $loyer_hint = (string) $L['loyer_mensuel'];
        break;
    }
}

$page_title = 'Enregistrer un paiement';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="max-w-2xl mx-auto">
    <div class="mb-6">
        <a href="<?= BASE_URL ?>/pages/paiements/index.php" class="text-sm text-primary hover:underline">← Historique des paiements</a>
        <h1 class="text-2xl font-semibold text-gray-800 mt-2">Enregistrer un paiement</h1>
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
                <label for="locataire_id" class="block text-sm font-medium text-gray-700 mb-1">Locataire actif <span class="text-red-500">*</span></label>
                <select name="locataire_id" id="locataire_id" required
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none bg-white">
                    <option value="">— Choisir —</option>
                    <?php foreach ($locataires as $L): ?>
                    <option value="<?= (int) $L['id'] ?>" <?= $sel_loc === (int) $L['id'] ? 'selected' : '' ?>
                            data-loyer="<?= htmlspecialchars((string) $L['loyer_mensuel'], ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($L['nom_complet'], ENT_QUOTES, 'UTF-8') ?>
                        — <?= htmlspecialchars($L['maison'], ENT_QUOTES, 'UTF-8') ?> Ch.<?= htmlspecialchars($L['chambre'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($loyer_hint !== ''): ?>
                <p class="text-xs text-gray-400 mt-1">Loyer mensuel indiqué sur la fiche : <?= number_format((float) $loyer_hint, 0, ',', ' ') ?> FCFA (à titre indicatif).</p>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="mois_concerne" class="block text-sm font-medium text-gray-700 mb-1">Mois concerné <span class="text-red-500">*</span></label>
                    <input type="month" name="mois_concerne" id="mois_concerne" required
                           value="<?= htmlspecialchars((string) ($_POST['mois_concerne'] ?? $default_mois), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                </div>
                <div>
                    <label for="date_paiement" class="block text-sm font-medium text-gray-700 mb-1">Date réelle du paiement <span class="text-red-500">*</span></label>
                    <input type="date" name="date_paiement" id="date_paiement" required
                           value="<?= htmlspecialchars((string) ($_POST['date_paiement'] ?? $default_date), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="montant" class="block text-sm font-medium text-gray-700 mb-1">Montant (FCFA) <span class="text-red-500">*</span></label>
                    <input type="text" name="montant" id="montant" required inputmode="decimal"
                           value="<?= htmlspecialchars((string) ($_POST['montant'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                           class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
                </div>
                <div>
                    <label for="mode_paiement" class="block text-sm font-medium text-gray-700 mb-1">Mode <span class="text-red-500">*</span></label>
                    <select name="mode_paiement" id="mode_paiement" required
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none bg-white">
                        <?php foreach ($modes as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= ((string) ($_POST['mode_paiement'] ?? '')) === $val ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label for="note" class="block text-sm font-medium text-gray-700 mb-1">Note (optionnel)</label>
                <input type="text" name="note" id="note" maxlength="255"
                       value="<?= htmlspecialchars((string) ($_POST['note'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-primary/30 focus:border-primary outline-none">
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">Enregistrer</button>
                <a href="<?= BASE_URL ?>/pages/paiements/index.php" class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50 transition">Annuler</a>
            </div>
        </form>
    </section>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
