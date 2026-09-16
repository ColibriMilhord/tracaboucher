<?php
// ============================================================
//  Export CSV (séparateur ";", UTF-8 BOM → ouverture directe dans Excel FR)
//  export.php?type=entree|sortie&id=..   → fiche de traçabilité d'un lot
//  export.php?type=registre_entrees      → registre complet des entrées
//  export.php?type=registre_fabrications → registre complet des fabrications
// ============================================================
require_once __DIR__ . '/includes/auth.php';

$type = $_GET['type'] ?? '';
$id   = (int)($_GET['id'] ?? 0);

// L'agent local (PC de la balance) télécharge les exports « balance » sans
// session navigateur, via un jeton secret partagé (réglage token_export).
// Réservé aux exports destinés à DFS, jamais aux registres nominatifs.
$exports_balance = ['dfs_articulo', 'dfs_articles', 'dfs_lots'];
$jeton_attendu   = reglage('token_export');
$jeton_fourni    = (string)($_GET['token'] ?? '');
$acces_jeton = in_array($type, $exports_balance, true)
            && $jeton_attendu !== ''
            && $jeton_fourni !== ''
            && hash_equals($jeton_attendu, $jeton_fourni);

if (!$acces_jeton) {
    $moi = exiger_connexion();
}

$pdo = db();

$nom = 'export';
$lignes = [];

if ($type === 'sortie' && $id) {
    $s = sortie_par_id($id);
    if (!$s) { http_response_code(404); exit('Introuvable'); }
    $nom = 'traca_' . $s['num_lot'];
    $lignes[] = ['FICHE DE TRACABILITE - FABRICATION'];
    $lignes[] = [];
    $lignes[] = ['N° de lot', $s['num_lot']];
    $lignes[] = ['Date de fabrication', fmt_date($s['date_fabrication'])];
    $lignes[] = ['Produit', $s['produit']];
    $lignes[] = ['Quantité fabriquée', $s['quantite'] . ' ' . $s['unite']];
    $lignes[] = ['Conditionnement', LIB_CONDITIONNEMENT[$s['conditionnement']] ?? ''];
    $lignes[] = ['Conservation', LIB_CONSERVATION[$s['conservation']] ?? ''];
    $lignes[] = ['DLC / DDM', fmt_date($s['dlc'])];
    $lignes[] = ['Cuisson', trim(($s['cuisson_temp'] ?? '') . ' °C ' . ($s['cuisson_duree'] ?? ''))];
    $lignes[] = ['Refroidissement', $s['refroidissement'] ?? ''];
    $lignes[] = ['Notes', $s['notes'] ?? ''];
    $lignes[] = ['Saisi par', $s['cree_par'] ?? ''];
    $lignes[] = ['Saisi le', date('d/m/Y H:i', strtotime($s['date_creation']))];
    $lignes[] = [];
    $lignes[] = ['MATIERES PREMIERES UTILISEES'];
    $lignes[] = ['N° lot entrée', 'Type', 'Date réception', 'Fournisseur', 'Lot fournisseur', 'Température (°C)', 'Qté utilisée (kg)'];
    foreach (entrees_de_sortie($id) as $e) {
        $lignes[] = [$e['num_lot'], $e['type_libelle'], fmt_date($e['date_entree']), $e['fournisseur'],
                     $e['num_lot_fournisseur'] ?? '', $e['temperature'], $e['qte_utilisee'] ?? ''];
    }

} elseif ($type === 'entree' && $id) {
    $e = entree_par_id($id);
    if (!$e) { http_response_code(404); exit('Introuvable'); }
    $nom = 'traca_' . $e['num_lot'];
    $lignes[] = ['FICHE DE TRACABILITE - ENTREE MATIERE PREMIERE'];
    $lignes[] = [];
    $lignes[] = ['N° de lot', $e['num_lot']];
    $lignes[] = ['Type', $e['type_libelle']];
    $lignes[] = ['Date de réception', fmt_date($e['date_entree'])];
    $lignes[] = ['Fournisseur', $e['fournisseur']];
    $lignes[] = ['Lot fournisseur', $e['num_lot_fournisseur'] ?? ''];
    $lignes[] = ['Température (°C)', $e['temperature']];
    $lignes[] = ['Poids (kg)', $e['poids_kg'] ?? ''];
    $lignes[] = ['Forme', LIB_FORME[$e['forme']] ?? ''];
    $lignes[] = ['État', LIB_ETAT[$e['etat']] ?? ''];
    $lignes[] = ['Notes', $e['notes'] ?? ''];
    $lignes[] = ['Saisi par', $e['cree_par'] ?? ''];
    $lignes[] = ['Saisi le', date('d/m/Y H:i', strtotime($e['date_creation']))];
    $lignes[] = [];
    $lignes[] = ['FABRICATIONS ISSUES DE CE LOT'];
    $lignes[] = ['N° lot fabrication', 'Date', 'Produit', 'Quantité', 'Conditionnement', 'Conservation', 'Qté utilisée (kg)'];
    foreach (sorties_de_entree($id) as $s) {
        $lignes[] = [$s['num_lot'], fmt_date($s['date_fabrication']), $s['produit'],
                     $s['quantite'] . ' ' . $s['unite'], LIB_CONDITIONNEMENT[$s['conditionnement']] ?? '',
                     LIB_CONSERVATION[$s['conservation']] ?? '', $s['qte_utilisee'] ?? ''];
    }

} elseif ($type === 'registre_entrees') {
    $nom = 'registre_entrees_' . date('Ymd');
    $lignes[] = ['N° de lot', 'Date', 'Type', 'Fournisseur', 'Lot fournisseur', 'Poids (kg)',
                 'Température (°C)', 'Forme', 'État', 'Notes', 'Saisi par'];
    $rows = $pdo->query('SELECT e.*, t.libelle AS type_libelle FROM lots_entree e
                         JOIN types_matiere t ON t.id=e.type_id ORDER BY e.date_entree, e.id');
    foreach ($rows as $e) {
        $lignes[] = [$e['num_lot'], fmt_date($e['date_entree']), $e['type_libelle'], $e['fournisseur'],
                     $e['num_lot_fournisseur'] ?? '', $e['poids_kg'] ?? '', $e['temperature'],
                     LIB_FORME[$e['forme']] ?? '', LIB_ETAT[$e['etat']] ?? '', $e['notes'] ?? '', $e['cree_par'] ?? ''];
    }

} elseif ($type === 'registre_fabrications') {
    $nom = 'registre_fabrications_' . date('Ymd');
    $lignes[] = ['N° de lot', 'Date', 'Produit', 'Quantité', 'Unité', 'Lots d\'entrée utilisés',
                 'Conditionnement', 'Conservation', 'DLC', 'Cuisson', 'Refroidissement', 'Notes', 'Saisi par'];
    $rows = $pdo->query('SELECT * FROM lots_sortie ORDER BY date_fabrication, id');
    foreach ($rows as $s) {
        $srcs = array_map(fn($e) => $e['num_lot'], entrees_de_sortie((int)$s['id']));
        $lignes[] = [$s['num_lot'], fmt_date($s['date_fabrication']), $s['produit'], $s['quantite'], $s['unite'],
                     implode(' + ', $srcs), LIB_CONDITIONNEMENT[$s['conditionnement']] ?? '',
                     LIB_CONSERVATION[$s['conservation']] ?? '', fmt_date($s['dlc']),
                     trim(($s['cuisson_temp'] ?? '') . ' ' . ($s['cuisson_duree'] ?? '')),
                     $s['refroidissement'] ?? '', $s['notes'] ?? '', $s['cree_par'] ?? ''];
    }

// ── Fichiers destinés à l'étiqueteuse Dibal (à mapper une fois dans DGI)
} elseif ($type === 'dfs_articles') {
    $nom = 'dibal_articles_' . date('Ymd');
    $lignes[] = ['PLU', 'EAN13', 'LIBELLE', 'LIBELLE_COURT', 'FAMILLE',
                 'PRIX_KG', 'DLC_JOURS', 'CLASSE_TRACA',
                 'MENTION_BIO', 'TAUX_BIO', 'CODE_CERTIFICATEUR', 'ORIGINE_AGRICOLE', 'INGREDIENTS'];
    foreach ($pdo->query('SELECT * FROM produits WHERE actif=1 ORDER BY plu') as $p) {
        $tb = taux_bio((int)$p['id']);
        $lignes[] = [$p['plu'], $p['ean13'], $p['libelle'],
                     $p['libelle_court'] ?: mb_substr($p['libelle'], 0, 40),
                     $p['famille'] ?? '', $p['prix_kg'] ?? '', $p['dlc_jours'] ?? '',
                     $p['classe_traca'] ?? '',
                     $tb['mention'], $tb['taux'] === null ? '' : number_format($tb['taux'], 2, '.', ''),
                     $tb['mention'] === 'bio' ? reglage('code_certificateur') : '',
                     $tb['mention'] === 'bio' ? reglage('origine_agricole', 'Agriculture France') : '',
                     liste_ingredients((int)$p['id'])];
    }

// ── Export « natif DFS » : les en-têtes sont les vrais noms de champs de
//    dat_articulo (schéma sys_datos_dfs). Le mapping DGI devient 1:1.
//    CLASE_NOMBRE reste en clair : les IdClase DFS n'existent pas encore, DGI
//    fera la correspondance vers la classe de traçabilité créée dans DFS.
} elseif ($type === 'dfs_articulo') {
    $nom = 'dfs_dat_articulo_' . date('Ymd');
    $lignes[] = ['IdArticulo', 'PLUNumber', 'Descripcion', 'Descripcion1', 'IdTipo',
                 'PrecioConIVA', 'PrecioEstandar', 'DiasCaducidad', 'EANScanner',
                 'Texto1', 'Texto2', 'Texto3', 'CLASE_NOMBRE', 'FAMILIA'];
    foreach ($pdo->query('SELECT * FROM produits WHERE actif=1 ORDER BY plu') as $p) {
        $id   = (int)ltrim($p['plu'], '0') ?: (int)$p['plu'];
        $prix = $p['prix_kg'] !== null ? number_format((float)$p['prix_kg'], 2, '.', '') : '';
        $tb   = taux_bio((int)$p['id']);
        $ingr = liste_ingredients((int)$p['id']);
        // Texto2 : mention d'origine pour la viande bovine (règlement 1760/2000).
        $origine = '';
        if (($p['classe_traca'] ?? '') === 'viande_bovine') {
            $ap = origine_lot([
                'pays_naissance'    => reglage('origine_naissance', 'France'),
                'pays_elevage'      => reglage('origine_elevage', 'France'),
                'pays_abattage'     => reglage('origine_abattage', 'France'),
                'agrement_abattoir' => reglage('agrement_abattoir'),
            ]);
            $origine = trim(implode(' ', $ap['lignes']) . ' ' . mention_decoupe());
        }
        // Texto3 : mention bio, seulement si le seuil est atteint.
        $bio = $tb['mention'] === 'bio'
             ? 'Agriculture biologique - ' . reglage('code_certificateur') . ' - ' . reglage('origine_agricole', 'Agriculture France')
             : '';
        $lignes[] = [
            $id, $id,
            mb_substr($p['libelle'], 0, 100),
            mb_substr($p['libelle_court'] ?: $p['libelle'], 0, 100),
            1,                                   // 1 = article au poids
            $prix, $prix,
            $p['dlc_jours'] ?? '',
            $p['ean13'] ?? '',
            mb_substr($ingr, 0, 250),
            mb_substr($origine, 0, 250),
            mb_substr($bio, 0, 250),
            $p['classe_traca'] ?? '',
            $p['famille'] ?? '',
        ];
    }

} elseif ($type === 'dfs_lots') {
    // Par défaut les fabrications du jour ; sinon la période demandée.
    $depuis = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['depuis'] ?? '') ? $_GET['depuis'] : date('Y-m-d');
    $jusqu  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['jusqu'] ?? '')  ? $_GET['jusqu']  : $depuis;
    $nom = 'dibal_lots_' . str_replace('-', '', $depuis);

    $lignes[] = ['NUM_LOT', 'DATE_FAB', 'PLU', 'EAN13', 'PRODUIT', 'QUANTITE', 'UNITE',
                 'DLC', 'CONDITIONNEMENT', 'CONSERVATION', 'LOTS_ENTREE', 'FOURNISSEURS', 'LOT_FOURNISSEUR', 'AGREMENT_ABATTOIR',
                 'ORIGINE', 'MENTION_ORIGINE', 'DECOUPE_EN', 'NUM_ANIMAUX'];

    $q = $pdo->prepare(
        'SELECT s.*, p.plu, p.ean13 FROM lots_sortie s
         LEFT JOIN produits p ON p.id = s.produit_id
         WHERE s.date_fabrication BETWEEN ? AND ?
         ORDER BY s.date_fabrication, s.id');
    $q->execute([$depuis, $jusqu]);

    foreach ($q as $s) {
        $sources  = entrees_de_sortie((int)$s['id']);
        $org      = origine_fabrication((int)$s['id']);
        $mentions = $sources ? origine_lot($sources[0])['lignes'] : [];
        $lignes[] = [
            $s['num_lot'],
            date('d/m/Y', strtotime($s['date_fabrication'])),
            $s['plu'] ?? '', $s['ean13'] ?? '', $s['produit'],
            $s['quantite'], $s['unite'], $s['dlc'] ? date('d/m/Y', strtotime($s['dlc'])) : '',
            LIB_CONDITIONNEMENT[$s['conditionnement']] ?? '',
            LIB_CONSERVATION[$s['conservation']] ?? '',
            implode(' + ', array_column($sources, 'num_lot')),
            implode(' + ', array_unique(array_column($sources, 'fournisseur'))),
            implode(' + ', array_filter(array_unique(array_column($sources, 'num_lot_fournisseur')))),
            implode(' + ', array_filter(array_unique(array_column($sources, 'agrement_abattoir')))),
            $org['unique'] ?? '',
            $org['melange'] ? 'ORIGINES MULTIPLES' : implode(' | ', $mentions),
            mention_decoupe(),
            implode(' + ', array_filter(array_unique(array_column($sources, 'num_animal')))),
        ];
    }

} else {
    http_response_code(400);
    exit('Type d\'export inconnu.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $nom) . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");                 // BOM UTF-8
foreach ($lignes as $l) fputcsv($out, $l, ';');
fclose($out);
