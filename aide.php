<?php
// ============================================================
//  Aide — le guide complet du logiciel.
//
//  Le contenu vit dans includes/aide_contenu.php : cette page ne fait
//  que le mettre en forme. Un chapitre à ajouter se rajoute là-bas,
//  sans toucher à cet écran.
//
//  Trois façons de s'en servir, et c'est voulu :
//   - on cherche un sujet         → le sommaire, en haut ;
//   - on apprend le logiciel      → on déplie les chapitres dans l'ordre ;
//   - on vérifie une règle        → le mémo, en bas, ou l'impression.
// ============================================================
$page_active = 'aide';
$page_title  = 'Aide';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/aide_contenu.php';
$moi = exiger_connexion();

$chapitres = aide_chapitres();
$memo      = aide_memo();

// Un lien peut viser un chapitre précis (aide.php?c=etiquettes) : il
// s'ouvre alors seul, les autres restent repliés. Sans paramètre, le
// premier chapitre est ouvert — la page ne doit pas accueillir par un
// mur de titres fermés.
$cible  = (string)($_GET['c'] ?? '');
$defaut = $cible !== '' && isset($chapitres[$cible]) ? $cible : array_key_first($chapitres);

$nb_etapes = 0; $nb_regles = 0;
foreach ($chapitres as $c) { $nb_etapes += count($c['etapes']); $nb_regles += count($c['regles']); }

require __DIR__ . '/includes/header.php';
?>

<style>
  /* Imprimée, cette page est le manuel de l'atelier : on ne garde que
     le texte, et tous les chapitres sont dépliés (cf. beforeprint). */
  @media print {
    header, aside, nav, .sans-impression { display: none !important; }
    main { padding: 0 !important; max-width: none !important; }
    .chapitre { break-inside: avoid; border: 0; padding: 0; margin-bottom: 1.2rem; }
    .chapitre summary { list-style: none; }
    a[href]::after { content: ''; }
  }
</style>

<div class="flex items-start justify-between gap-4 mb-1">
  <h2 class="font-headline-lg text-2xl font-bold text-primary">Aide</h2>
  <div class="flex items-center gap-1 shrink-0 sans-impression">
    <button type="button" id="tout-deplier"
            class="text-xs font-semibold text-primary px-3 py-2 rounded-full hover:bg-surface-container-high">
      Tout déplier
    </button>
    <button type="button" onclick="window.print()" title="Imprimer le guide"
            class="material-symbols-outlined text-on-surface-variant hover:bg-surface-container-high p-2 rounded-full">print</button>
  </div>
</div>
<p class="text-sm text-on-surface-variant mb-6">
  Le mode d'emploi complet : <?= count($chapitres) ?> chapitres, <?= $nb_etapes ?> étapes
  et <?= $nb_regles ?> règles appliquées par le logiciel.
  Tout est décrit depuis le logiciel lui-même — ce qui est écrit ici est ce qu'il fait.
</p>

<!-- ── Sommaire ────────────────────────────────────────────── -->
<section class="mb-8 sans-impression">
  <h3 class="font-headline-md font-bold mb-3 text-sm uppercase tracking-wide text-on-surface-variant">Sommaire</h3>
  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
    <?php foreach ($chapitres as $cle => $c): ?>
    <a href="#<?= h($cle) ?>" data-sommaire="<?= h($cle) ?>"
       class="bg-surface rounded-xl border border-outline-variant p-3 flex items-start gap-3 hover:bg-surface-container-low">
      <span class="material-symbols-outlined text-primary shrink-0"><?= h($c['icone']) ?></span>
      <span class="min-w-0">
        <span class="block text-sm font-semibold leading-snug"><?= h($c['titre']) ?></span>
        <span class="block text-xs text-on-surface-variant leading-snug mt-0.5"><?= h($c['resume']) ?></span>
      </span>
    </a>
    <?php endforeach ?>
    <a href="#memo" class="bg-primary-container text-on-primary-container rounded-xl p-3 flex items-start gap-3 sm:col-span-2">
      <span class="material-symbols-outlined shrink-0">rule</span>
      <span class="min-w-0">
        <span class="block text-sm font-semibold leading-snug">Les règles en une page</span>
        <span class="block text-xs leading-snug mt-0.5 opacity-90">Le mémo à relire avant un contrôle.</span>
      </span>
    </a>
  </div>
</section>

<!-- ── Chapitres ───────────────────────────────────────────── -->
<?php foreach ($chapitres as $cle => $c): ?>
<section id="<?= h($cle) ?>" class="scroll-mt-20 mb-4">
  <details class="chapitre bg-surface rounded-xl border border-outline-variant overflow-hidden"
           <?= $cle === $defaut ? 'open' : '' ?>>
    <summary class="flex items-start gap-3 p-4 cursor-pointer select-none hover:bg-surface-container-low">
      <span class="material-symbols-outlined text-primary shrink-0 mt-0.5"><?= h($c['icone']) ?></span>
      <span class="flex-1 min-w-0">
        <span class="block font-headline-md font-bold leading-snug"><?= h($c['titre']) ?></span>
        <span class="block text-xs text-on-surface-variant leading-snug mt-0.5"><?= h($c['resume']) ?></span>
      </span>
      <span class="material-symbols-outlined text-on-surface-variant shrink-0 chevron transition-transform">expand_more</span>
    </summary>

    <div class="px-4 pb-5 pt-1 border-t border-outline-variant">
      <p class="text-sm leading-relaxed mb-5 mt-4"><?= $c['intro'] ?></p>

      <ol class="flex flex-col gap-3 mb-5">
        <?php foreach ($c['etapes'] as $i => [$t_etape, $texte]): ?>
        <li class="flex gap-3">
          <span class="shrink-0 w-6 h-6 rounded-full bg-primary text-on-primary text-xs font-bold flex items-center justify-center mt-0.5"><?= $i + 1 ?></span>
          <span class="min-w-0 text-sm">
            <strong class="block"><?= h($t_etape) ?></strong>
            <span class="text-on-surface-variant leading-relaxed"><?= $texte ?></span>
          </span>
        </li>
        <?php endforeach ?>
      </ol>

      <div class="bg-surface-container-low rounded-lg p-4">
        <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-on-surface-variant mb-2">
          <span class="material-symbols-outlined text-base">gavel</span>Les règles
        </div>
        <ul class="flex flex-col gap-2 text-sm leading-relaxed">
          <?php foreach ($c['regles'] as $r): ?>
          <li class="flex gap-2">
            <span class="text-primary font-bold shrink-0">·</span><span><?= $r ?></span>
          </li>
          <?php endforeach ?>
        </ul>
      </div>

      <?php if (!empty($c['liens'])): ?>
      <div class="flex flex-wrap gap-2 mt-4 sans-impression">
        <?php foreach ($c['liens'] as [$url, $lib]): ?>
        <a href="<?= h($url) ?>" class="inline-flex items-center gap-1 text-xs font-semibold text-primary border border-outline-variant rounded-full px-3 py-2 hover:bg-surface-container-low">
          <?= h($lib) ?><span class="material-symbols-outlined text-sm">arrow_forward</span>
        </a>
        <?php endforeach ?>
      </div>
      <?php endif ?>
    </div>
  </details>
</section>
<?php endforeach ?>

<!-- ── Mémo ────────────────────────────────────────────────── -->
<section id="memo" class="scroll-mt-20 mt-8">
  <h3 class="font-headline-lg text-xl font-bold text-primary mb-1">Les règles en une page</h3>
  <p class="text-sm text-on-surface-variant mb-4">
    À imprimer et punaiser dans l'atelier : ce que le logiciel fait tout seul, et ce qu'il refuse de faire.
  </p>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <?php foreach ($memo as $titre => $lignes): ?>
    <div class="chapitre bg-surface rounded-xl border border-outline-variant p-4">
      <div class="font-headline-md font-bold text-sm mb-3"><?= h($titre) ?></div>
      <ul class="flex flex-col gap-2 text-sm leading-relaxed">
        <?php foreach ($lignes as $l): ?>
        <li class="flex gap-2"><span class="text-primary font-bold shrink-0">·</span><span><?= $l ?></span></li>
        <?php endforeach ?>
      </ul>
    </div>
    <?php endforeach ?>
  </div>
</section>

<p class="text-xs text-on-surface-variant mt-8 leading-relaxed">
  Cette aide est écrite dans le logiciel et livrée avec lui : quand une règle change, elle change
  ici le même jour. Version affichée : <?= h(version_affichee()) ?>.
  Pour la balance et l'import dans DFS, voir l'<a class="text-primary underline" href="guide.php">assistant balance</a>.
</p>

<script>
(function () {
  var details = Array.prototype.slice.call(document.querySelectorAll('details.chapitre'));

  // Le chevron suit l'état, sans dépendre d'un sélecteur CSS que
  // Tailwind ne génère pas depuis le CDN.
  details.forEach(function (d) {
    var chev = d.querySelector('.chevron');
    var suivre = function () { if (chev) chev.style.transform = d.open ? 'rotate(180deg)' : ''; };
    d.addEventListener('toggle', suivre);
    suivre();
  });

  // Un lien du sommaire ouvre son chapitre : sinon on saute sur un
  // titre fermé, et il faut cliquer une deuxième fois.
  function ouvrir(cle) {
    var section = cle ? document.getElementById(cle) : null;
    if (!section) return false;
    var d = section.querySelector('details.chapitre');
    if (d) d.open = true;
    return true;
  }
  document.querySelectorAll('[data-sommaire]').forEach(function (a) {
    a.addEventListener('click', function () { ouvrir(a.getAttribute('data-sommaire')); });
  });
  window.addEventListener('hashchange', function () { ouvrir(location.hash.slice(1)); });
  if (location.hash) { ouvrir(location.hash.slice(1)); }

  var bouton = document.getElementById('tout-deplier');
  if (bouton) {
    bouton.addEventListener('click', function () {
      var ouvre = details.some(function (d) { return !d.open; });
      details.forEach(function (d) { d.open = ouvre; });
      bouton.textContent = ouvre ? 'Tout replier' : 'Tout déplier';
    });
  }

  // À l'impression, tout est déplié : un manuel papier avec des
  // chapitres manquants ne sert à rien.
  var avant = [];
  window.addEventListener('beforeprint', function () {
    avant = details.map(function (d) { return d.open; });
    details.forEach(function (d) { d.open = true; });
  });
  window.addEventListener('afterprint', function () {
    details.forEach(function (d, i) { if (avant.length) d.open = avant[i]; });
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
