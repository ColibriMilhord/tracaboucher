<?php
// ============================================================
//  Diagnostic de configuration — à utiliser quand plus rien ne marche.
//
//  Cette page est volontairement autonome : elle n'inclut ni db.php ni
//  auth.php, pour continuer à répondre quand justement la connexion à
//  la base échoue. Elle se connecte à MySQL SANS nommer de base, ce qui
//  réussit même si DB_NAME est faux, puis demande au serveur quelles
//  bases cet utilisateur voit réellement et lesquelles portent les
//  tables de TraçaBoucher et celles du portail CAUSSELOT.
//
//  Elle ne montre jamais de mot de passe, et reste fermée tant que
//  DIAGNOSTIC_CLE n'est pas défini dans config.local.php.
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/version.php';

function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES); }

$ouverte = defined('DIAGNOSTIC_CLE') && DIAGNOSTIC_CLE !== '';
$autorise = $ouverte && hash_equals(DIAGNOSTIC_CLE, (string)($_GET['cle'] ?? ''));

// Tables qui identifient chaque base sans ambiguïté.
const REPERES = [
    'lots_entree'  => 'TraçaBoucher',
    'utilisateurs' => 'Comptes',
    'sessions'     => 'Sessions partagées',
];

$erreur = null;
$bases  = [];      // nom de base => [table => présente]
$vues   = [];      // bases visibles, même vides

if ($autorise) {
    try {
        // Pas de dbname : la connexion réussit même si DB_NAME est faux.
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        foreach ($pdo->query('SHOW DATABASES') as $r) {
            $nom = (string)reset($r);
            if (in_array($nom, ['information_schema', 'performance_schema', 'mysql', 'sys'], true)) continue;
            $vues[] = $nom;
            $bases[$nom] = array_fill_keys(array_keys(REPERES), false);
        }

        // information_schema ne montre que ce sur quoi l'utilisateur a des droits :
        // c'est donc aussi un test d'accès, pas seulement d'existence.
        $q = $pdo->prepare(
            'SELECT TABLE_SCHEMA, TABLE_NAME FROM information_schema.TABLES
              WHERE TABLE_NAME IN (' . implode(',', array_fill(0, count(REPERES), '?')) . ')'
        );
        $q->execute(array_keys(REPERES));
        foreach ($q as $r) {
            $bases[$r['TABLE_SCHEMA']][$r['TABLE_NAME']] = true;
            if (!in_array($r['TABLE_SCHEMA'], $vues, true)) $vues[] = $r['TABLE_SCHEMA'];
        }
    } catch (PDOException $ex) {
        error_log('Diagnostic : connexion MySQL impossible — ' . $ex->getMessage());
        $erreur = match ((int)($ex->errorInfo[1] ?? 0)) {
            1045 => "L'utilisateur MySQL ou son mot de passe est refusé (DB_USER / DB_PASS).",
            2002, 2003 => 'Le serveur MySQL est injoignable (DB_HOST).',
            default => 'Le serveur MySQL a refusé la connexion (code '
                     . (int)($ex->errorInfo[1] ?? 0) . ').',
        };
    }
}

// Ce que la configuration devrait dire, d'après ce que le serveur montre.
// Deux passes : la base de TraçaBoucher se reconnaît à `lots_entree`, et
// c'est seulement une fois qu'on la connaît qu'on peut désigner l'autre
// base à comptes comme étant celle du portail.
$base_traca = null;
foreach ($bases as $nom => $t) {
    if ($t['lots_entree']) { $base_traca = $nom; break; }
}
$base_comptes = null;
foreach ($bases as $nom => $t) {
    // La base du portail porte les sessions partagées ; à défaut, c'est
    // simplement une autre base que celle de TraçaBoucher avec des comptes.
    if ($nom === $base_traca || !$t['utilisateurs']) continue;
    if ($t['sessions']) { $base_comptes = $nom; break; }
    $base_comptes = $base_comptes ?? $nom;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Diagnostic – <?= e(APP_NAME) ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,"Public Sans",sans-serif;background:#f9f8f4;color:#2c2c2c;
       padding:20px;line-height:1.55}
  .box{background:#fff;border:1px solid #e0e0e0;border-radius:16px;padding:24px;max-width:760px;
       margin:0 auto 16px}
  h1{font-size:20px;color:#2c5530;margin-bottom:4px}
  h2{font-size:15px;margin:20px 0 8px;color:#2c5530}
  .sub{font-size:12px;color:#6a6a6a;margin-bottom:8px}
  table{width:100%;border-collapse:collapse;margin-top:8px;font-size:14px}
  th,td{text-align:left;padding:8px 6px;border-bottom:1px solid #eee;vertical-align:top}
  th{font-size:12px;color:#6a6a6a;font-weight:600}
  code{background:#f0f0f0;padding:1px 5px;border-radius:4px;font-size:13px}
  .ok{color:#1b5e20;font-weight:700}
  .ko{color:#93000a}
  .err{background:#ffdad6;color:#93000a;padding:12px 14px;border-radius:10px;margin-top:12px}
  .info{background:#e7f3ea;color:#1b5e20;padding:12px 14px;border-radius:10px;margin-top:12px}
  .conseil{background:#fff8e1;padding:12px 14px;border-radius:10px;margin-top:12px;font-size:14px}
  ul{margin:8px 0 0 20px}
</style>
</head>
<body>
<div class="box">
  <h1>Diagnostic de configuration</h1>
  <div class="sub"><?= e(APP_NAME) ?> — <?= e(version_affichee()) ?></div>

<?php if (!$ouverte): ?>
  <div class="err">
    Page fermée. Pour l'ouvrir, ajoutez une ligne dans <code>config.local.php</code> :
    <br><br><code>define('DIAGNOSTIC_CLE', 'un-mot-long-et-unique');</code>
    <br><br>puis appelez <code>diagnostic.php?cle=un-mot-long-et-unique</code>.
    Retirez la ligne quand vous avez fini.
  </div>
<?php elseif (!$autorise): ?>
  <div class="err">Clé absente ou incorrecte.</div>
<?php else: ?>

  <h2>Ce que lit l'application</h2>
  <table>
    <tr><th>DB_HOST</th><td><code><?= e(DB_HOST) ?></code></td></tr>
    <tr><th>DB_USER</th><td><code><?= e(DB_USER) ?></code></td></tr>
    <tr><th>DB_NAME</th><td><code><?= e(DB_NAME) ?></code> <span class="sub">base de TraçaBoucher</span></td></tr>
    <tr><th>DB_NAME_CAUSSELOT</th><td><code><?= e(DB_NAME_CAUSSELOT) ?></code> <span class="sub">base des comptes du portail</span></td></tr>
    <tr><th>CAUSSELOT_URL</th><td><code><?= e(CAUSSELOT_URL !== '' ? CAUSSELOT_URL : '—') ?></code></td></tr>
  </table>

  <?php if ($erreur !== null): ?>
  <div class="err"><strong>Connexion au serveur MySQL impossible.</strong><br><?= e($erreur) ?></div>
  <?php else: ?>

  <h2>Ce que le serveur MySQL montre à cet utilisateur</h2>
  <p class="sub">Une base absente de cette liste n'est pas associée à <code><?= e(DB_USER) ?></code> dans hPanel.</p>
  <table>
    <tr>
      <th>Base</th>
      <?php foreach (REPERES as $table => $libelle): ?>
      <th><?= e($libelle) ?><br><span class="sub"><?= e($table) ?></span></th>
      <?php endforeach ?>
    </tr>
    <?php foreach ($vues as $nom): $t = $bases[$nom] ?? array_fill_keys(array_keys(REPERES), false); ?>
    <tr>
      <td><code><?= e($nom) ?></code></td>
      <?php foreach (array_keys(REPERES) as $table): ?>
      <td class="<?= $t[$table] ? 'ok' : 'ko' ?>"><?= $t[$table] ? 'oui' : '—' ?></td>
      <?php endforeach ?>
    </tr>
    <?php endforeach ?>
    <?php if (!$vues): ?>
    <tr><td colspan="<?= count(REPERES) + 1 ?>">Aucune base visible pour cet utilisateur.</td></tr>
    <?php endif ?>
  </table>

  <h2>Ce qu'il faut écrire dans config.local.php</h2>
  <?php if ($base_traca === null && $base_comptes === null): ?>
  <div class="err">
    Aucune base visible ne contient les tables attendues. Vérifiez dans hPanel que
    <code><?= e(DB_USER) ?></code> est bien associé aux bases, et que les migrations
    ont été passées.
  </div>
  <?php else: ?>
  <div class="info">
    <?php if ($base_traca !== null): ?>
    <code>define('DB_NAME', '<?= e($base_traca) ?>');</code><br>
    <?php else: ?>
    <span class="ko">Aucune base visible ne contient <code>lots_entree</code> : DB_NAME reste à déterminer.</span><br>
    <?php endif ?>
    <?php if ($base_comptes !== null): ?>
    <code>define('DB_NAME_CAUSSELOT', '<?= e($base_comptes) ?>');</code>
    <?php else: ?>
    <span class="ko">Aucune autre base visible ne contient <code>utilisateurs</code> :
    l'utilisateur MySQL n'a probablement pas encore accès à la base du portail.</span>
    <?php endif ?>
  </div>
  <?php endif ?>

  <div class="conseil">
    <strong>Rappel</strong>
    <ul>
      <li><code>DB_NAME</code> est la base de TraçaBoucher : c'est elle qui porte <code>lots_entree</code>.</li>
      <li><code>DB_NAME_CAUSSELOT</code> est la base d'app.causselot.fr : elle porte <code>utilisateurs</code> et <code>sessions</code>. Sa valeur exacte est le <code>DB_NAME</code> du <code>config.local.php</code> d'app.causselot.fr.</li>
      <li>Les deux bases doivent être associées au même utilisateur MySQL dans hPanel, sinon la connexion unique reste inactive (TraçaBoucher continue alors sur ses comptes locaux).</li>
      <li>Retirez <code>DIAGNOSTIC_CLE</code> de <code>config.local.php</code> une fois le réglage terminé.</li>
    </ul>
  </div>

  <?php endif ?>
<?php endif ?>
</div>
</body>
</html>
