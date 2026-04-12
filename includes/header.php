<?php
// $page_title doit être défini avant d'inclure ce fichier
$page_title = $page_title ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#1A56A0', light: '#D6E4F7', dark: '#0D3A70' },
                        accent:  { DEFAULT: '#0E7C6B', light: '#D4F0EB' },
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 min-h-screen font-sans">

<!-- Barre de navigation -->
<nav class="bg-primary shadow-md">
    <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-14">

        <!-- Logo / nom -->
        <a href="<?= BASE_URL ?>/pages/dashboard.php" class="text-white font-semibold text-lg tracking-wide flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.75L12 3l9 6.75V21a.75.75 0 01-.75.75H15v-6H9v6H3.75A.75.75 0 013 21V9.75z"/>
            </svg>
            <?= APP_NAME ?>
        </a>

        <!-- Menu -->
        <div class="flex items-center gap-1 text-sm flex-wrap justify-end">
            <?php
            $scriptPath = str_replace('\\', '/', $_SERVER['PHP_SELF'] ?? '');
            $nav = [
                BASE_URL . '/pages/dashboard.php'         => ['Tableau de bord', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                BASE_URL . '/pages/maisons/index.php'     => ['Maisons',         'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
                BASE_URL . '/pages/chambres/index.php'    => ['Chambres',        'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z'],
                BASE_URL . '/pages/locataires/index.php'  => ['Locataires',      'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0'],
                BASE_URL . '/pages/paiements/index.php'   => ['Paiements',       'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
            ];
            foreach ($nav as $href => [$label, $icon]):
                $path = parse_url($href, PHP_URL_PATH) ?: '';
                $active = $path !== '' && str_ends_with($scriptPath, $path);
                $cls = $active
                    ? 'bg-white/20 text-white'
                    : 'text-white/80 hover:bg-white/10 hover:text-white';
            ?>
            <a href="<?= $href ?>" class="flex items-center gap-1.5 px-3 py-1.5 rounded-md transition <?= $cls ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="<?= $icon ?>"/>
                </svg>
                <?= $label ?>
            </a>
            <?php endforeach; ?>

            <a href="<?= BASE_URL ?>/logout.php" class="ml-3 text-white/70 hover:text-white text-xs px-2 py-1 rounded border border-white/20 hover:border-white/50 transition">
                Déconnexion
            </a>
        </div>
    </div>
</nav>

<!-- Contenu principal -->
<main class="max-w-7xl mx-auto px-4 py-8">
