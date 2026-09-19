<?php
// ============================================================
//  Authentification par comptes nominatifs — session partagée
//  (SSO) avec app.causselot.fr : la table `utilisateurs` et la
//  table `sessions` vivent dans la base causselot (DB_NAME_CAUSSELOT),
//  pas dans celle de TraçaBoucher. Se connecter une fois sur
//  app.causselot.fr suffit donc pour être déjà connecté ici.
//
//  Tant que cette base n'est pas accessible, l'application continue
//  de tourner sur ses comptes locaux : voir table_comptes() plus bas.
// ============================================================
require_once __DIR__ . '/db.php';

final class SessionBD implements SessionHandlerInterface {
    public function __construct(private PDO $pdo, private string $table) {}

    public function open($chemin, $nom): bool { return true; }
    public function close(): bool { return true; }

    public function read($id): string|false {
        $q = $this->pdo->prepare("SELECT data FROM {$this->table} WHERE id = ?");
        $q->execute([$id]);
        $d = $q->fetchColumn();
        return $d === false ? '' : $d;
    }

    public function write($id, $data): bool {
        $uid = $_SESSION['uid'] ?? null;
        $this->pdo->prepare(
            "REPLACE INTO {$this->table} (id, data, user_id, ip, last_activity) VALUES (?,?,?,?,?)"
        )->execute([$id, $data, $uid, $_SERVER['REMOTE_ADDR'] ?? null, time()]);
        return true;
    }

    public function destroy($id): bool {
        $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = ?")->execute([$id]);
        return true;
    }

    public function gc($duree_max): int|false {
        $q = $this->pdo->prepare("DELETE FROM {$this->table} WHERE last_activity < ?");
        $q->execute([time() - $duree_max]);
        return $q->rowCount();
    }
}

// ============================================================
//  Où vivent réellement les comptes
//
//  Trois cas, essayés dans cet ordre :
//
//  1. Une connexion dédiée à la base du portail, avec SES identifiants
//     MySQL (DB_USER_CAUSSELOT). C'est la voie la plus simple sur un
//     hébergement mutualisé : chaque base garde son propre utilisateur,
//     et aucun droit inter-bases n'est à demander. L'authentification
//     ne fait d'ailleurs aucune jointure entre les deux bases.
//
//  2. La connexion de TraçaBoucher, en nommant la base du portail dans
//     la requête. Cela exige que le même utilisateur MySQL ait accès aux
//     deux bases, sans quoi MySQL refuse (erreur 1142).
//
//  3. À défaut, la table `utilisateurs` locale. Les comptes d'origine
//     sont toujours là : mieux vaut un atelier qui travaille sans la
//     connexion unique qu'un atelier à la porte.
// ============================================================

// Une table est-elle réellement lisible par cette connexion ?
function table_lisible_sur(PDO $pdo, string $table): bool {
    try { $pdo->query("SELECT 1 FROM $table LIMIT 1"); return true; }
    catch (PDOException $e) { return false; }
}

function table_lisible(string $table): bool {
    return table_lisible_sur(db(), $table);
}

// La configuration désigne-t-elle une autre base que celle de TraçaBoucher ?
function base_partagee_configuree(): bool {
    return DB_NAME_CAUSSELOT !== '' && DB_NAME_CAUSSELOT !== DB_NAME;
}

// Des identifiants propres à la base du portail ont-ils été fournis ?
function connexion_dediee_configuree(): bool {
    return base_partagee_configuree() && DB_USER_CAUSSELOT !== '';
}

/**
 * Connexion et tables à employer pour les comptes, résolues une fois.
 *
 * @return array{pdo: PDO, comptes: string, sessions: string, partage: bool}
 */
function comptes_source(): array {
    static $src = null;
    if ($src !== null) return $src;

    // 1. Connexion dédiée à la base du portail.
    if (connexion_dediee_configuree()) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST_CAUSSELOT . ';dbname=' . DB_NAME_CAUSSELOT . ';charset=utf8mb4',
                DB_USER_CAUSSELOT, DB_PASS_CAUSSELOT,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES => false]
            );
            return $src = ['pdo' => $pdo, 'comptes' => 'utilisateurs',
                           'sessions' => 'sessions', 'partage' => true];
        } catch (PDOException $e) {
            error_log('Connexion dédiée à ' . DB_NAME_CAUSSELOT . ' impossible : ' . $e->getMessage());
        }
    }

    // 2. Connexion de TraçaBoucher, base du portail nommée dans la requête.
    if (base_partagee_configuree()) {
        $comptes = '`' . DB_NAME_CAUSSELOT . '`.utilisateurs';
        if (table_lisible_sur(db(), $comptes)) {
            return $src = ['pdo' => db(), 'comptes' => $comptes,
                           'sessions' => '`' . DB_NAME_CAUSSELOT . '`.sessions',
                           'partage' => true];
        }
    }

    // 3. Comptes locaux.
    return $src = ['pdo' => db(), 'comptes' => 'utilisateurs',
                   'sessions' => 'sessions', 'partage' => false];
}

function pdo_comptes(): PDO   { return comptes_source()['pdo']; }
function table_comptes(): string { return comptes_source()['comptes']; }

// Vrai quand les comptes lus sont bien ceux du portail CAUSSELOT.
function comptes_partages(): bool { return comptes_source()['partage']; }

/**
 * Sessions partagées, à une condition stricte.
 *
 * La table `sessions` ne porte que des identifiants numériques de
 * comptes. Les partager en lisant les comptes ailleurs ferait passer
 * l'utilisateur n° 3 du portail pour l'utilisateur n° 3 de TraçaBoucher
 * — deux personnes différentes, et potentiellement deux rôles
 * différents. Les sessions ne sont donc partagées que lorsque les
 * comptes le sont aussi ; sinon on repart sur des sessions fichier.
 */
function table_sessions_causselot_accessible(): bool {
    static $ok = null;
    if ($ok === null) {
        $src = comptes_source();
        $ok = $src['partage'] && table_lisible_sur($src['pdo'], $src['sessions']);
    }
    return $ok;
}

/**
 * Ce qui empêche la connexion unique de fonctionner, en clair, ou null
 * si tout va bien. Sert aux écrans d'administration ; le message nomme
 * des bases MySQL et n'est jamais montré à un visiteur non connecté.
 */
function diagnostic_comptes(): ?string {
    if (!base_partagee_configuree()) {
        return "DB_NAME_CAUSSELOT n'est pas renseigné dans config.local.php : "
             . "les comptes restent propres à TraçaBoucher.";
    }
    if (comptes_partages()) return null;
    if (connexion_dediee_configuree()) {
        return "La connexion à la base « " . DB_NAME_CAUSSELOT . " » avec ses propres "
             . "identifiants (DB_USER_CAUSSELOT / DB_PASS_CAUSSELOT) est refusée par "
             . "MySQL. Reprenez-les dans le config.local.php d'app.causselot.fr. "
             . "En attendant, les comptes locaux prennent le relais.";
    }
    return "La base « " . DB_NAME_CAUSSELOT . " » est inaccessible à l'utilisateur MySQL "
         . "de TraçaBoucher. Le plus simple est d'ajouter DB_USER_CAUSSELOT et "
         . "DB_PASS_CAUSSELOT dans config.local.php, repris du config.local.php "
         . "d'app.causselot.fr : aucun droit inter-bases n'est alors nécessaire. "
         . "En attendant, les comptes locaux prennent le relais.";
}

// Aucune table de comptes lisible : l'application ne peut rien faire.
function comptes_introuvables(): bool {
    return !table_lisible_sur(pdo_comptes(), table_comptes());
}

/**
 * Les deux schémas ne sont pas identiques : `email` n'existe que dans
 * celui d'app.causselot.fr, et `derniere_connexion` peut manquer sur un
 * TraçaBoucher installé de longue date. On interroge donc la table
 * plutôt que de supposer ses colonnes.
 */
function colonne_comptes(string $colonne): bool {
    static $colonnes = null;
    if ($colonnes === null) {
        $colonnes = [];
        try {
            foreach (pdo_comptes()->query('SHOW COLUMNS FROM ' . table_comptes()) as $c) {
                $colonnes[strtolower($c['Field'])] = true;
            }
        } catch (PDOException $e) {
            // Table illisible : les appelants le découvriront autrement.
        }
    }
    return isset($colonnes[strtolower($colonne)]);
}

function session_demarrer(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    if (table_sessions_causselot_accessible()) {
        $src = comptes_source();
        session_set_save_handler(new SessionBD($src['pdo'], $src['sessions']));
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '.causselot.fr',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);
    session_name('CAUSSESSION'); // même cookie que app.causselot.fr : c'est ce qui fait le SSO
    session_start();
}

function utilisateur_courant(): ?array {
    session_demarrer();
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u === null) {
        $q = pdo_comptes()->prepare('SELECT id, identifiant, nom, role, actif FROM ' . table_comptes() . ' WHERE id=?');
        $q->execute([$_SESSION['uid']]);
        $u = $q->fetch() ?: false;
        if (!$u || !$u['actif']) { deconnexion(); return null; }
    }
    return $u ?: null;
}

function exiger_connexion(): array {
    $u = utilisateur_courant();
    if (!$u) {
        $cible = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: login.php?suite=' . urlencode($cible));
        exit;
    }
    return $u;
}

function exiger_admin(): array {
    $u = exiger_connexion();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        exit('<p style="font-family:sans-serif;padding:24px">Accès réservé aux administrateurs. <a href="index.php">Retour</a></p>');
    }
    return $u;
}

function connecter(string $identifiant, string $mdp): ?array {
    session_demarrer();
    $q = pdo_comptes()->prepare('SELECT * FROM ' . table_comptes() . ' WHERE identifiant=? AND actif=1');
    $q->execute([$identifiant]);
    $u = $q->fetch();
    if (!$u || !password_verify($mdp, $u['mot_de_passe'])) return null;

    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    if (colonne_comptes('derniere_connexion')) {
        pdo_comptes()->prepare('UPDATE ' . table_comptes() . ' SET derniere_connexion=NOW() WHERE id=?')
            ->execute([$u['id']]);
    }
    return $u;
}

function deconnexion(): void {
    session_demarrer();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function aucun_compte(): bool {
    try {
        return (int)pdo_comptes()->query('SELECT COUNT(*) FROM ' . table_comptes())->fetchColumn() === 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Message d'erreur si le mot de passe est trop faible, null sinon
function verifier_force_mdp(string $mdp): ?string {
    if (strlen($mdp) < 10) return 'Le mot de passe doit contenir au moins 10 caractères.';
    if (!preg_match('/[A-Za-z]/', $mdp) || !preg_match('/[0-9]/', $mdp)) {
        return 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
    }
    return null;
}
