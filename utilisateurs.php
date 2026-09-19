<?php
// La gestion des comptes est centralisée sur app.causselot.fr depuis la
// connexion unique (SSO) : les rôles y sont 'admin'/'atelier'/'client',
// pas 'admin'/'operateur' comme ici avant. Garder ce formulaire local
// écrirait un rôle que app.causselot.fr ne reconnaît pas, d'où la
// redirection plutôt qu'un formulaire désynchronisé.
require_once __DIR__ . '/includes/auth.php';
exiger_admin();

// On ne redirige que si les comptes lus ici sont bien ceux du portail :
// sinon l'administrateur modifierait des comptes que cette application
// n'utilise pas, et se demanderait pourquoi rien ne change.
if (comptes_partages() && defined('CAUSSELOT_URL') && CAUSSELOT_URL !== '') {
    header('Location: ' . rtrim(CAUSSELOT_URL, '/') . '/gestion_utilisateurs.php');
    exit;
}
$souci = diagnostic_comptes();

$page_active = 'utilisateurs';
$page_title  = 'Utilisateurs';
require __DIR__ . '/includes/header.php';
?>
<div class="bg-surface rounded-xl border border-outline-variant p-5">
  <h2 class="font-headline-lg text-2xl font-bold text-primary mb-2">Utilisateurs</h2>
  <p class="text-sm text-on-surface-variant mb-4">
    Les comptes sont désormais gérés depuis le portail CAUSSELOT
    (app.causselot.fr), pour rester communs à tous les services.
  </p>

  <?php if ($souci !== null): ?>
  <div class="bg-error-container text-on-error-container rounded-lg p-3 text-sm leading-relaxed mb-4">
    <strong class="block mb-1">La connexion unique n'est pas active</strong>
    <?= h($souci) ?>
    Tant que ce point n'est pas réglé, les comptes modifiés sur le portail
    ne changeront rien ici : ce sont les comptes locaux qui servent.
  </div>
  <a href="parametres.php" class="bg-primary text-on-primary rounded-full px-5 py-2.5 font-bold text-sm inline-block">
    Voir l'état dans les paramètres
  </a>
  <?php else: ?>
  <p class="text-sm text-on-surface-variant">
    Il manque juste <code>CAUSSELOT_URL</code> dans
    <code>config.local.php</code> pour vous y rediriger automatiquement —
    voir <code>config.example.php</code>.
  </p>
  <?php endif ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
