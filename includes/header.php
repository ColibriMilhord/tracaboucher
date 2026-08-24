<?php $page_active = $page_active ?? 'dashboard'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="dark">
  <title><?= h($page_title ?? APP_NAME) ?></title>
  
  <!-- Material Symbols -->
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
  <!-- Google Fonts: Chivo & Public Sans -->
  <link href="https://fonts.googleapis.com" rel="preconnect"/>
  <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
  <link href="https://fonts.googleapis.com/css2?family=Chivo:wght@400;700;800&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <!-- Tailwind Theme Configuration -->
  <script id="tailwind-config">
    tailwind.config = {
        darkMode: "class",
        theme: {
            extend: {
                colors: {
                    primary: "#2c5530", // Vert Causselot
                    "primary-container": "#4a7c59", // Vert clair
                    "on-primary": "#ffffff",
                    "on-primary-container": "#ffffff",
                    secondary: "#8b4513", // Terre Causselot
                    "secondary-container": "#a05a2c", // Terre clair
                    "on-secondary": "#ffffff",
                    "on-secondary-container": "#ffffff",
                    background: "#f9f8f4", // Light Causselot
                    "on-background": "#2c2c2c",
                    surface: "#ffffff",
                    "on-surface": "#2c2c2c",
                    "surface-variant": "#e0e0e0",
                    "on-surface-variant": "#4a4a4a",
                    "outline-variant": "#e0e0e0",
                    error: "#ba1a1a",
                    "error-container": "#ffdad6",
                    "on-error": "#ffffff",
                    "on-error-container": "#93000a",
                    "surface-container-lowest": "#ffffff",
                    "surface-container-low": "#f9f8f4",
                    "surface-container": "#f0f0f0",
                    "surface-container-high": "#e8e8e8",
                    "surface-container-highest": "#e0e0e0"
                },
                borderRadius: {
                    DEFAULT: "0.125rem",
                    lg: "0.25rem",
                    xl: "0.5rem",
                    "full": "9999px"
                },
                fontFamily: {
                    "headline-lg": ["Chivo", "sans-serif"],
                    "headline-md": ["Chivo", "sans-serif"],
                    "display": ["Chivo", "sans-serif"],
                    "body-md": ["Public Sans", "sans-serif"],
                    "body-lg": ["Public Sans", "sans-serif"],
                    "label-md": ["Public Sans", "sans-serif"],
                    "label-xl": ["Public Sans", "sans-serif"]
                }
            }
        }
    }
  </script>
  <style>
    body { min-height: 100dvh; }
    /* Utilitaires supplémentaires pour reproduire l'ancien comportement si besoin */
    .modal-bg { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 500; align-items: flex-end; }
    .modal-bg.open { display: flex; }
    .modal { background: white; border-radius: 20px 20px 0 0; padding: 0 16px 40px; width: 100%; max-height: 94vh; overflow-y: auto; animation: slideUp .25s ease; }
    @keyframes slideUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
  </style>
</head>
<body class="bg-background text-on-background font-body-md overflow-x-hidden flex flex-col min-h-screen">

<!-- TopAppBar -->
<header class="w-full top-0 sticky z-50 bg-surface border-b border-outline-variant flex items-center justify-between px-4 h-14">
  <button class="material-symbols-outlined text-primary hover:bg-surface-container-high active:bg-surface-variant transition-colors p-2 rounded-full" data-icon="menu">menu</button>
  <h1 class="font-headline-md text-xl font-bold text-primary"><?= h($page_title_top ?? 'BoucherTraçabilité') ?></h1>
  <button class="material-symbols-outlined text-primary hover:bg-surface-container-high active:bg-surface-variant transition-colors p-2 rounded-full" data-icon="account_circle">account_circle</button>
</header>

<div class="flex flex-1 relative">
  <!-- NavigationDrawer (Desktop Only) -->
  <aside class="hidden md:flex flex-col h-[calc(100vh-56px)] w-64 rounded-r-xl border-r border-outline-variant shadow-lg bg-surface py-6 sticky top-[56px] overflow-y-auto z-40">
    <nav class="flex-1 flex flex-col gap-2">
      <a class="<?= $page_active==='dashboard'?'bg-primary-container text-on-primary-container':'text-on-surface-variant hover:bg-surface-container-low' ?> rounded-full mx-2 active:bg-surface-container-highest transition-all flex items-center gap-4 px-4 py-3 font-body-lg" href="index.php">
        <span class="material-symbols-outlined" data-icon="dashboard">dashboard</span>Tableau de bord
      </a>
      <a class="<?= $page_active==='carcasse'?'bg-primary-container text-on-primary-container':'text-on-surface-variant hover:bg-surface-container-low' ?> rounded-full mx-2 active:bg-surface-container-highest transition-all flex items-center gap-4 px-4 py-3 font-body-lg" href="lots.php">
        <span class="material-symbols-outlined" data-icon="qr_code_scanner">qr_code_scanner</span>Carcasse
      </a>
      <a class="<?= $page_active==='stocks'?'bg-primary-container text-on-primary-container':'text-on-surface-variant hover:bg-surface-container-low' ?> rounded-full mx-2 active:bg-surface-container-highest transition-all flex items-center gap-4 px-4 py-3 font-body-lg" href="stocks.php">
        <span class="material-symbols-outlined" data-icon="inventory_2">inventory_2</span>Stocks
      </a>
      <a class="<?= $page_active==='plats'?'bg-primary-container text-on-primary-container':'text-on-surface-variant hover:bg-surface-container-low' ?> rounded-full mx-2 active:bg-surface-container-highest transition-all flex items-center gap-4 px-4 py-3 font-body-lg" href="traiteur.php">
        <span class="material-symbols-outlined" data-icon="restaurant_menu">restaurant_menu</span>Plats
      </a>
    </nav>
  </aside>

  <!-- Main Container -->
  <main class="flex-1 p-4 md:p-8 overflow-y-auto pb-32 md:pb-10 max-w-5xl mx-auto w-full">
