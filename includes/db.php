<?php
require_once __DIR__ . '/../config.php';

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
            die('<div style="font-family:sans-serif;padding:20px;background:#fee;border:2px solid #7A1C1C;border-radius:12px;margin:20px"><strong>⚠️ Connexion BD impossible</strong><br>'.htmlspecialchars($e->getMessage()).'<br><br>Vérifiez <code>config.php</code></div>');
        }
    }
    return $pdo;
}

// ── Génération numéro lot carcasse : AGN-202507-001
function generer_num_lot(int $espece_id, string $date_entree): string {
    $pdo = db();
    $q = $pdo->prepare('SELECT code FROM especes WHERE id=?');
    $q->execute([$espece_id]);
    $row = $q->fetch();
    if (!$row) return '';
    $mois = date('Ym', strtotime($date_entree));
    $count = $pdo->prepare('SELECT COUNT(*) FROM lots_carcasses WHERE espece_id=? AND num_lot LIKE ?');
    $count->execute([$espece_id, $row['code'].'-'.$mois.'-%']);
    $seq = (int)$count->fetchColumn() + 1;
    return sprintf('%s-%s-%03d', $row['code'], $mois, $seq);
}

// ── Génération référence lot fini traiteur : TR-20231027-001
function generer_ref_lot_fini(string $date_prep): string {
    $pdo = db();
    $d = date('Ymd', strtotime($date_prep));
    $count = $pdo->prepare('SELECT COUNT(*) FROM plats_cuisines WHERE ref_lot_fini LIKE ?');
    $count->execute(['TR-'.$d.'-%']);
    $seq = (int)$count->fetchColumn() + 1;
    return sprintf('TR-%s-%03d', $d, $seq);
}

// ── Upload fichier
function upload_fichier(array $file, string $sous_dossier='abattoir'): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > MAX_UPLOAD_SIZE) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT)) return null;
    $dir = UPLOAD_DIR . $sous_dossier . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $nom = uniqid($sous_dossier.'_', true) . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $dir.$nom)) return $sous_dossier.'/'.$nom;
    return null;
}

// ── Solde disponible d'un lot
function solde_lot(int $lot_id): float {
    $pdo = db();
    $q = $pdo->prepare('SELECT poids_carcasse_kg FROM lots_carcasses WHERE id=?');
    $q->execute([$lot_id]);
    $entree = (float)($q->fetchColumn() ?: 0);
    $q2 = $pdo->prepare('SELECT COALESCE(SUM(poids_kg),0) FROM sorties_viande WHERE lot_id=?');
    $q2->execute([$lot_id]);
    $sorties = (float)$q2->fetchColumn();
    $q3 = $pdo->prepare('SELECT COALESCE(SUM(poids_kg),0) FROM plats_viande WHERE lot_id=?');
    $q3->execute([$lot_id]);
    $plats = (float)$q3->fetchColumn();
    return max(0, $entree - $sorties - $plats);
}

// ── Stock par espèce
function stock_par_espece(): array {
    $pdo = db();
    $especes = $pdo->query('SELECT * FROM especes ORDER BY libelle')->fetchAll();
    $result = [];
    foreach ($especes as $e) {
        $q = $pdo->prepare(
            'SELECT COALESCE(SUM(l.poids_carcasse_kg),0) as entree FROM lots_carcasses l WHERE l.espece_id=?'
        );
        $q->execute([$e['id']]);
        $entree = (float)$q->fetchColumn();

        $q2 = $pdo->prepare(
            'SELECT COALESCE(SUM(s.poids_kg),0) FROM sorties_viande s
             JOIN lots_carcasses l ON l.id=s.lot_id WHERE l.espece_id=?'
        );
        $q2->execute([$e['id']]);
        $sorties = (float)$q2->fetchColumn();

        $q3 = $pdo->prepare(
            'SELECT COALESCE(SUM(pv.poids_kg),0) FROM plats_viande pv
             JOIN lots_carcasses l ON l.id=pv.lot_id WHERE l.espece_id=?'
        );
        $q3->execute([$e['id']]);
        $plats = (float)$q3->fetchColumn();

        $dispo = max(0, $entree - $sorties - $plats);
        $result[] = array_merge($e, [
            'entree'=>$entree, 'sorties'=>$sorties, 'plats'=>$plats, 'dispo'=>$dispo,
            'pct_utilise'=>$entree>0 ? min(100,round(($sorties+$plats)/$entree*100)) : 0
        ]);
    }
    return $result;
}

// ── Helpers
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
function fmt_date(?string $d): string { return $d ? date('d/m/Y', strtotime($d)) : '—'; }
function fmt_poids(?float $p): string { return $p===null ? '—' : number_format($p,2,',',' ').' kg'; }
function badge_esp(array $lot): string {
    return '<span class="badge-esp" style="background:'.h($lot['couleur']).'">'.h($lot['code']).'</span>';
}
