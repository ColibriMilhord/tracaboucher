<?php
// ============================================================
//  Lecture de fichiers CSV produits par d'autres logiciels.
//
//  Portage du moteur éprouvé sur les relevés bancaires d'app.causselot.fr
//  (81 cas de test), généralisé : ici ce ne sont plus des opérations
//  bancaires mais des étiquettes sorties de la balance. Le principe
//  reste le même et c'est lui qui compte — on ne sait jamais à l'avance
//  comment un logiciel tiers met en forme son export, donc on reconnaît
//  d'abord par les en-têtes, puis par le contenu, et on laisse corriger.
// ============================================================

/**
 * Ramène le contenu en UTF-8. Les logiciels de caisse et de balance
 * exportent encore très souvent en Windows-1252 : sans conversion,
 * « CÔTE DE BŒUF » arrive haché et ne s'apparie avec rien.
 */
function csv_en_utf8(string $contenu, string $encodage = 'auto'): string {
    $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu);   // BOM UTF-8
    if ($encodage !== 'auto' && $encodage !== '') {
        $converti = @iconv($encodage, 'UTF-8//TRANSLIT', $contenu);
        return $converti === false ? $contenu : $converti;
    }
    if (function_exists('mb_check_encoding') && mb_check_encoding($contenu, 'UTF-8')) {
        return $contenu;
    }
    $converti = @iconv('Windows-1252', 'UTF-8//TRANSLIT', $contenu);
    return $converti === false ? $contenu : $converti;
}

function csv_sans_accents(string $s): string {
    $converti = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    return $converti === false ? $s : $converti;
}

/** Le séparateur le plus probable, déduit de la première ligne utile. */
function csv_devine_separateur(string $ligne): string {
    $candidats = [';' => substr_count($ligne, ';'),
                  ',' => substr_count($ligne, ','),
                  "\t" => substr_count($ligne, "\t"),
                  '|' => substr_count($ligne, '|')];
    arsort($candidats);
    $premier = array_key_first($candidats);
    return $candidats[$premier] > 0 ? $premier : ';';
}

/**
 * Un nombre tel que l'écrivent les logiciels : « 1 234,56 », « 1.234,56 »,
 * « -12.5 », parfois suivi d'une unité. Renvoie null si ce n'en est pas un.
 */
function csv_nombre(string $brut): ?float {
    $s = trim($brut);
    if ($s === '') return null;
    $s = str_replace(["\xC2\xA0", ' ', "\t", '€', 'EUR', 'kg', 'KG', 'Kg', 'g'], '', $s);
    $negatif = str_starts_with($s, '-') || (str_starts_with($s, '(') && str_ends_with($s, ')'));
    $s = trim($s, "()+-");
    if ($s === '') return null;

    // Le dernier séparateur rencontré est le séparateur décimal ; les
    // autres ne sont que des séparateurs de milliers.
    $pos_virgule = strrpos($s, ',');
    $pos_point   = strrpos($s, '.');
    if ($pos_virgule !== false && ($pos_point === false || $pos_virgule > $pos_point)) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    } else {
        $s = str_replace(',', '', $s);
    }
    if (!preg_match('/^\d+(\.\d+)?$/', $s)) return null;
    return ($negatif ? -1 : 1) * (float)$s;
}

/** Une date, dans les formats que les logiciels emploient. */
function csv_date(string $brut, string $format_attendu = 'd/m/Y'): ?string {
    $s = trim($brut);
    if ($s === '') return null;
    // Un horodatage « 03/10/2026 14:22 » : la partie heure ne nous sert pas.
    $s = preg_replace('/[ T]\d{1,2}[:h]\d{2}(:\d{2})?$/', '', $s);
    foreach (array_unique([$format_attendu, 'd/m/Y', 'd/m/y', 'Y-m-d', 'd-m-Y', 'd.m.Y', 'Ymd']) as $f) {
        $d = DateTime::createFromFormat('!' . $f, $s);
        if ($d instanceof DateTime) {
            $annee = (int)$d->format('Y');
            if ($annee >= 1990 && $annee <= 2100) return $d->format('Y-m-d');
        }
    }
    return null;
}

/**
 * Découpe le fichier sans rien interpréter : en-tête et cellules brutes.
 * Séparé de l'interprétation pour que l'écran d'import puisse proposer
 * une autre mise en correspondance sans redemander le fichier.
 *
 * @param array $opts separateur, encodage, lignes_entete
 * @return array{entete: string[], cellules: array[], separateur: string, erreurs: string[]}
 */
function csv_decouper(string $contenu, array $opts = []): array {
    $contenu = csv_en_utf8($contenu, (string)($opts['encodage'] ?? 'auto'));
    $contenu = str_replace(["\r\n", "\r"], "\n", $contenu);
    $brutes  = array_values(array_filter(explode("\n", $contenu), fn($l) => trim($l) !== ''));
    if (!$brutes) {
        return ['entete' => [], 'cellules' => [], 'separateur' => ';', 'erreurs' => ['Le fichier est vide.']];
    }

    $sep = (string)($opts['separateur'] ?? 'auto');
    if ($sep === '' || $sep === 'auto') { $sep = csv_devine_separateur($brutes[0]); }

    // Certains logiciels préfixent l'export de lignes de garde (titre,
    // période, totaux) : la vraie ligne d'en-tête est la première qui
    // porte plusieurs colonnes.
    $avec_entete = (int)($opts['lignes_entete'] ?? 1) > 0;
    while ($avec_entete && count(str_getcsv($brutes[0], $sep, '"', '\\')) < 2 && count($brutes) > 1) {
        array_shift($brutes);
    }

    $entete = [];
    if ($avec_entete) {
        $entete = array_map(fn($c) => trim((string)$c), str_getcsv(array_shift($brutes), $sep, '"', '\\'));
    }
    $cellules = array_map(fn($l) => str_getcsv($l, $sep, '"', '\\'), $brutes);

    return ['entete' => $entete, 'cellules' => $cellules, 'separateur' => $sep, 'erreurs' => []];
}

/**
 * Indice d'une colonne : par son intitulé exact, ou par son numéro quand
 * le fichier n'a pas d'en-tête exploitable.
 */
function csv_indice(array $entete, ?string $nom): ?int {
    $nom = trim((string)$nom);
    if ($nom === '') return null;
    $i = array_search($nom, $entete, true);
    if ($i !== false) return (int)$i;
    return ctype_digit($nom) ? (int)$nom : null;
}

/**
 * Devine les colonnes d'après les intitulés.
 *
 * @param array $roles code du rôle => mots-clés à chercher, sans accents
 * @return array code du rôle => intitulé trouvé, ou null
 */
function csv_devine_par_entete(array $entete, array $roles): array {
    $trouve = function (array $mots) use ($entete): ?string {
        foreach ($entete as $col) {
            $n = mb_strtolower(csv_sans_accents((string)$col));
            foreach ($mots as $m) {
                if ($n !== '' && str_contains($n, $m)) return (string)$col;
            }
        }
        return null;
    };
    $resultat = [];
    foreach ($roles as $code => $mots) { $resultat[$code] = $trouve($mots); }
    return $resultat;
}

/**
 * Profil de chaque colonne, déduit de son CONTENU : combien de cellules
 * se lisent comme une date, comme un nombre, et quelle est l'abondance
 * de texte. C'est ce qui permet d'absorber un export dont les intitulés
 * ne disent rien — ou qui n'en a pas du tout.
 *
 * @return array indice de colonne => ['dates','nombres','texte','remplies','min','max','entiers']
 */
function csv_profiler(array $cellules, int $echantillon = 20): array {
    $lignes = array_slice($cellules, 0, $echantillon);
    if (!$lignes) return [];

    $profil = [];
    $nb = max(array_map('count', $lignes));
    for ($i = 0; $i < $nb; $i++) {
        $p = ['dates' => 0, 'nombres' => 0, 'texte' => 0, 'remplies' => 0,
              'min' => null, 'max' => null, 'entiers' => 0, 'exemple' => ''];
        foreach ($lignes as $l) {
            $v = trim((string)($l[$i] ?? ''));
            if ($v === '') continue;
            $p['remplies']++;
            if ($p['exemple'] === '') { $p['exemple'] = $v; }
            if (csv_date($v) !== null) { $p['dates']++; continue; }
            $n = csv_nombre($v);
            if ($n !== null) {
                $p['nombres']++;
                if ($p['min'] === null || $n < $p['min']) { $p['min'] = $n; }
                if ($p['max'] === null || $n > $p['max']) { $p['max'] = $n; }
                if (floor($n) == $n) { $p['entiers']++; }
                continue;
            }
            $p['texte'] += mb_strlen($v);
        }
        $profil[$i] = $p;
    }
    return $profil;
}

/** Une colonne est-elle majoritairement de ce type ? */
function csv_colonne_est(array $p, string $type, float $seuil = 0.8): bool {
    if (($p['remplies'] ?? 0) === 0) return false;
    return ($p[$type] ?? 0) / $p['remplies'] >= $seuil;
}
