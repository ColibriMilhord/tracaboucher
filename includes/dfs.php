<?php
// ============================================================
//  ARTICLES.TXT : le fichier que DFS (Dibal) importe, via DGI/RGI.
//
//  Le format suit le manuel Dibal « DGI / RGI » (49-MDRGI000EN10) :
//   - séparateur d'un seul caractère (« ; ») et AUCUN délimiteur de
//     texte : DGI n'en connaît pas, un libellé mis entre guillemets par
//     fputcsv() serait imprimé avec ses guillemets. Un « ; » dans un
//     texte devient donc « , ».
//   - longueurs de la balance : Name et Name 2 = 20 caractères,
//     Text 01 à Text 20 = 24, G Text = 2048. RGI écarte toute ligne
//     dont une donnée est invalide (§ 5.2.6) : on coupe proprement
//     ici, et on le signale, plutôt que de voir un produit disparaître
//     de la balance sans explication.
//   - Code sur 6 chiffres au plus et Type obligatoires (1 = au poids).
//   - une première ligne de titres (les noms des champs DGI), sautée
//     à l'import par « Initial line = 1 ». Elle porte aussi le BOM
//     UTF-8 : un lecteur qui ne le comprendrait pas ne perd qu'elle.
//  Fins de ligne Windows (CRLF) : le fichier est lu sur un PC.
// ============================================================

// [titre de la colonne = champ DGI à choisir, type DGI à vérifier, contenu]
const DFS_COLONNES = [
    ['Code',            'Numeric',                          'PLU'],
    ['Type',            'Numeric',                          '1 = vendu au poids'],
    ['Name',            'Text',                             'Libellé, 1re ligne'],
    ['Name 2',          'Text',                             'Libellé, 2e ligne'],
    ['Price',           'Numeric with dot as decimal mark', 'Prix TTC au kg'],
    ['Expiration days', 'Numeric',                          'Durée de vie (la balance calcule la DLC)'],
    ['G Text',          'Text',                             'Ingrédients'],
    ['Text 01',         'Text',                             'Origine, ou Né en'],
    ['Text 02',         'Text',                             'Élevé en'],
    ['Text 03',         'Text',                             'Abattu en'],
    ['Text 04',         'Text',                             'Agrément de l\'abattoir'],
    ['Text 05',         'Text',                             'Découpé en'],
    ['Text 06',         'Text',                             'Agrément de l\'atelier'],
    ['Text 07',         'Text',                             'Code de l\'organisme bio'],
    ['Text 08',         'Text',                             'Origine agricole (bio)'],
];
// Ajoutée en dernière colonne quand un n° de format est réglé dans Paramètres.
const DFS_COLONNE_FORMAT = ['Label format', 'Numeric', 'N° du format d\'étiquette'];

const DFS_NOM_MAX   = 20;
const DFS_TEXTE_MAX = 24;
const DFS_GTEXT_MAX = 2048;

// Dossier que RGI surveille, proposé par défaut partout (agent, aide).
const DFS_DOSSIER = 'C:\\DibalImport';

// Une valeur telle que DGI la lira : ni « ; », ni retour à la ligne.
function dfs_propre(?string $s): string {
    $s = str_replace(';', ',', (string)$s);
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s);
    return trim(preg_replace('/ {2,}/', ' ', $s));
}

/**
 * Libellé réparti sur Name et Name 2, coupé entre deux mots si possible.
 * @return array{0: string, 1: string, 2: bool}  [ligne 1, ligne 2, abîmé ?]
 * « abîmé » : une fin perdue, ou un mot partagé entre les deux lignes.
 */
function dfs_nom_en_deux(string $libelle): array {
    $t = dfs_propre($libelle);
    if (mb_strlen($t) <= DFS_NOM_MAX) return [$t, '', false];
    $pos = mb_strrpos(mb_substr($t, 0, DFS_NOM_MAX + 1), ' ');
    $mot_coupe = !$pos;
    if ($mot_coupe) $pos = DFS_NOM_MAX;
    $l1 = rtrim(mb_substr($t, 0, $pos));
    $l2 = ltrim(mb_substr($t, $pos));
    return [$l1, mb_substr($l2, 0, DFS_NOM_MAX), $mot_coupe || mb_strlen($l2) > DFS_NOM_MAX];
}

/**
 * Text 01 à Text 06 : mentions d'origine viande bovine (règlement 1760/2000),
 * une par champ, l'agrément sur sa propre ligne pour tenir en 24 caractères.
 * La règle « Origine : X » quand les trois pays coïncident reste celle
 * d'origine_lot() ; on ne la réécrit pas ici.
 */
function dfs_mentions_origine(): array {
    $o = origine_lot([
        'pays_naissance' => reglage('origine_naissance', 'France'),
        'pays_elevage'   => reglage('origine_elevage', 'France'),
        'pays_abattage'  => reglage('origine_abattage', 'France'),
    ]);
    if (!$o['complet']) return array_fill(0, 6, '');
    $l = $o['lignes'];                       // [Origine, Abattu] ou [Né, Élevé, Abattu]
    [$ne, $eleve, $abattu] = count($l) === 2 ? [$l[0], '', $l[1]] : $l;
    return [$ne, $eleve, $abattu, reglage('agrement_abattoir'),
            'Découpé en : ' . reglage('pays_decoupe', 'France'), reglage('agrement_atelier')];
}

/**
 * Tout le contenu du fichier, et ce qu'il a fallu couper ou qui manque.
 * @return array{titres: string[], lignes: array<int, string[]>, alertes: string[]}
 */
function dfs_articles(): array {
    $titres = array_column(DFS_COLONNES, 0);
    $format = reglage('dfs_format_etiquette');
    if ($format !== '') $titres[] = DFS_COLONNE_FORMAT[0];

    // Mentions communes à tous les produits concernés : calculées une fois,
    // signalées seulement si un produit les porte vraiment.
    $trop_longues = function (array $textes): array {
        $coupes = [];
        foreach ($textes as $t) {
            if (mb_strlen($t) > DFS_TEXTE_MAX) $coupes[] = "Mention « $t » : plus de " . DFS_TEXTE_MAX
                . ' caractères, coupée sur l\'étiquette (Paramètres).';
        }
        return $coupes;
    };
    $origine = array_map('dfs_propre', dfs_mentions_origine());
    $bio     = array_map('dfs_propre', [reglage('code_certificateur'), reglage('origine_agricole', 'Agriculture France')]);
    $alertes_origine = $trop_longues($origine);
    if (implode('', $origine) === '') {
        $alertes_origine[] = 'Mentions d\'origine incomplètes : pays de naissance, d\'élevage ou d\'abattage vide (Paramètres).';
    }
    $alertes_bio = $trop_longues($bio);
    if ($bio[0] === '') $alertes_bio[] = 'Produits bio sans code d\'organisme certificateur (Paramètres).';
    $coupe24 = fn(array $t) => array_map(fn($s) => mb_substr($s, 0, DFS_TEXTE_MAX), $t);
    $origine = $coupe24($origine);
    $bio     = $coupe24($bio);

    $lignes = [];
    $sans_prix = $sans_dlc = $noms_coupes = [];
    $a_du_bovin = $a_du_bio = false;
    foreach (db()->query('SELECT * FROM produits WHERE actif=1 ORDER BY plu') as $p) {
        $plu = (int)$p['plu'];
        [$nom, $nom2, $coupe] = dfs_nom_en_deux($p['libelle_court'] ?: $p['libelle']);
        if ($coupe) $noms_coupes[] = $p['plu'];
        if ($p['prix_kg'] === null) $sans_prix[] = $p['plu'];
        if ($p['dlc_jours'] === null) $sans_dlc[] = $p['plu'];

        $est_bovin = ($p['classe_traca'] ?? '') === 'viande_bovine';
        $est_bio   = taux_bio((int)$p['id'])['mention'] === 'bio';
        $a_du_bovin = $a_du_bovin || $est_bovin;
        $a_du_bio   = $a_du_bio || $est_bio;

        $ligne = [
            (string)$plu,
            '1',
            $nom, $nom2,
            $p['prix_kg'] === null ? '' : number_format((float)$p['prix_kg'], 2, '.', ''),
            $p['dlc_jours'] === null ? '' : (string)min(999, max(0, (int)$p['dlc_jours'])),
            mb_substr(dfs_propre(liste_ingredients((int)$p['id'])), 0, DFS_GTEXT_MAX),
        ];
        $ligne = array_merge($ligne,
            $est_bovin ? $origine : array_fill(0, 6, ''),
            $est_bio ? $bio : ['', '']);
        if ($format !== '') $ligne[] = $format;
        $lignes[] = $ligne;
    }

    $alertes = array_merge($a_du_bovin ? $alertes_origine : [], $a_du_bio ? $alertes_bio : []);
    if ($noms_coupes) $alertes[] = 'Libellé qui ne tient pas sur 2 lignes de ' . DFS_NOM_MAX . ' caractères : PLU '
                                 . implode(', ', $noms_coupes) . '. Raccourcissez leur libellé court.';
    if ($sans_prix)   $alertes[] = 'Sans prix au kg : PLU ' . implode(', ', $sans_prix) . '.';
    if ($sans_dlc)    $alertes[] = 'Sans durée de vie, donc sans DLC imprimée : PLU ' . implode(', ', $sans_dlc) . '.';

    return ['titres' => $titres, 'lignes' => $lignes, 'alertes' => $alertes];
}

// Le fichier tel qu'il part vers le PC de la balance.
function dfs_fichier_articles(): string {
    $a = dfs_articles();
    $txt = implode(';', $a['titres']) . "\r\n";
    foreach ($a['lignes'] as $l) $txt .= implode(';', $l) . "\r\n";
    // Repli si DGI lit le fichier en ANSI (accents imprimés « Ã© ») :
    // réglable dans Paramètres, sans BOM puisque ce n'est plus de l'UTF-8.
    if (reglage('dfs_encodage') === 'ansi') {
        return mb_convert_encoding($txt, 'Windows-1252', 'UTF-8');
    }
    return "\xEF\xBB\xBF" . $txt;
}

// URL que l'agent du PC de la balance appelle pour récupérer ARTICLES.TXT.
function url_recuperation(string $jeton): string {
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
          . '://' . ($_SERVER['HTTP_HOST'] ?? 'causselot.fr')
          . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/tracabilite/'), '/\\');
    return $base . '/export.php?type=dfs_articulo&token=' . $jeton;
}
