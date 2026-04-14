<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/paiement_modes.php';

$db = Database::getInstance();

$paiementMonthColumn = Database::paiementMonthColumn();

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
            $dup = $db->prepare("SELECT id FROM paiements WHERE locataire_id = ? AND $paiementMonthColumn = ?");
            $dup->execute([$locataire_id, $mois_concerne]);
            if ($dup->fetch()) {
                $errors[] = 'Un paiement existe déjà pour ce locataire et ce mois.';
            }
        }

        if (empty($errors)) {
            require_once __DIR__ . '/../../includes/quittance_service.php';
            try {
                $db->beginTransaction();
                $ins = $db->prepare("
                    INSERT INTO paiements (locataire_id, montant, $paiementMonthColumn, date_paiement, mode_paiement, note)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
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
                <button type="button" id="btn-confirm-open"
                        class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">
                    Enregistrer
                </button>
                <a href="<?= BASE_URL ?>/pages/paiements/index.php"
                   class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50 transition">
                    Annuler
                </a>
            </div>
        </form>
    </section>
</div>

<!-- ── Modale de confirmation ─────────────────────────────────────────── -->
<div id="confirm-modal"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden"
     role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-start gap-3 mb-4">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h2 id="confirm-modal-title" class="text-base font-semibold text-gray-800">
                    Confirmer l'enregistrement
                </h2>
                <p id="confirm-modal-body" class="mt-1 text-sm text-gray-600 leading-relaxed"></p>
            </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button type="button" id="btn-confirm-cancel"
                    class="px-4 py-2 rounded-lg border border-gray-200 text-gray-600 text-sm hover:bg-gray-50 transition">
                Annuler
            </button>
            <button type="button" id="btn-confirm-ok"
                    class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:bg-primary-dark transition">
                Oui, enregistrer
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const modal      = document.getElementById('confirm-modal');
    const modalBody  = document.getElementById('confirm-modal-body');
    const btnOpen    = document.getElementById('btn-confirm-open');
    const btnCancel  = document.getElementById('btn-confirm-cancel');
    const btnOk      = document.getElementById('btn-confirm-ok');
    const form       = btnOpen.closest('form');
    const selectLoc  = document.getElementById('locataire_id');

    function openModal() {
        const opt = selectLoc.options[selectLoc.selectedIndex];
        if (!opt || opt.value === '') {
            // Laisser la validation HTML5 gérer le champ vide
            form.reportValidity();
            return;
        }

        // Extraire nom et chambre depuis le texte de l'option
        // Format : "Nom Complet — Maison Ch.XX"
        const fullText = opt.text.trim();
        const parts    = fullText.split('—');
        const nomLoc   = parts[0].trim();
        const chambre  = parts.length > 1 ? parts[1].trim() : '';

        let msg = `Êtes-vous sûr de vouloir enregistrer le paiement pour le locataire <strong>${escHtml(nomLoc)}</strong>`;
        if (chambre !== '') {
            // Extraire uniquement le numéro de chambre (après "Ch.")
            const chMatch = chambre.match(/Ch\.(\S+)/i);
            const chNum   = chMatch ? chMatch[1] : chambre;
            msg += ` de la chambre <strong>${escHtml(chNum)}</strong>`;
        }
        msg += '&nbsp;?';

        modalBody.innerHTML = msg;
        modal.classList.remove('hidden');
        btnOk.focus();
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    btnOpen.addEventListener('click', function () {
        // Valider le formulaire avant d'ouvrir la modale
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        openModal();
    });

    btnCancel.addEventListener('click', closeModal);

    btnOk.addEventListener('click', function () {
        closeModal();
        form.submit();
    });

    // Fermer en cliquant sur le fond
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    // Fermer avec Échap
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

