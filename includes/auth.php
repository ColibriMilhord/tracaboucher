<?php
// ============================================================
//  Authentification par comptes nominatifs
// ============================================================
require_once __DIR__ . '/db.php';

function session_demarrer(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);
    session_name('TRACASESS');
    session_start();
}

function utilisateur_courant(): ?array {
    session_demarrer();
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u === null) {
        $q = db()->prepare('SELECT id, identifiant, nom, role, actif FROM utilisateurs WHERE id=?');
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
    $q = db()->prepare('SELECT * FROM utilisateurs WHERE identifiant=? AND actif=1');
    $q->execute([$identifiant]);
    $u = $q->fetch();
    if (!$u || !password_verify($mdp, $u['mot_de_passe'])) return null;

    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    db()->prepare('UPDATE utilisateurs SET derniere_connexion=NOW() WHERE id=?')->execute([$u['id']]);
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
        return (int)db()->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn() === 0;
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
