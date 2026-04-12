<?php
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Identifiants admin (à changer !)
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', password_hash('admin123', PASSWORD_DEFAULT));

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');

    if ($user === ADMIN_USER && password_verify($pass, ADMIN_PASS)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ' . BASE_URL . '/pages/dashboard.php');
        exit;
    }
    $error = 'Identifiants incorrects.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">

<div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-sm">

    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-12 h-12 bg-blue-50 rounded-xl mb-3">
            <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75L12 3l9 6.75V21a.75.75 0 01-.75.75H15v-6H9v6H3.75A.75.75 0 013 21V9.75z"/>
            </svg>
        </div>
        <h1 class="text-xl font-semibold text-gray-800"><?= APP_NAME ?></h1>
        <p class="text-sm text-gray-400 mt-1">Connexion administrateur</p>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3 mb-4">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-600 mb-1">Identifiant</label>
            <input type="text" name="username" required autofocus
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-600 mb-1">Mot de passe</label>
            <input type="password" name="password" required
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>

        <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg text-sm transition">
            Se connecter
        </button>
    </form>

    <p class="text-center text-xs text-gray-300 mt-6">Identifiants par défaut : admin / admin123</p>
</div>

</body>
</html>
