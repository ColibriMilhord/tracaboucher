<?php
// La gestion des comptes est centralisée sur app.causselot.fr depuis la
// connexion unique (SSO) : les rôles y sont 'admin'/'atelier'/'client',
// pas 'admin'/'operateur' comme ici avant. Garder ce formulaire local
// écrirait un rôle que app.causselot.fr ne reconnaît pas, d'où la
// redirection plutôt qu'un formulaire désynchronisé.
require_once __DIR__ . '/includes/auth.php';
exiger_admin();

if (defined('CAUSSELOT_URL') && CAUSSELOT_URL !== '') {
    header('Location: ' . rtrim(CAUSSELOT_URL, '/') . '/gestion_utilisateurs.php');
    exit;
}

$page_active = 'utilisateurs';
$page_title  = 'Utilisateurs';
require __DIR__ . '/includes/header.php';
?>
<div class="bg-surface rounded-xl border border-outline-variant p-5">
  <h2 class="font-headline-lg text-2xl font-bold text-primary mb-2">Utilisateurs</h2>
  <p class="text-sm text-on-surface-variant">
    Les comptes sont désormais gérés depuis le portail CAUSSELOT
    (app.causselot.fr), pour rester communs à tous les services.
    Il manque juste <code>CAUSSELOT_URL</code> dans
    <code>config.local.php</code> pour vous y rediriger automatiquement —
    voir <code>config.example.php</code>.
  </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
