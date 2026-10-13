<?php
declare(strict_types=1);

/**
 * Helpers métier : libellés, droits de groupes, génération de référence, RG12.
 */

const LIBELLES_STATUT = [
    'signalee'             => 'Signalée',
    'en_cours'             => 'En cours',
    'resolue'              => 'Résolue',
    'non_resolue'          => 'Non résolue',
    'en_attente_materiel'  => 'En attente de matériel',
];

const LIBELLES_PRIORITE = [
    'basse'   => 'Basse',
    'normale' => 'Normale',
    'haute'   => 'Haute',
    'urgente' => 'Urgente',
];

const LIBELLES_BESOIN = [
    'signale'  => 'Signalé',
    'commande' => 'Commandé',
    'recu'     => 'Reçu',
];

const CLASSE_STATUT = [
    'signalee' => 'secondary', 'en_cours' => 'primary', 'resolue' => 'success',
    'non_resolue' => 'danger', 'en_attente_materiel' => 'warning text-dark',
];

/** Est membre actif du groupe ? (un administrateur est considéré comme supervisant tout) */
function estMembre(int $groupeId, int $userId): bool
{
    $q = Database::pdo()->prepare(
        'SELECT 1 FROM membres_groupe WHERE groupe_id = ? AND utilisateur_id = ?
         AND retire_par_id IS NULL AND date_retrait IS NULL'
    );
    $q->execute([$groupeId, $userId]);
    return (bool)$q->fetchColumn();
}

function estChef(int $groupeId, int $userId): bool
{
    $q = Database::pdo()->prepare(
        'SELECT 1 FROM membres_groupe WHERE groupe_id = ? AND utilisateur_id = ? AND est_chef = 1
         AND retire_par_id IS NULL AND date_retrait IS NULL'
    );
    $q->execute([$groupeId, $userId]);
    return (bool)$q->fetchColumn();
}

/** L'utilisateur peut-il gérer les membres de ce groupe ? */
function peutGererGroupe(array $user, int $groupeId): bool
{
    if ($user['role_code'] === 'admin') {
        return true;
    }
    // chef, ou créateur encore propriétaire
    $q = Database::pdo()->prepare('SELECT createur_id FROM groupes WHERE id = ? AND archive_le IS NULL');
    $q->execute([$groupeId]);
    $createur = $q->fetchColumn();
    return $createur !== false && ((int)$createur === (int)$user['id'] || estChef($groupeId, (int)$user['id']));
}

/** RG12 : si le groupe ne compte plus aucun chef actif, l'archiver (même transaction que l'action). */
function archiverSiSansChef(int $groupeId): void
{
    $pdo = Database::pdo();
    $q = $pdo->prepare(
        'SELECT COUNT(*) FROM membres_groupe m
         JOIN utilisateurs u ON u.id = m.utilisateur_id
         WHERE m.groupe_id = ? AND m.est_chef = 1 AND m.retire_par_id IS NULL
           AND m.date_retrait IS NULL AND u.actif = 1'
    );
    $q->execute([$groupeId]);
    if ((int)$q->fetchColumn() === 0) {
        $pdo->prepare("UPDATE groupes SET archive_le = NOW(), archive_motif = 'sans_chef'
                       WHERE id = ? AND archive_le IS NULL")
            ->execute([$groupeId]);
        Journal::log(Security::utilisateurCourant()['id'] ?? null, Journal::GROUPE_ARCHIVE,
            'groupe', $groupeId, $groupeId, 'Aucun chef actif (RG12)');
    }
}

/** RG12, désactivation de compte : vérifie tous les groupes où l'utilisateur était chef. */
function archiverGroupesDe(int $utilisateurId): void
{
    $q = Database::pdo()->prepare(
        'SELECT DISTINCT groupe_id FROM membres_groupe
         WHERE utilisateur_id = ? AND est_chef = 1 AND retire_par_id IS NULL AND date_retrait IS NULL'
    );
    $q->execute([$utilisateurId]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $groupeId) {
        archiverSiSansChef((int)$groupeId);
    }
}

/**
 * Génère la référence lisible suivante : INT-AAAA-NNNN.
 * À appeler après l'INSERT dans la même transaction (la référence utilise l'id).
 */
function attribuerReference(PDO $pdo, int $idIntervention): string
{
    $annee = (int)date('Y');
    $q = $pdo->prepare('SELECT COUNT(*) FROM interventions
                        WHERE reference LIKE ?');
    $q->execute(['INT-' . $annee . '-%']);
    $numero = ((int)$q->fetchColumn()) + 1; // les références sont ajoutées au fil de l'eau, l'ordre d'id suit
    return sprintf('INT-%d-%04d', $annee, max($numero, (int)$idIntervention));
}

/** Espace personnel : l'intervention (sans groupe) appartient-elle à l'utilisateur (ou admin en supervision) ? */
function peutAccederIntervention(array $user, array $intervention): bool
{
    if ($user['role_code'] === 'admin') {
        return true;
    }
    if ($intervention['groupe_id'] === null) {
        return (int)$intervention['cree_par_id'] === (int)$user['id'];
    }
    return estMembre((int)$intervention['groupe_id'], (int)$user['id']);
}
