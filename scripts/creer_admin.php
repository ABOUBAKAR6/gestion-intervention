<?php
declare(strict_types=1);

/**
 * Initialisation contrôlée du premier administrateur.
 * Procédure volontairement réservée à la ligne de commande (aucun formulaire public).
 *
 * Exécution :
 *   php scripts/creer_admin.php "email@exemple.com" 'MotDePasseRobuste123'
 *
 * Le mot de passe (min. 12 caractères) est haché ; rien n'est conservé en clair.
 */
require dirname(__DIR__) . '/src/init.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Script réservé à la ligne de commande.');
}

[, $email, $motDePasse] = array_pad($argv, 3, null);
$email = Auth::normaliserEmail((string)$email);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "E-mail invalide.\n");
    exit(1);
}
if (strlen((string)$motDePasse) < MOT_DE_PASSE_MIN) {
    fwrite(STDERR, 'Mot de passe trop court (' . MOT_DE_PASSE_MIN . ' caractères minimum).' . PHP_EOL);
    exit(1);
}

$pdo = Database::pdo();

$existe = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ?');
$existe->execute([$email]);
if ($existe->fetchColumn()) {
    $genre = $pdo->prepare(
        'SELECT r.code FROM utilisateurs u JOIN roles r ON r.id = u.role_id WHERE u.email = ?'
    );
    $genre->execute([$email]);
    fwrite(STDERR, 'Ce compte existe déjà (rôle : ' . $genre->fetchColumn() . '). Rien n\'a été modifié.' . PHP_EOL);
    exit(1);
}

$adminId = (int)$pdo->query("SELECT id FROM roles WHERE code = 'admin'")->fetchColumn();
$pdo->prepare(
    'INSERT INTO utilisateurs (role_id, nom, prenom, email, password_hash, actif, doit_changer_mot_de_passe)
     VALUES (?, "Admin", "Service technique", ?, ?, 1, 0)'
)->execute([$adminId, $email, password_hash((string)$motDePasse, PASSWORD_DEFAULT)]);

fwrite(STDOUT, 'Administrateur créé : ' . $email . PHP_EOL);
Journal::log((int)$pdo->lastInsertId(), Journal::COMPTES_CRUES, 'utilisateur', (int)$pdo->lastInsertId(),
    null, 'Premier administrateur initialisé en CLI');
exit(0);
