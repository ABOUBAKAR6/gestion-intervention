<?php
declare(strict_types=1);

/**
 * Sécurité transversale : session durcie, CSRF, échappement, gardes d'accès.
 * Contrôles côté serveur sur CHAQUE page et action (section 6 du cahier).
 */
final class Security
{
    public static function demarrerSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('gi_session');
        $cookie = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $cookie['path'],
            'domain'   => $cookie['domain'],
            'secure'   => (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? 'off') !== 'off'), // Secure en production (HTTPS)
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        if (!isset($_SESSION['derniere_activite'])) {
            $_SESSION['derniere_activite'] = time();
        }
        // Inactivité limitée et configurable (30 min par défaut)
        if (time() - (int)$_SESSION['derniere_activite'] > SESSION_INACTIVITE_MINUTES * 60) {
            self::detruireSession();
            header('Location: ' . BASE_URL . '/login.php?expire=1');
            exit;
        }
        $_SESSION['derniere_activite'] = time();
    }

    public static function detruireSession(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        setcookie(session_name(), '', time() - 3600, '/', '', false, true);
    }

    /** Régénère l'identifiant de session après authentification */
    public static function renouvelerSession(): void
    {
        session_regenerate_id(true);
    }

    /** Utilisateur de la session courante si valide (actif + auth_version), sinon null. */
    public static function utilisateurCourant(): ?array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        if (empty($_SESSION['utilisateur_id'])) {
            return null;
        }
        $u = Database::pdo()->prepare(
            'SELECT u.*, r.code AS role_code, r.nom AS role_nom FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id WHERE u.id = ?'
        );
        $u->execute([$_SESSION['utilisateur_id']]);
        $user = $u->fetch();
        // Vérification à chaque requête : compte actif + version de session cohérente
        if (!$user || (int)$user['actif'] !== 1
            || (int)$user['auth_version'] !== (int)($_SESSION['auth_version'] ?? -1)) {
            self::detruireSession();
            return $cache = null;
        }
        return $cache = $user;
    }

    /* ---------- CSRF ---------- */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf" value="' . self::e(self::csrfToken()) . '">';
    }

    public static function csrfVerifier(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }
        if (!hash_equals(self::csrfToken(), (string)($_POST['csrf'] ?? ''))) {
            http_response_code(403);
            exit('Requête refusée (jeton CSRF invalide).');
        }
    }

    /* ---------- Gardes ---------- */
    public static function exigerConnexion(): array
    {
        Security::demarrerSession();
        $u = self::utilisateurCourant();
        if ($u === null) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
        // Changement obligatoire du mot de passe avant tout accès métier
        if ((int)$u['doit_changer_mot_de_passe'] === 1) {
            $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
            if ($script !== 'changer_mdp.php' && $script !== 'deconnexion.php') {
                header('Location: ' . BASE_URL . '/changer_mdp.php');
                exit;
            }
        }
        return $u;
    }

    public static function exigerAdmin(): array
    {
        $u = self::exigerConnexion();
        if ($u['role_code'] !== 'admin') {
            Journal::log((int)$u['id'], 'acces_refuse_admin', 'page',
                null, null, 'Script : ' . ($_SERVER['SCRIPT_NAME'] ?? '?'));
            http_response_code(403);
            exit('Accès refusé : réservé à l\'administrateur.');
        }
        return $u;
    }

    /* ---------- Divers ---------- */
    public static function e(?string $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function flashBoite(): string
    {
        $html = '';
        $defs = ['succes' => 'success', 'erreur' => 'danger', 'info' => 'info', 'avertissement' => 'warning'];
        if (!empty($_SESSION['flash'])) {
            foreach ($_SESSION['flash'] as $f) {
                $c = $defs[$f['type']] ?? 'secondary';
                $html .= '<div class="alert alert-' . $c . ' alert-dismissible fade show">'
                      . self::e($f['message'])
                      . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            }
            unset($_SESSION['flash']);
        }
        return $html;
    }

    public static function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
