<?php
// ============================================================
//  Matériel — le PC de l'atelier et sa balance.
//
//  L'agent est un petit programme qui tourne sur le poste où est
//  installé DFS. Il porte les produits vers la balance, et remonte ce
//  qu'il voit. Son installation était jusqu'ici décrite dans un README
//  qu'il fallait aller chercher dans le dépôt : elle se fait maintenant
//  depuis le site, étape par étape, avec vérification à la fin.
// ============================================================
$page_active = 'materiel';
$page_title  = 'Matériel';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/atelier.php';
$moi = exiger_admin();

$pdo = db();
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['gen_token'])) {
        $pdo->prepare('INSERT INTO reglages (cle, valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)')
            ->execute(['token_export', bin2hex(random_bytes(24))]);
        $msg = 'Jeton généré. Recopiez l\'URL ci-dessous dans l\'agent.';
    } elseif (isset($_POST['declare_installe'])) {
        definir_reglage_utilisateur((int)$moi['id'], 'agent_installe', 'oui');
        $msg = "C'est noté : l'installation ne vous sera plus proposée.";
    } elseif (isset($_POST['oublier'])) {
        definir_reglage_utilisateur((int)$moi['id'], 'agent_installe', '');
        $msg = 'La question vous sera de nouveau posée.';
    }
}

$token    = reglage('token_export');
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? '')
          . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
$url_recup = $token !== '' ? $base_url . '/export.php?type=dfs_articulo&token=' . $token : '';

$statut     = json_decode(reglage('agent_statut') ?: '', true);
$inventaire = json_decode(reglage('agent_inventaire') ?: '', true);
$agent_vu   = is_array($statut) && !empty($statut['le']);

require __DIR__ . '/includes/header.php';
?>

<h2 class="font-headline-lg text-2xl font-bold text-primary mb-1">Matériel</h2>
<p class="text-sm text-on-surface-variant mb-5">Le PC de l'atelier, celui où est installé DFS.</p>

<?php if ($msg): ?><div class="bg-primary-container text-on-primary-container rounded-xl p-4 mb-4 text-sm"><?= h($msg) ?></div><?php endif ?>
<?php if ($err): ?><div class="bg-error-container text-on-error-container rounded-xl p-4 mb-4 text-sm"><?= h($err) ?></div><?php endif ?>

<!-- État, en tête : c'est la première chose qu'on vient voir -->
<section class="rounded-xl p-5 mb-5 <?= $agent_vu && ($statut['etat'] ?? '') === 'ok'
      ? 'bg-primary-container text-on-primary-container'
      : ($agent_vu ? 'bg-error-container text-on-error-container' : 'bg-surface border border-outline-variant') ?>">
  <div class="font-bold flex items-center gap-2 mb-1">
    <span class="material-symbols-outlined"><?= $agent_vu ? (($statut['etat'] ?? '') === 'ok' ? 'cloud_done' : 'cloud_off') : 'help' ?></span>
    <?php if (!$agent_vu): ?>Agent jamais vu
    <?php elseif (($statut['etat'] ?? '') === 'ok'): ?>Agent en service
    <?php else: ?>Agent en erreur<?php endif ?>
  </div>
  <div class="text-sm">
    <?php if (!$agent_vu): ?>
      Aucune nouvelle du PC de l'atelier. Suivez les étapes ci-dessous.
    <?php else: ?>
      Dernier passage le <?= h(date('d/m/Y à H:i', strtotime($statut['le']))) ?>
      <?= ($statut['etat'] ?? '') === 'ok'
          ? '— ' . (int)($statut['nb'] ?? 0) . ' produit(s) transmis.'
          : '— ' . h($statut['message'] ?? 'raison inconnue') ?>
    <?php endif ?>
  </div>
</section>

<!-- ─────────── Installation, étape par étape ─────────── -->
<h3 class="font-headline-md font-bold text-primary mb-3">Installer l'agent</h3>

<?php
$etapes = [
  1 => ['titre' => 'Générer le jeton',
        'fait'  => $token !== ''],
  2 => ['titre' => 'Télécharger le dossier de l\'agent',
        'fait'  => false],
  3 => ['titre' => 'Lancer installer.bat sur le PC de l\'atelier',
        'fait'  => false],
  4 => ['titre' => 'Vérifier que l\'agent répond',
        'fait'  => $agent_vu],
];
?>
<div class="flex flex-col gap-3 mb-6">

  <!-- Étape 1 -->
  <section class="bg-surface rounded-xl border <?= $etapes[1]['fait'] ? 'border-primary' : 'border-outline-variant' ?> p-5">
    <div class="flex items-center gap-3 mb-2">
      <span class="<?= $etapes[1]['fait'] ? 'bg-primary text-on-primary' : 'bg-surface-container-high' ?> rounded-full w-8 h-8 flex items-center justify-center font-bold shrink-0">
        <?= $etapes[1]['fait'] ? '✓' : '1' ?>
      </span>
      <span class="font-bold flex-1"><?= h($etapes[1]['titre']) ?></span>
    </div>
    <p class="text-xs text-on-surface-variant mb-3">
      Il autorise l'agent à lire les produits, et rien d'autre. Révocable à tout moment.
    </p>
    <?php if ($token === ''): ?>
    <form method="post">
      <button name="gen_token" value="1" class="bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm">Générer le jeton</button>
    </form>
    <?php else: ?>
    <div class="bg-surface-container-low rounded-xl p-3">
      <div class="text-xs text-on-surface-variant mb-1">URL de récupération — à coller à l'étape 3</div>
      <code class="text-xs break-all" id="url-recup"><?= h($url_recup) ?></code>
    </div>
    <div class="flex flex-wrap gap-2 mt-3">
      <button type="button" id="copier-url" class="bg-surface-container text-on-surface rounded-full px-5 py-2 font-bold text-sm flex items-center gap-2">
        <span class="material-symbols-outlined text-base">content_copy</span>Copier l'URL
      </button>
      <form method="post" onsubmit="return confirm('Générer un nouveau jeton ? L\'ancien cessera de fonctionner.')">
        <button name="gen_token" value="1" class="bg-surface-container text-on-surface rounded-full px-5 py-2 font-bold text-sm">Régénérer</button>
      </form>
    </div>
    <?php endif ?>
  </section>

  <!-- Étape 2 -->
  <section class="bg-surface rounded-xl border border-outline-variant p-5">
    <div class="flex items-center gap-3 mb-2">
      <span class="bg-surface-container-high rounded-full w-8 h-8 flex items-center justify-center font-bold shrink-0">2</span>
      <span class="font-bold flex-1">Télécharger le dossier de l'agent</span>
    </div>
    <p class="text-xs text-on-surface-variant mb-3">
      Un fichier ZIP. À copier sur le PC de l'atelier — clé USB, e-mail, peu importe.
    </p>
    <a href="telecharger_agent.php" class="bg-primary text-on-primary rounded-full px-6 py-3 font-bold text-sm inline-flex items-center gap-2">
      <span class="material-symbols-outlined">download</span>agent-balance.zip
    </a>
  </section>

  <!-- Étape 3 -->
  <section class="bg-surface rounded-xl border border-outline-variant p-5">
    <div class="flex items-center gap-3 mb-2">
      <span class="bg-surface-container-high rounded-full w-8 h-8 flex items-center justify-center font-bold shrink-0">3</span>
      <span class="font-bold flex-1">Lancer <code class="text-sm">installer.bat</code></span>
    </div>
    <p class="text-xs text-on-surface-variant mb-2">Sur le PC de l'atelier, pas sur votre téléphone.</p>
    <ol class="text-sm flex flex-col gap-2 list-decimal pl-5">
      <li>Décompressez le ZIP, par exemple dans <code class="text-xs">C:\TracaBoucher\</code>.</li>
      <li>Clic droit sur <code class="text-xs">installer.bat</code> → <strong>Exécuter en tant qu'administrateur</strong>.</li>
      <li>Collez l'<strong>URL de récupération</strong> de l'étape 1 quand il la demande.</li>
      <li>Indiquez le <strong>dossier surveillé par DFS</strong> — votre installateur le connaît.</li>
      <li>Le mot de passe MySQL de DFS est facultatif : il ne sert qu'aux sauvegardes.</li>
    </ol>
    <p class="text-xs text-on-surface-variant mt-3">
      L'installateur planifie l'agent toutes les deux minutes, fait un essai, et affiche
      <strong>OK</strong> ou la raison de l'échec.
    </p>
  </section>

  <!-- Étape 4 -->
  <section class="bg-surface rounded-xl border <?= $agent_vu ? 'border-primary' : 'border-outline-variant' ?> p-5">
    <div class="flex items-center gap-3 mb-2">
      <span class="<?= $agent_vu ? 'bg-primary text-on-primary' : 'bg-surface-container-high' ?> rounded-full w-8 h-8 flex items-center justify-center font-bold shrink-0">
        <?= $agent_vu ? '✓' : '4' ?>
      </span>
      <span class="font-bold flex-1">Vérifier que l'agent répond</span>
    </div>
    <?php if ($agent_vu): ?>
    <p class="text-sm">Il a donné signe de vie le <?= h(date('d/m/Y à H:i', strtotime($statut['le']))) ?>. L'installation est terminée.</p>
    <?php else: ?>
    <p class="text-sm text-on-surface-variant mb-3">
      Comptez deux minutes après l'installation, puis rechargez cette page.
    </p>
    <a href="materiel.php" class="bg-surface-container text-on-surface rounded-full px-5 py-2 font-bold text-sm">Recharger</a>
    <?php endif ?>
  </section>
</div>

<!-- ─────────── Inventaire de la base DFS ─────────── -->
<h3 class="font-headline-md font-bold text-primary mb-3">Base de la balance</h3>
<section class="bg-surface rounded-xl border border-outline-variant p-5 mb-5">
  <p class="text-xs text-on-surface-variant mb-3">
    Pour récupérer automatiquement les étiquettes pesées, il faut savoir dans quelle table
    DFS les range. L'agent peut le dire : il liste les tables de sa base et leur volume.
    Aucune donnée n'est copiée, seulement des noms et des comptages.
  </p>

  <?php if (!is_array($inventaire) || empty($inventaire['tables'])): ?>
  <div class="bg-surface-container-low rounded-xl p-4 text-sm">
    <p class="mb-2">Pas encore d'inventaire.</p>
    <p class="text-xs text-on-surface-variant">
      Il arrivera au prochain passage de l'agent, une fois celui-ci mis à jour
      (le ZIP de l'étape 2 contient la version qui sait le faire).
    </p>
  </div>
  <?php else: ?>
  <p class="text-xs text-on-surface-variant mb-2">
    Relevé le <?= h(date('d/m/Y à H:i', strtotime($inventaire['le'] ?? 'now'))) ?> —
    <?= count($inventaire['tables']) ?> table(s).
  </p>
  <div class="border border-outline-variant rounded-xl overflow-hidden max-h-96 overflow-y-auto">
    <table class="w-full text-sm">
      <thead class="bg-surface-container-low sticky top-0">
        <tr><th class="text-left px-3 py-2">Table</th><th class="text-right px-3 py-2">Lignes</th></tr>
      </thead>
      <tbody>
        <?php foreach ($inventaire['tables'] as $t): ?>
        <tr class="border-t border-outline-variant">
          <td class="px-3 py-2 font-mono text-xs"><?= h((string)($t['nom'] ?? '')) ?></td>
          <td class="px-3 py-2 text-right"><?= number_format((int)($t['lignes'] ?? 0), 0, ',', ' ') ?></td>
        </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
  <?php endif ?>
</section>

<section class="bg-surface rounded-xl border border-outline-variant p-5">
  <h3 class="font-headline-md font-bold mb-1">Ma réponse à la question de l'agent</h3>
  <p class="text-xs text-on-surface-variant mb-3">
    L'écran d'import demande une fois si l'agent est installé. Vous avez répondu :
    <strong><?= agent_question_reglee($moi) ? 'oui, ne plus me le demander' : 'pas encore répondu' ?></strong>.
  </p>
  <form method="post" class="flex flex-wrap gap-2">
    <?php if (agent_question_reglee($moi)): ?>
    <button name="oublier" value="1" class="bg-surface-container text-on-surface rounded-full px-5 py-2 font-bold text-sm">Me reposer la question</button>
    <?php else: ?>
    <button name="declare_installe" value="1" class="bg-surface-container text-on-surface rounded-full px-5 py-2 font-bold text-sm">Ne plus me le demander</button>
    <?php endif ?>
  </form>
</section>

<script>
document.getElementById('copier-url')?.addEventListener('click', function () {
  var url = document.getElementById('url-recup');
  navigator.clipboard?.writeText(url.textContent.trim()).then(function () {
    var b = document.getElementById('copier-url');
    b.lastChild.textContent = 'Copié';
  });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
