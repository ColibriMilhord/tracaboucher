<?php
// ============================================================
//  Authentification par comptes nominatifs — session partagée
//  (SSO) avec app.causselot.fr : la table `utilisateurs` et la
//  table `sessions` vivent dans la base causselot (DB_NAME_CAUSSELOT),
//  pas dans celle de TraçaBoucher. Se connecter une fois sur
//  app.causselot.fr suffit donc pour être déjà connecté ici.
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

// Tant que USER_TRACA n'a pas reçu l'accès à la base causselot dans
// hPanel (voir docs/integration-tracabilite.md), la table qualifiée
// n'est pas accessible : on continue alors avec des sessions fichier
// classiques plutôt que de bloquer complètement l'application.
function table_sessions_causselot_accessible(): bool {
    static $ok = null;
    if ($ok === null) {
        try { db()->query('SELECT 1 FROM ' . DB_NAME_CAUSSELOT . '.sessions LIMIT 1'); $ok = true; }
        catch (PDOException $e) { $ok = false; }
    }
    return $ok;
}

function session_demarrer(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    if (table_sessions_causselot_accessible()) {
        session_set_save_handler(new SessionBD(db(), DB_NAME_CAUSSELOT . '.sessions'));
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
        $q = db()->prepare('SELECT id, identifiant, nom, role, actif FROM ' . DB_NAME_CAUSSELOT . '.utilisateurs WHERE id=?');
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
    $q = db()->prepare('SELECT * FROM ' . DB_NAME_CAUSSELOT . '.utilisateurs WHERE identifiant=? AND actif=1');
    $q->execute([$identifiant]);
    $u = $q->fetch();
    if (!$u || !password_verify($mdp, $u['mot_de_passe'])) return null;

    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    db()->prepare('UPDATE ' . DB_NAME_CAUSSELOT . '.utilisateurs SET derniere_connexion=NOW() WHERE id=?')->execute([$u['id']]);
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
        return (int)db()->query('SELECT COUNT(*) FROM ' . DB_NAME_CAUSSELOT . '.utilisateurs')->fetchColumn() === 0;
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
