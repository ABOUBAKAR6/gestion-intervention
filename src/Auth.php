<?php
declare(strict_types=1);

/**
 * Authentification : e-mail + mot de passe uniquement (RG2).
 * - password_hash / password_verify, jamais de mot de passe en clair
 * - limitation des tentatives : CONNEXION_ECHECS_MAX échecs en CONNEXION_FENETRE_MINUTES
 *   par e-mail ET par adresse IP, puis blocage temporaire
 * - messages génériques : ne révèlent pas l'existence d'un compte
 * - identifiant de session régénéré après authentification
 */
final class Auth
{
    public static function connexionBloquee(string $email, string $ip): bool
    {
        $pdo = Database::pdo();
        $t = $pdo->prepare(
            'SELECT COUNT(*) FROM tentatives_connexion
             WHERE reussie = 0 AND created_at > (NOW() - INTERVAL ' . CONNEXION_FENETRE_MINUTES . ' MINUTE)
               AND (email_normalise = ? OR adresse_ip = ?)'
        );
        $t->execute([$email, $ip]);
        return (int)$t->fetchColumn() >= CONNEXION_ECHECS_MAX;
    }

    /**
     * Tente la connexion. Retourne l'utilisateur ou null.
     * message est toujours générique.
     */
    public static function connecter(string $email, string $motDePasse, string $ip): ?array
    {
        $pdo   = Database::pdo();
        $email = self::normaliserEmail($email);

        if (self::connexionBloquee($email, $ip)) {
            return null;
        }

        $q = $pdo->prepare(
            'SELECT u.*, r.code AS role_code FROM utilisateurs u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ?'
        );
        $q->execute([$email]);
        $user = $q->fetch();

        $ok = $user !== false
              && (int)$user['actif'] === 1
              && password_verify($motDePasse, $user['password_hash'])
              && !password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT);

        // Trace de chaque tentative (blocage/rate-limit), sans jamais stocker le mot de passe
        $pdo->prepare('INSERT INTO tentatives_connexion (email_normalise, adresse_ip, reussie)
                       VALUES (?, ?, ?)')
            ->execute([$email, $ip, $ok ? 1 : 0]);

        if (!$ok) {
            return null;
        }

        $pdo->prepare('UPDATE utilisateurs SET last_login_at = NOW() WHERE id = ?')
            ->execute([$user['id']]);

        Security::renouvelerSession();
        $_SESSION['utilisateur_id'] = (int)$user['id'];
        $_SESSION['auth_version']   = (int)$user['auth_version'];
        $_SESSION['derniere_activite'] = time();

        Journal::log((int)$user['id'], Journal::CONNEXION_REUSSIE);
        return $user;
    }

    /**
     * Change le mot de passe du compte : minimum 12 caractères.
     * Augmente auth_version : toutes les autres sessions du compte sont invalidées.
     */
    public static function changerMotDePasse(int $userId, string $nouveau): bool
    {
        if (mb_strlen($nouveau) < MOT_DE_PASSE_MIN) {
            return false;
        }
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE utilisateurs
                       SET password_hash = ?, doit_changer_mot_de_passe = 0, auth_version = auth_version + 1
                       WHERE id = ?')
            ->execute([password_hash($nouveau, PASSWORD_DEFAULT), $userId]);
        $_SESSION['auth_version'] = (int)(self::version($userId));
        Journal::log($userId, Journal::COMPTE_MDP_CHANGE);
        return true;
    }

    public static function version(int $userId): int
    {
        $q = Database::pdo()->prepare('SELECT auth_version FROM utilisateurs WHERE id = ?');
        $q->execute([$userId]);
        return (int)$q->fetchColumn();
    }

    public static function deconnecter(int $userId): void
    {
        Journal::log($userId, 'deconnexion');
        Security::detruireSession();
    }

    /** Génère un mot de passe temporaire robuste et lisible. */
    public static function motDePasseTemporaire(int $longueur = 14): string
    {
        $jeu = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%';
        $out = '';
        $max = mb_strlen($jeu) - 1;
        for ($i = 0; $i < $longueur; $i++) {
            $out .= $jeu[random_int(0, $max)];
        }
        return $out;
    }

    public static function normaliserEmail(string $email): string
    {
        return mb_strtolower(trim(str_replace(' ', '', $email)));
    }
}
