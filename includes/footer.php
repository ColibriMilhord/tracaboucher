  </main>
</div>

<nav class="fixed bottom-0 w-full z-50 md:hidden bg-surface border-t border-outline-variant flex justify-around items-center nav-basse pb-safe px-2">
  <?php foreach ([
      ['dashboard',    'index.php',        'dashboard',     'Accueil'],
      ['entrees',      'entrees.php',      'move_to_inbox', 'Entrées'],
      ['fabrications', 'fabrications.php', 'outbox',        'Fabric.'],
      ['tracabilite',  'tracabilite.php',  'account_tree',  'Traça'],
      ['exports',      'exports.php',      'download',      'Exports'],
  ] as [$k,$url,$icone,$lib]): $on = $page_active === $k; ?>
  <a href="<?= $url ?>" class="flex flex-col items-center justify-center <?= $on?'text-primary font-bold':'text-on-surface-variant' ?> active:scale-95 transition-transform px-3 py-1 rounded-full">
    <span class="material-symbols-outlined"><?= $icone ?></span>
    <span class="text-xs mt-0.5"><?= $lib ?></span>
  </a>
  <?php endforeach ?>
</nav>

<script src="app.js"></script>

</body></html>
