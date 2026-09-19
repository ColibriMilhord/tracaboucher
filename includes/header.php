<?php $page_active = $page_active ?? 'dashboard'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#2c5530">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="TraçaBoucher">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="format-detection" content="telephone=no">
  <link rel="manifest" href="manifest.json">
  <link rel="apple-touch-icon" href="apple-touch-icon.png">
  <link rel="icon" href="icon-192.png" type="image/png">
  <title><?= h($page_title ?? APP_NAME) ?></title>

  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
  <link href="https://fonts.googleapis.com" rel="preconnect"/>
  <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
  <link href="https://fonts.googleapis.com/css2?family=Chivo:wght@400;700;800&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: { extend: {
            colors: {
                primary: "#2c5530", "primary-container": "#4a7c59",
                "on-primary": "#ffffff", "on-primary-container": "#ffffff",
                secondary: "#8b4513", "secondary-container": "#a05a2c",
                "on-secondary": "#ffffff", "on-secondary-container": "#ffffff",
                background: "#f9f8f4", "on-background": "#2c2c2c",
                surface: "#ffffff", "on-surface": "#2c2c2c",
                "surface-variant": "#e0e0e0", "on-surface-variant": "#4a4a4a",
                "outline-variant": "#e0e0e0",
                error: "#ba1a1a", "error-container": "#ffdad6",
                "on-error": "#ffffff", "on-error-container": "#93000a",
                "surface-container-low": "#f9f8f4", "surface-container": "#f0f0f0",
                "surface-container-high": "#e8e8e8", "surface-container-highest": "#e0e0e0"
            },
            borderRadius: { DEFAULT: "0.125rem", lg: "0.25rem", xl: "0.5rem", full: "9999px" },
            fontFamily: {
                "headline-lg": ["Chivo","sans-serif"], "headline-md": ["Chivo","sans-serif"],
                display: ["Chivo","sans-serif"],
                "body-md": ["Public Sans","sans-serif"], "body-lg": ["Public Sans","sans-serif"],
                "label-md": ["Public Sans","sans-serif"]
            }
        } }
    }
  </script>
  <style>
    body { min-height: 100dvh; -webkit-text-size-adjust: 100%; }
    .lot-badge { font-family: 'Chivo', monospace; letter-spacing: .5px; }

    /* 16px minimum : en dessous, iOS zoome à chaque prise de focus. */
    input, select, textarea, button { font-size: 16px; font-family: inherit; }
    input[type=date], input[type=text], input[type=search], input[type=number],
    input[type=password], select, textarea { min-height: 48px; }

    /* Encoches et barre gestuelle des téléphones récents */
    .pb-safe { padding-bottom: env(safe-area-inset-bottom, 0px); }
    .nav-basse { height: calc(64px + env(safe-area-inset-bottom, 0px)); }

    /* Bouton d'enregistrement toujours atteignable au pouce */
    @media (max-width: 767px) {
      .barre-envoi {
        position: sticky;
        bottom: calc(72px + env(safe-area-inset-bottom, 0px));
        z-index: 30;
        box-shadow: 0 6px 20px rgba(0,0,0,.18);
      }
    }
    @media (hover: none) {
      .material-symbols-outlined { user-select: none; }
    }
  </style>
</head>
<body class="bg-background text-on-background font-body-md overflow-x-hidden flex flex-col min-h-screen">

<header class="w-full top-0 sticky z-50 bg-surface border-b border-outline-variant flex items-center justify-between px-4 h-14">
  <a href="index.php" class="font-headline-md text-lg font-bold text-primary flex items-center gap-2">
    <span class="material-symbols-outlined">inventory_2</span><?= h(APP_NAME) ?>
  </a>
  <div class="flex items-center gap-1">
    <?php if (($moi['role'] ?? '') === 'admin'): ?>
    <a href="guide.php" title="Assistant balance" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container-high p-2 rounded-full">help</a>
    <a href="produits.php" title="Produits" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container-high p-2 rounded-full">inventory</a>
    <a href="parametres.php" title="Paramètres" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container-high p-2 rounded-full">settings</a>
    <a href="utilisateurs.php" title="Utilisateurs" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container-high p-2 rounded-full">group</a>
    <?php endif ?>
    <span class="hidden sm:inline text-sm text-on-surface-variant px-2"><?= h($moi['nom'] ?? '') ?></span>
    <a href="logout.php" title="Se déconnecter" class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container-high p-2 rounded-full">logout</a>
  </div>
</header>

<div class="flex flex-1 relative">
  <aside class="hidden md:flex flex-col h-[calc(100vh-56px)] w-60 border-r border-outline-variant bg-surface py-6 sticky top-[56px] overflow-y-auto z-40">
    <nav class="flex-1 flex flex-col gap-1">
      <?php
      $liens = [
        ['dashboard',    'index.php',        'dashboard',       'Tableau de bord'],
        ['entrees',      'entrees.php',      'move_to_inbox',   'Entrées'],
        ['fabrications', 'fabrications.php', 'outbox',          'Fabrications'],
        ['tracabilite',  'tracabilite.php',  'account_tree',    'Traçabilité'],
      ];
      if (($moi['role'] ?? '') === 'admin') {
        $liens[] = ['produits', 'produits.php', 'inventory', 'Produits'];
        $liens[] = ['guide', 'guide.php', 'help', 'Assistant balance'];
      }
      if (defined('CAUSSELOT_URL') && CAUSSELOT_URL !== '') {
        $liens[] = ['causselot', CAUSSELOT_URL, 'storefront', 'Portail CAUSSELOT'];
      }
      foreach ($liens as [$k,$url,$icone,$lib]):
        $on = $page_active === $k;
      ?>
      <a class="<?= $on?'bg-primary-container text-on-primary-container font-semibold':'text-on-surface-variant hover:bg-surface-container-low' ?> rounded-full mx-2 transition-all flex items-center gap-4 px-4 py-3 font-body-lg" href="<?= $url ?>">
        <span class="material-symbols-outlined"><?= $icone ?></span><?= $lib ?>
      </a>
      <?php endforeach ?>
    </nav>
    <div class="px-6 pt-4 text-xs text-on-surface-variant" title="Version déployée"><?= h(version_affichee()) ?></div>
  </aside>

  <main class="flex-1 p-4 md:p-8 pb-36 md:pb-10 max-w-5xl mx-auto w-full">
