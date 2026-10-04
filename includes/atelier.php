<?php
// ============================================================
//  Atelier : cycle de vie d'un lot de fabrication.
//
//  Un lot s'OUVRE avant de fabriquer — le numéro existe dès cet
//  instant, donc on peut étiqueter — et se CLÔTURE à la fin, quand on
//  connaît enfin le poids produit. Entre les deux, plusieurs lots
//  peuvent cohabiter : c'est le quotidien d'un atelier.
//
//  `quantite` n'est jamais lue tant que le lot est ouvert : c'est
//  `statut` qui porte le sens, pas une valeur nulle ou à zéro.
// ============================================================
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csv.php';

const LOT_OUVERT  = 'ouvert';
const LOT_CLOTURE = 'cloture';

// La migration 007 n'est peut-être pas passée : sans ce garde-fou,
// l'application tomberait avant qu'on puisse aller la lancer.
function atelier_installe(): bool {
    static $ok = null;
    if ($ok === null) {
        try { db()->query('SELECT statut FROM lots_sortie LIMIT 1'); $ok = true; }
        catch (PDOException $e) { $ok = false; }
    }
    return $ok;
}

function etiquettes_installe(): bool {
    static $ok = null;
    if ($ok === null) {
        try { db()->query('SELECT 1 FROM etiquettes LIMIT 1'); $ok = true; }
        catch (PDOException $e) { $ok = false; }
    }
    return $ok;
}

// ============================================================
//  Préférences par utilisateur
// ============================================================

/**
 * Cache des préférences, partagé entre lecture et écriture : sans cela,
 * un écran qui enregistre une préférence puis la relit dans la même
 * requête afficherait encore l'ancienne valeur.
 */
function &cache_reglages_utilisateur(): array {
    static $cache = [];
    return $cache;
}

function reglage_utilisateur(int $uid, string $cle, string $defaut = ''): string {
    $cache = &cache_reglages_utilisateur();
    if (!isset($cache[$uid])) {
        $cache[$uid] = [];
        try {
            $q = db()->prepare('SELECT cle, valeur FROM reglages_utilisateur WHERE utilisateur_id = ?');
            $q->execute([$uid]);
            foreach ($q as $r) { $cache[$uid][$r['cle']] = (string)$r['valeur']; }
        } catch (PDOException $e) { /* migration pas encore passée */ }
    }
    return $cache[$uid][$cle] ?? $defaut;
}

function definir_reglage_utilisateur(int $uid, string $cle, string $valeur): void {
    try {
        db()->prepare('INSERT INTO reglages_utilisateur (utilisateur_id, cle, valeur) VALUES (?,?,?)
                       ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)')
            ->execute([$uid, $cle, $valeur]);
    } catch (PDOException $e) {
        error_log('Préférence utilisateur non enregistrée : ' . $e->getMessage());
        return;
    }
    $cache = &cache_reglages_utilisateur();
    $cache[$uid] ??= [];
    $cache[$uid][$cle] = $valeur;
}

/**
 * L'utilisateur a-t-il déjà répondu à la question de l'agent balance ?
 * Répondre « oui, il est installé » clôt le sujet pour lui : on ne le
 * relance pas à chaque import.
 */
function agent_question_reglee(array $moi): bool {
    return reglage_utilisateur((int)$moi['id'], 'agent_installe') === 'oui';
}

// ============================================================
//  Ouvrir, lister, clôturer
// ============================================================

/** Les lots encore ouverts, le plus récent d'abord. */
function lots_ouverts(): array {
    if (!atelier_installe()) return [];
    $lots = db()->query(
        "SELECT * FROM lots_sortie WHERE statut = 'ouvert'
          ORDER BY date_fabrication DESC, id DESC"
    )->fetchAll();

    foreach ($lots as &$l) {
        $l['du_jour']  = $l['date_fabrication'] === date('Y-m-d');
        $l['sources']  = entrees_de_sortie((int)$l['id']);
        $l['etiq']     = etiquettes_du_lot((int)$l['id']);
        $l['poids_etiq'] = array_sum(array_map(fn($e) => (float)$e['poids_kg'], $l['etiq']));
    }
    unset($l);
    return $lots;
}

function nb_lots_ouverts(): int {
    if (!atelier_installe()) return 0;
    return (int)db()->query("SELECT COUNT(*) FROM lots_sortie WHERE statut = 'ouvert'")->fetchColumn();
}

/**
 * Ouvre un lot : date, produit, matières premières. Rien d'autre n'est
 * demandé — tout le reste se sait à la fin.
 *
 * @param array $liens entree_id => quantité utilisée (kg), ou null
 * @return array{id: int, num_lot: string}
 */
function ouvrir_lot(string $date_fab, string $produit, ?int $produit_id,
                    array $liens, array $moi, array $defauts = []): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $num = generer_num_sortie($date_fab);

        // `quantite` reste à 0 tant que le lot est ouvert : aucun écran
        // ne la lit dans cet état, c'est `statut` qui fait foi. On ne
        // force pas NULL, la colonne du registre n'étant pas forcément
        // nullable et n'ayant pas à être retouchée pour si peu.
        $pdo->prepare(
            'INSERT INTO lots_sortie
             (num_lot, date_fabrication, produit, produit_id, quantite, unite,
              conditionnement, conservation, dlc, cree_par, statut, ouvert_le, ouvert_par)
             VALUES (?,?,?,?,0,?,?,?,?,?,?,NOW(),?)'
        )->execute([
            $num, $date_fab, $produit, $produit_id ?: null,
            $defauts['unite'] ?? 'kg',
            $defauts['conditionnement'] ?? 'barquette',
            $defauts['conservation'] ?? 'froid_positif',
            $defauts['dlc'] ?? null,
            $moi['nom'] ?? '', LOT_OUVERT, $moi['nom'] ?? '',
        ]);
        $id = (int)$pdo->lastInsertId();

        $ins = $pdo->prepare('INSERT INTO lots_sortie_entrees (sortie_id, entree_id, quantite_kg) VALUES (?,?,?)');
        foreach ($liens as $eid => $q_kg) { $ins->execute([$id, (int)$eid, $q_kg]); }

        $pdo->commit();
        return ['id' => $id, 'num_lot' => $num];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

/**
 * Clôture un lot. Renvoie false si quelqu'un l'a déjà clôturé entre
 * temps — deux personnes travaillent dans l'atelier, le dernier arrivé
 * ne doit pas écraser le travail du premier sans le savoir.
 */
function cloturer_lot(int $id, float $quantite, string $unite, ?string $dlc, array $moi): bool {
    $q = db()->prepare(
        "UPDATE lots_sortie
            SET quantite = ?, unite = ?, dlc = ?, statut = ?, cloture_le = NOW(), cloture_par = ?
          WHERE id = ? AND statut = ?"
    );
    $q->execute([$quantite, $unite, $dlc ?: null, LOT_CLOTURE, $moi['nom'] ?? '', $id, LOT_OUVERT]);
    return $q->rowCount() > 0;
}

/** Rouvre un lot clôturé — réservé à l'administrateur, et tracé. */
function rouvrir_lot(int $id, array $moi): bool {
    $q = db()->prepare("UPDATE lots_sortie SET statut = ?, cloture_le = NULL, cloture_par = NULL
                         WHERE id = ? AND statut = ?");
    $q->execute([LOT_OUVERT, $id, LOT_CLOTURE]);
    if ($q->rowCount() > 0) {
        error_log('Lot ' . $id . ' rouvert par ' . ($moi['nom'] ?? '?'));
        return true;
    }
    return false;
}

/** Un lot est-il encore modifiable ? */
function lot_ouvert(array $lot): bool {
    return ($lot['statut'] ?? LOT_CLOTURE) === LOT_OUVERT;
}

/** Quantité affichable, ou null tant qu'elle n'est pas connue. */
function quantite_connue(array $lot): ?float {
    return lot_ouvert($lot) ? null : (float)$lot['quantite'];
}

// ============================================================
//  Étiquettes remontées de la balance
// ============================================================

function etiquettes_du_lot(int $sortie_id): array {
    if (!etiquettes_installe()) return [];
    $q = db()->prepare('SELECT * FROM etiquettes WHERE sortie_id = ? ORDER BY date_etiquette, id');
    $q->execute([$sortie_id]);
    return $q->fetchAll();
}

function etiquettes_orphelines(): array {
    if (!etiquettes_installe()) return [];
    return db()->query('SELECT * FROM etiquettes WHERE sortie_id IS NULL
                        ORDER BY date_etiquette DESC, produit, id')->fetchAll();
}

/** Empreinte d'une étiquette : réimporter le même fichier ne duplique rien. */
function empreinte_etiquette(string $num_lot, string $produit, ?string $date, ?float $poids, int $occurrence): string {
    return hash('sha256', implode('|', [
        mb_strtolower(trim($num_lot)), mb_strtolower(trim($produit)),
        (string)$date, $poids === null ? '' : number_format($poids, 3, '.', ''), $occurrence,
    ]));
}

/**
 * Rôles de colonnes attendus dans un export d'étiquettes, et les mots
 * qui les trahissent dans un en-tête. Espagnol inclus : DFS est un
 * logiciel Dibal, ses exports le sont parfois.
 */
const ETIQ_ROLES = [
    'num_lot' => ['lot', 'lote', 'batch'],
    'produit' => ['produit', 'designation', 'descripcion', 'libelle', 'article', 'articulo'],
    'plu'     => ['plu', 'code article', 'idarticulo', 'codigo'],
    'date'    => ['date', 'fecha', 'jour'],
    'poids'   => ['poids', 'peso', 'quantite', 'cantidad', 'kg', 'weight'],
];

/**
 * Colonnes retenues : les intitulés d'abord, le contenu ensuite.
 *
 * Reconnaissance par contenu, propre aux étiquettes : une colonne de
 * dates est la date ; une colonne de nombres décimaux compris entre
 * quelques grammes et quelques dizaines de kilos est le poids ; une
 * colonne de quatre chiffres est le PLU ; une colonne qui ressemble à
 * « 031026-1 » est le numéro de lot ; le texte le plus abondant est le
 * produit.
 */
function etiquettes_colonnes(array $entete, array $cellules, array $impose = []): array {
    $cols = csv_devine_par_entete($entete, ETIQ_ROLES);
    $profil = csv_profiler($cellules);
    $nom = fn(int $i) => $entete[$i] ?? (string)$i;
    $pris = array_filter($cols, fn($v) => $v !== null);

    $libre = function (int $i) use ($pris, $nom): bool {
        return !in_array($nom($i), $pris, true);
    };

    if ($cols['date'] === null) {
        foreach ($profil as $i => $p) {
            if (csv_colonne_est($p, 'dates') && $libre($i)) { $cols['date'] = $nom($i); break; }
        }
    }
    if ($cols['num_lot'] === null) {
        foreach ($profil as $i => $p) {
            // « 031026-1 », « JB-280926 » : chiffres et tirets, jamais un nombre seul.
            if (preg_match('/^[A-Z]{0,3}-?\d{4,8}-\d{1,3}$/i', (string)$p['exemple']) && $libre($i)) {
                $cols['num_lot'] = $nom($i); break;
            }
        }
    }
    if ($cols['plu'] === null) {
        foreach ($profil as $i => $p) {
            if (csv_colonne_est($p, 'nombres') && $p['entiers'] === $p['nombres']
                && preg_match('/^\d{4}$/', (string)$p['exemple']) && $libre($i)) {
                $cols['plu'] = $nom($i); break;
            }
        }
    }
    if ($cols['poids'] === null) {
        foreach ($profil as $i => $p) {
            if (!csv_colonne_est($p, 'nombres') || !$libre($i)) continue;
            // Un poids de barquette : décimal, et pas un prix à trois chiffres.
            if ($p['entiers'] < $p['nombres'] && (float)$p['max'] <= 200) { $cols['poids'] = $nom($i); break; }
        }
    }
    if ($cols['produit'] === null) {
        $meilleur = 0;
        foreach ($profil as $i => $p) {
            if ($p['texte'] > $meilleur && $libre($i)) { $meilleur = $p['texte']; $cols['produit'] = $nom($i); }
        }
    }

    foreach ($impose as $role => $valeur) {
        $valeur = trim((string)$valeur);
        if (array_key_exists($role, $cols)) { $cols[$role] = $valeur !== '' ? $valeur : null; }
    }
    return $cols;
}

/**
 * Interprète les cellules en étiquettes.
 *
 * @return array{lignes: array, ignorees: int, erreurs: string[]}
 */
function etiquettes_interpreter(array $entete, array $cellules, array $cols, string $format_date = 'd/m/Y'): array {
    $i = [];
    foreach (array_keys(ETIQ_ROLES) as $role) { $i[$role] = csv_indice($entete, $cols[$role] ?? null); }

    $erreurs = [];
    // Le produit est le minimum vital : sans lui, une étiquette ne veut
    // rien dire. Le numéro de lot est ce qui permet le rattachement
    // automatique ; sans lui, le rattachement se fera à la main.
    if ($i['produit'] === null && $i['num_lot'] === null) {
        $erreurs[] = "Ni colonne « produit » ni colonne « numéro de lot » : impossible de savoir de quoi parle ce fichier.";
    }
    if ($erreurs) return ['lignes' => [], 'ignorees' => 0, 'erreurs' => $erreurs];

    $lignes = [];
    $ignorees = 0;
    foreach ($cellules as $c) {
        $produit = $i['produit'] !== null ? trim((string)($c[$i['produit']] ?? '')) : '';
        $num     = $i['num_lot'] !== null ? trim((string)($c[$i['num_lot']] ?? '')) : '';
        if ($produit === '' && $num === '') { $ignorees++; continue; }   // ligne de total, pied de page

        $date  = $i['date']  !== null ? csv_date((string)($c[$i['date']] ?? ''), $format_date) : null;
        $poids = $i['poids'] !== null ? csv_nombre((string)($c[$i['poids']] ?? '')) : null;
        $plu   = $i['plu']   !== null ? preg_replace('/\D/', '', (string)($c[$i['plu']] ?? '')) : '';

        $lignes[] = [
            'num_lot'  => mb_substr($num, 0, 40),
            'produit'  => mb_substr($produit, 0, 150),
            'plu'      => $plu !== '' ? substr(str_pad($plu, 4, '0', STR_PAD_LEFT), -4) : null,
            'date'     => $date,
            'poids_kg' => $poids !== null && $poids > 0 ? $poids : null,
        ];
    }
    return ['lignes' => $lignes, 'ignorees' => $ignorees, 'erreurs' => []];
}

/**
 * Lots auxquels une étiquette peut se rattacher.
 *
 * Pas seulement les lots ouverts : un fichier peut arriver après la
 * clôture — c'est même le cas normal quand la saisie est différée de
 * plusieurs jours. On remonte donc aussi les lots récemment clôturés.
 */
function lots_rattachables(int $jours = 60): array {
    if (!atelier_installe()) return [];
    $q = db()->prepare(
        "SELECT * FROM lots_sortie
          WHERE statut = 'ouvert' OR date_fabrication >= ?
          ORDER BY date_fabrication DESC, id DESC"
    );
    $q->execute([date('Y-m-d', strtotime('-' . $jours . ' days'))]);
    return $q->fetchAll();
}

function lot_par_numero(string $num): ?array {
    if (!atelier_installe() || trim($num) === '') return null;
    $q = db()->prepare('SELECT * FROM lots_sortie WHERE num_lot = ? LIMIT 1');
    $q->execute([trim($num)]);
    return $q->fetch() ?: null;
}

/**
 * Un lot sans matière première est un trou dans la chaîne : on sait ce
 * qui est sorti, pas ce qui est entré dedans. C'est le cas d'un lot créé
 * depuis une étiquette, où le fichier de la balance ne dit rien des
 * lots d'entrée. On le signale, et on interdit de clôturer ainsi.
 */
function lot_complet(int $id): bool {
    $q = db()->prepare('SELECT COUNT(*) FROM lots_sortie_entrees WHERE sortie_id = ?');
    $q->execute([$id]);
    return (int)$q->fetchColumn() > 0;
}

/**
 * Crée le lot qu'une étiquette désigne, AVEC LE NUMÉRO DU FICHIER.
 *
 * Le cas : l'artisan a créé le lot directement à la balance, par
 * anticipation, sans passer par l'application. Les barquettes portent
 * déjà « 031026-2 ». Générer un nouveau numéro ici ferait mentir les
 * étiquettes déjà posées — c'est le numéro imprimé qui fait foi, et le
 * registre doit s'y conformer.
 *
 * Le lot naît sans matière première : le fichier de la balance n'en sait
 * rien. Il est donc ouvert et signalé incomplet jusqu'à ce que
 * quelqu'un coche les lots d'entrée dans l'Atelier.
 *
 * @return array{id: int, num_lot: string}
 * @throws RuntimeException si le numéro est déjà pris par un autre lot
 */
function creer_lot_depuis_etiquette(string $num_lot, string $produit, ?string $date,
                                    array $moi, ?int $produit_id = null): array {
    $num = trim($num_lot);
    if ($num === '')     { throw new RuntimeException("L'étiquette ne porte pas de numéro de lot."); }
    if ($produit === '') { throw new RuntimeException("L'étiquette ne porte pas de produit."); }

    $existant = lot_par_numero($num);
    if ($existant) {
        throw new RuntimeException(
            'Le numéro ' . $num . ' est déjà celui de « ' . $existant['produit'] . ' » '
            . '(' . fmt_date($existant['date_fabrication']) . '). Deux lots ne peuvent pas '
            . 'porter le même numéro : rattachez les étiquettes à ce lot, ou corrigez le fichier.'
        );
    }

    $pdo = db();
    $pdo->prepare(
        'INSERT INTO lots_sortie
         (num_lot, date_fabrication, produit, produit_id, quantite, unite,
          conditionnement, conservation, cree_par, statut, ouvert_le, ouvert_par)
         VALUES (?,?,?,?,0,?,?,?,?,?,NOW(),?)'
    )->execute([
        $num, $date ?: date('Y-m-d'), $produit, $produit_id,
        'kg', 'barquette', 'froid_positif',
        $moi['nom'] ?? '', LOT_OUVERT, $moi['nom'] ?? '',
    ]);
    return ['id' => (int)$pdo->lastInsertId(), 'num_lot' => $num];
}

/**
 * Le numéro porté par une étiquette désigne-t-il déjà un AUTRE produit
 * dans le registre ? C'est le signe que l'application et la balance ont
 * attribué le même numéro chacune de son côté — il faut le voir tout de
 * suite, pas le découvrir au contrôle.
 */
function conflit_numero(string $num, string $produit): ?array {
    $lot = lot_par_numero($num);
    if (!$lot) return null;
    return mb_strtolower(trim($lot['produit'])) === mb_strtolower(trim($produit)) ? null : $lot;
}

/**
 * Rattache une étiquette au lot qu'elle désigne.
 *
 * Le numéro de lot du fichier est le signal décisif. À défaut, on
 * cherche un lot du même produit à la même date : c'est le cas du
 * boucher qui a étiqueté à la balance sans y ressaisir le numéro.
 */
function lot_pour_etiquette(array $etiq, array $lots): ?array {
    $num = mb_strtolower(trim((string)$etiq['num_lot']));
    if ($num !== '') {
        foreach ($lots as $l) {
            if (mb_strtolower($l['num_lot']) === $num) return $l;
        }
    }
    $produit = mb_strtolower(trim((string)$etiq['produit']));
    if ($produit === '') return null;

    $candidats = [];
    foreach ($lots as $l) {
        if (mb_strtolower($l['produit']) !== $produit) continue;
        if ($etiq['date'] !== null && $l['date_fabrication'] !== $etiq['date']) continue;
        $candidats[] = $l;
    }
    // Un seul lot possible : on rattache. Deux lots du même produit le
    // même jour, c'est précisément le cas que l'artisan distingue par
    // l'occurrence — on ne tranche pas à sa place.
    return count($candidats) === 1 ? $candidats[0] : null;
}
