<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../version.php';

// ============================================================
//  Connexion
// ============================================================
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES=>false]
            );
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:20px;background:#fee;border:2px solid #7A1C1C;border-radius:12px;margin:20px"><strong>Connexion BD impossible</strong><br>'.htmlspecialchars($e->getMessage()).'<br><br>Vérifiez que <code>config.local.php</code> existe sur le serveur et contient les identifiants MySQL (modèle dans <code>config.example.php</code>)</div>');
        }
    }
    return $pdo;
}

// ============================================================
//  Helpers d'affichage
// ============================================================
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES); }
function fmt_date(?string $d): string { return $d ? date('d/m/Y', strtotime($d)) : '—'; }
function fmt_qte(?float $p, string $unite='kg'): string {
    return $p === null ? '—' : rtrim(rtrim(number_format($p, 3, ',', ' '), '0'), ',').' '.$unite;
}
function fmt_temp(?float $t): string { return $t === null ? '—' : number_format($t, 1, ',', ' ').' °C'; }
function nombre(string $v): ?float {
    $v = trim(str_replace([' ', ','], ['', '.'], $v));
    return $v === '' ? null : (float)$v;
}

const LIB_CONDITIONNEMENT = [
    'barquette' => 'Barquette',
    'sous_vide' => 'Sous vide',
    'vrac'      => 'Vrac',
];
const LIB_CONSERVATION = [
    'froid_positif' => '+2 °C',
    'congele'       => '-18 °C',
];
const LIB_FORME = ['carcasse'=>'Carcasse', 'vrac'=>'Vrac', 'colis'=>'Colis'];
const LIB_ETAT  = ['frais'=>'Frais', 'congele'=>'Congelé'];

// ============================================================
//  Réglages
// ============================================================
function reglage(string $cle, string $defaut=''): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT cle, valeur FROM reglages') as $r) {
            $cache[$r['cle']] = $r['valeur'];
        }
    }
    return $cache[$cle] ?? $defaut;
}

// ============================================================
//  Numérotation des lots
// ============================================================

// Entrée : TYPE-JJMMAA  (ex : JB-100726), puis -2, -3 si collision
function generer_num_entree(int $type_id, string $date_entree): string {
    $pdo = db();
    $q = $pdo->prepare('SELECT code FROM types_matiere WHERE id=?');
    $q->execute([$type_id]);
    $code = $q->fetchColumn();
    if (!$code) return '';

    $base = $code.'-'.date('dmy', strtotime($date_entree));
    return premier_libre($base, 'lots_entree');
}

// Sortie : [prefixe]JJMMAA-n  (ex : 100726-1)
function generer_num_sortie(string $date_fab): string {
    $base = reglage('prefixe_sortie').date('dmy', strtotime($date_fab));
    $pdo  = db();
    $q = $pdo->prepare('SELECT num_lot FROM lots_sortie WHERE num_lot LIKE ?');
    $q->execute([$base.'-%']);
    $max = 0;
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $n) {
        $suf = (int)substr($n, strlen($base) + 1);
        if ($suf > $max) $max = $suf;
    }
    return $base.'-'.($max + 1);
}

// Renvoie $base s'il est libre, sinon $base-2, $base-3...
function premier_libre(string $base, string $table): string {
    $pdo = db();
    $q = $pdo->prepare("SELECT COUNT(*) FROM `$table` WHERE num_lot=?");
    for ($i = 1; $i <= 99; $i++) {
        $cand = $i === 1 ? $base : $base.'-'.$i;
        $q->execute([$cand]);
        if ((int)$q->fetchColumn() === 0) return $cand;
    }
    return $base.'-'.uniqid();
}

// ============================================================
//  Upload
// ============================================================
const ERREURS_UPLOAD = [
    UPLOAD_ERR_INI_SIZE   => 'La photo dépasse la taille autorisée par le serveur.',
    UPLOAD_ERR_FORM_SIZE  => 'La photo est trop lourde.',
    UPLOAD_ERR_PARTIAL    => 'L\'envoi de la photo a été interrompu. Réessayez.',
    UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire introuvable sur le serveur.',
    UPLOAD_ERR_CANT_WRITE => 'Écriture impossible sur le serveur.',
    UPLOAD_ERR_EXTENSION  => 'Envoi bloqué par une extension PHP.',
];

const PHOTO_COTE_MAX = 1600;

/**
 * Réduit une image avec GD et la ré-encode en JPEG. Renvoie null si GD est absent,
 * si le format n'est pas une image, ou si le fichier est déjà assez petit.
 * Sert de filet quand le navigateur n'a pas pu réduire lui-même.
 */
function reduire_image(string $binaire): ?string {
    if (!function_exists('imagecreatefromstring')) return null;
    $infos = @getimagesizefromstring($binaire);
    if (!$infos || $infos[0] < 1 || $infos[1] < 1) return null;

    [$l, $h] = $infos;
    if ($l <= PHOTO_COTE_MAX && $h <= PHOTO_COTE_MAX && strlen($binaire) < 600 * 1024) {
        return null;                                   // inutile d'y toucher
    }
    $src = @imagecreatefromstring($binaire);
    if ($src === false) return null;

    // Redressement d'après l'orientation EXIF, sinon les photos partent couchées.
    if (($infos[2] ?? 0) === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($binaire));
        $angle = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 0] ?? 0;
        if ($angle !== 0) {
            $pivote = @imagerotate($src, $angle, 0);
            if ($pivote !== false) { imagedestroy($src); $src = $pivote; }
            [$l, $h] = [imagesx($src), imagesy($src)];
        }
    }

    $ratio = min(1, PHOTO_COTE_MAX / max($l, $h));
    $nl = max(1, (int)round($l * $ratio));
    $nh = max(1, (int)round($h * $ratio));

    $dst = imagecreatetruecolor($nl, $nh);
    imagefilledrectangle($dst, 0, 0, $nl, $nh, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nl, $nh, $l, $h);
    imagedestroy($src);

    ob_start();
    $ok = imagejpeg($dst, null, 82);
    $sortie = ob_get_clean();
    imagedestroy($dst);

    return ($ok && $sortie !== '') ? $sortie : null;
}

// Écrit un fichier dans uploads/<sous_dossier>/ et renvoie son chemin relatif.
function ecrire_fichier(string $contenu, string $ext, string $sous_dossier): ?string {
    $dir = UPLOAD_DIR . $sous_dossier . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $nom = uniqid($sous_dossier . '_', true) . '.' . $ext;
    return @file_put_contents($dir . $nom, $contenu) !== false ? $sous_dossier . '/' . $nom : null;
}

/**
 * Enregistre la photo d'un lot, qu'elle arrive redimensionnée par le navigateur
 * (champ caché `photo_data`, une data-URL JPEG) ou en fichier brut (repli si le
 * JavaScript n'a pas pu lire l'image : HEIC non décodable, permission refusée…).
 *
 * @return array{chemin: ?string, erreur: ?string}
 */
function enregistrer_photo(?array $file, ?string $data_url, string $sous_dossier = 'entrees'): array {
    // ── Voie normale : image déjà réduite côté navigateur
    if ($data_url !== null && $data_url !== '') {
        if (!preg_match('#^data:image/jpeg;base64,#', $data_url)) {
            return ['chemin' => null, 'erreur' => 'Format de photo inattendu.'];
        }
        $bin = base64_decode(substr($data_url, strlen('data:image/jpeg;base64,')), true);
        if ($bin === false || $bin === '') {
            return ['chemin' => null, 'erreur' => 'Photo illisible, reprenez-la.'];
        }
        if (strlen($bin) > MAX_UPLOAD_SIZE) {
            return ['chemin' => null, 'erreur' => 'Photo trop lourde après réduction.'];
        }
        if (@imagecreatefromstring($bin) === false) {
            return ['chemin' => null, 'erreur' => 'Le fichier reçu n\'est pas une image valide.'];
        }
        $chemin = ecrire_fichier($bin, 'jpg', $sous_dossier);
        return ['chemin' => $chemin,
                'erreur' => $chemin ? null : 'Enregistrement de la photo impossible sur le serveur.'];
    }

    // ── Repli : fichier brut
    if ($file === null || ($file['name'] ?? '') === '') {
        return ['chemin' => null, 'erreur' => null];          // aucune photo fournie
    }
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) return ['chemin' => null, 'erreur' => null];
    if ($code !== UPLOAD_ERR_OK) {
        return ['chemin' => null, 'erreur' => ERREURS_UPLOAD[$code] ?? 'Envoi de la photo impossible.'];
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['chemin' => null,
                'erreur' => 'Photo trop lourde (' . round($file['size'] / 1048576, 1) . ' Mo, maximum '
                            . round(MAX_UPLOAD_SIZE / 1048576) . ' Mo).'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        return ['chemin' => null,
                'erreur' => 'Format non accepté (' . h($ext) . '). Autorisés : ' . implode(', ', ALLOWED_EXT) . '.'];
    }

    // Le navigateur n'a pas su réduire l'image : on le fait ici, où la mémoire ne manque pas.
    if ($ext !== 'pdf') {
        $brut = @file_get_contents($file['tmp_name']);
        if ($brut !== false && ($reduit = reduire_image($brut)) !== null) {
            $chemin = ecrire_fichier($reduit, 'jpg', $sous_dossier);
            return ['chemin' => $chemin,
                    'erreur' => $chemin ? null : 'Enregistrement de la photo impossible sur le serveur.'];
        }
    }

    $dir = UPLOAD_DIR . $sous_dossier . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $nom = uniqid($sous_dossier . '_', true) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir . $nom)) {
        return ['chemin' => $sous_dossier . '/' . $nom, 'erreur' => null];
    }
    return ['chemin' => null, 'erreur' => 'Enregistrement de la photo impossible sur le serveur.'];
}

// ============================================================
//  Référentiel produits (PLU / EAN pour la balance-étiqueteuse)
// ============================================================

// Clé de contrôle EAN-13 : somme pondérée 1/3 des 12 premiers chiffres.
function cle_ean13(string $douze): int {
    $somme = 0;
    for ($i = 0; $i < 12; $i++) {
        $somme += (int)$douze[$i] * ($i % 2 === 0 ? 1 : 3);
    }
    return (10 - $somme % 10) % 10;
}

/**
 * EAN-13 interne à poids variable, format « 2 CCCCC PPPPPP K » :
 *   2       préfixe réservé à la circulation interne (GS1 20-29)
 *   CCCCC   code article, dérivé du PLU
 *   PPPPPP  poids ou prix, complété par la balance au moment de l'impression
 *   K       clé de contrôle
 * Aucune démarche GS1 nécessaire tant que les produits restent en vente directe.
 */
function ean13_interne(string $plu): string {
    $douze = '2' . str_pad(substr(preg_replace('/\D/', '', $plu), 0, 5), 5, '0', STR_PAD_LEFT) . '000000';
    return $douze . cle_ean13($douze);
}

function ean13_valide(string $ean): bool {
    return (bool)preg_match('/^\d{13}$/', $ean) && (int)$ean[12] === cle_ean13(substr($ean, 0, 12));
}

// Prochain PLU libre sur 4 chiffres (0001 à 9999).
function prochain_plu(): string {
    $pris = db()->query('SELECT plu FROM produits')->fetchAll(PDO::FETCH_COLUMN);
    $pris = array_flip(array_map('intval', $pris));
    for ($i = 1; $i <= 9999; $i++) {
        if (!isset($pris[$i])) return str_pad((string)$i, 4, '0', STR_PAD_LEFT);
    }
    return '';
}

function produits_actifs(): array {
    return db()->query('SELECT * FROM produits WHERE actif=1 ORDER BY ordre, libelle')->fetchAll();
}

function produit_par_id(int $id): ?array {
    $q = db()->prepare('SELECT * FROM produits WHERE id=?');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

// Le référentiel n'existe pas encore tant que la migration n'est pas passée.
function referentiel_produits_pret(): bool {
    static $pret = null;
    if ($pret === null) {
        try {
            db()->query('SELECT 1 FROM produits LIMIT 1');
            $pret = true;
        } catch (PDOException $e) {
            $pret = false;
        }
    }
    return $pret;
}

// ============================================================
//  Composition et taux bio — règlement (UE) 2018/848
// ============================================================
const SEUIL_BIO = 95.0;
const LIB_NATURE = [
    'agricole' => 'Ingrédient agricole',
    'eau'      => 'Eau (hors calcul)',
    'sel'      => 'Sel (hors calcul)',
    'autre'    => 'Autre non agricole (hors calcul)',
];

function recette_lignes(int $produit_id): array {
    $q = db()->prepare('SELECT * FROM recette_lignes WHERE produit_id=? ORDER BY quantite DESC, ordre, id');
    $q->execute([$produit_id]);
    return $q->fetchAll();
}

function recettes_pretes(): bool {
    static $pret = null;
    if ($pret === null) {
        try { db()->query('SELECT 1 FROM recette_lignes LIMIT 1'); $pret = true; }
        catch (PDOException $e) { $pret = false; }
    }
    return $pret;
}

/**
 * Taux d'ingrédients biologiques d'un produit.
 * Le règlement calcule le ratio sur les seuls ingrédients d'origine agricole :
 * l'eau et le sel sont exclus des deux termes, comme les additifs.
 *
 * @return array{agricole: float, bio: float, taux: ?float, mention: string, lignes: array}
 */
function taux_bio(int $produit_id): array {
    $lignes = recettes_pretes() ? recette_lignes($produit_id) : [];
    $agricole = $bio = 0.0;
    foreach ($lignes as $l) {
        if ($l['nature'] !== 'agricole') continue;
        $agricole += (float)$l['quantite'];
        if ($l['bio']) $bio += (float)$l['quantite'];
    }
    $taux = $agricole > 0 ? round($bio / $agricole * 100, 2) : null;

    if ($taux === null)            $mention = 'inconnu';
    elseif ($taux >= SEUIL_BIO)    $mention = 'bio';
    elseif ($taux > 0)             $mention = 'ingredients_bio';
    else                           $mention = 'non_bio';

    return ['agricole' => $agricole, 'bio' => $bio, 'taux' => $taux,
            'mention' => $mention, 'lignes' => $lignes];
}

// Ce que le produit a le droit d'afficher, en clair.
function libelle_mention_bio(string $mention): string {
    return [
        'bio'             => 'Bio — Eurofeuille autorisée',
        'ingredients_bio' => 'Ingrédients bio signalés dans la liste, sans allégation ni logo',
        'non_bio'         => 'Aucune mention bio',
        'inconnu'         => 'Composition non renseignée',
    ][$mention] ?? '';
}

/**
 * Liste d'ingrédients pour l'étiquette : ordre de poids décroissant (INCO),
 * astérisque sur les ingrédients biologiques.
 */
function liste_ingredients(int $produit_id): string {
    $t = taux_bio($produit_id);
    if (!$t['lignes']) return '';
    $parts = [];
    foreach ($t['lignes'] as $l) {
        $parts[] = $l['libelle'] . ($l['bio'] ? '*' : '');
    }
    $texte = implode(', ', $parts);
    $a_du_bio = $t['bio'] > 0;
    return $texte . ($a_du_bio ? '. * issu de l\'agriculture biologique.' : '.');
}

// ============================================================
//  Mentions d'origine — règlement (CE) 1760/2000
// ============================================================

/**
 * Mentions d'origine d'un lot d'entrée.
 * « Origine : X » n'est permise que si naissance, élevage et abattage ont eu
 * lieu dans le même pays ; sinon les trois lignes doivent figurer séparément.
 *
 * @return array{origine: ?string, lignes: string[], complet: bool}
 */
function origine_lot(array $e): array {
    $n = trim((string)($e['pays_naissance'] ?? ''));
    $el = trim((string)($e['pays_elevage'] ?? ''));
    $a = trim((string)($e['pays_abattage'] ?? ''));
    $ag = trim((string)($e['agrement_abattoir'] ?? ''));

    $complet = $n !== '' && $el !== '' && $a !== '';
    if (!$complet) return ['origine' => null, 'lignes' => [], 'complet' => false];

    $abattu = 'Abattu en : ' . $a . ($ag !== '' ? ' (' . $ag . ')' : '');

    if ($n === $el && $el === $a) {
        return ['origine' => $n, 'lignes' => ['Origine : ' . $n, $abattu], 'complet' => true];
    }
    return ['origine' => null,
            'lignes'  => ['Né en : ' . $n, 'Élevé en : ' . $el, $abattu],
            'complet' => true];
}

// Mention « Découpé en », identique pour tout l'atelier.
function mention_decoupe(): string {
    $pays = reglage('pays_decoupe', 'France');
    $ag   = reglage('agrement_atelier');
    return 'Découpé en : ' . $pays . ($ag !== '' ? ' (' . $ag . ')' : '');
}

/**
 * Origine consolidée d'une fabrication. Si ses lots d'entrée n'ont pas tous
 * la même origine, aucune mention unique n'est possible : il faut le signaler
 * plutôt que de laisser imprimer une origine fausse.
 *
 * @return array{unique: ?string, melange: bool, origines: string[], incomplets: string[]}
 */
function origine_fabrication(int $sortie_id): array {
    $origines = $incomplets = [];
    foreach (entrees_de_sortie($sortie_id) as $e) {
        $o = origine_lot($e);
        if (!$o['complet']) { $incomplets[] = $e['num_lot']; continue; }
        $cle = $o['origine'] ?? implode(' / ', $o['lignes']);
        $origines[$cle] = true;
    }
    $liste = array_keys($origines);
    return [
        'unique'     => count($liste) === 1 && !$incomplets ? $liste[0] : null,
        'melange'    => count($liste) > 1,
        'origines'   => $liste,
        'incomplets' => $incomplets,
    ];
}

// ============================================================
//  Requêtes métier
// ============================================================
function types_actifs(): array {
    return db()->query('SELECT * FROM types_matiere WHERE actif=1 ORDER BY ordre, libelle')->fetchAll();
}

function entree_par_id(int $id): ?array {
    $q = db()->prepare('SELECT e.*, t.code, t.libelle AS type_libelle, t.couleur
                        FROM lots_entree e JOIN types_matiere t ON t.id=e.type_id WHERE e.id=?');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

function sortie_par_id(int $id): ?array {
    $q = db()->prepare('SELECT * FROM lots_sortie WHERE id=?');
    $q->execute([$id]);
    return $q->fetch() ?: null;
}

// Traçabilité ascendante : les entrées utilisées par une fabrication
function entrees_de_sortie(int $sortie_id): array {
    $q = db()->prepare(
        'SELECT e.*, t.code, t.libelle AS type_libelle, t.couleur, se.quantite_kg AS qte_utilisee
         FROM lots_sortie_entrees se
         JOIN lots_entree e   ON e.id = se.entree_id
         JOIN types_matiere t ON t.id = e.type_id
         WHERE se.sortie_id = ? ORDER BY t.ordre, e.num_lot');
    $q->execute([$sortie_id]);
    return $q->fetchAll();
}

// Traçabilité descendante : les fabrications issues d'un lot d'entrée
function sorties_de_entree(int $entree_id): array {
    $q = db()->prepare(
        'SELECT s.*, se.quantite_kg AS qte_utilisee
         FROM lots_sortie_entrees se
         JOIN lots_sortie s ON s.id = se.sortie_id
         WHERE se.entree_id = ? ORDER BY s.date_fabrication DESC, s.num_lot');
    $q->execute([$entree_id]);
    return $q->fetchAll();
}

// Solde d'un lot d'entrée (si le poids d'entrée est renseigné)
function solde_entree(int $entree_id): ?array {
    $pdo = db();
    $q = $pdo->prepare('SELECT poids_kg FROM lots_entree WHERE id=?');
    $q->execute([$entree_id]);
    $poids = $q->fetchColumn();
    if ($poids === null || $poids === false) return null;
    $q2 = $pdo->prepare('SELECT COALESCE(SUM(quantite_kg),0) FROM lots_sortie_entrees WHERE entree_id=?');
    $q2->execute([$entree_id]);
    $utilise = (float)$q2->fetchColumn();
    return ['entree'=>(float)$poids, 'utilise'=>$utilise, 'reste'=>max(0, (float)$poids - $utilise)];
}
