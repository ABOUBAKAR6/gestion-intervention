<?php
declare(strict_types=1);

/**
 * Journal d'activités (RG10/RG11) : table en ajout seulement.
 * Aucune méthode de modification ou suppression n'existe.
 * Jamais de mot de passe ni de secret dans les détails.
 */
final class Journal
{
    /** Actions connues (documentation) */
    public const COMPTES_CRUES       = 'compte_cree';
    public const COMPTE_DESACTIVE    = 'compte_desactive';
    public const COMPTE_REACTIVER    = 'compte_reactive';
    public const COMPTE_MDP_RESET    = 'compte_mdp_reset';
    public const COMPTE_MDP_CHANGE   = 'mdp_change';
    public const CONNEXION_REUSSIE   = 'connexion_reussie';
    public const CONNEXION_ECHOUEE   = 'connexion_echouee';
    public const GROUPE_CREE         = 'groupe_cree';
    public const GROUPE_ARCHIVE      = 'groupe_archive';
    public const GROUPE_RESTORE      = 'groupe_restaure';
    public const MEMBRE_AJOUTE       = 'membre_ajoute';
    public const MEMBRE_RETIRE       = 'membre_retire';
    public const CHEF_NOMME          = 'chef_nomme';
    public const CHEF_RETIRE         = 'chef_retire';
    public const INTERV_CREES        = 'intervention_creee';
    public const INTERV_MODIFIEE     = 'intervention_modifiee';
    public const INTERV_STATUT       = 'intervention_statut_change';
    public const INTERV_SUPPRIMEE    = 'intervention_supprimee';
    public const BESOIN_AJOUTE       = 'besoin_ajoute';
    public const BESOIN_STATUT       = 'besoin_statut_change';
    public const REFERENTIEL_CRUE    = 'referentiel_cree';
    public const PDF_GENERE          = 'pdf_genere';

    public static function log(
        ?int $utilisateurId,
        string $action,
        string $objetType = '',
        ?int $objetId = null,
        ?int $groupeId = null,
        ?string $details = null
    ): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        Database::pdo()->prepare(
            'INSERT INTO journal_activites (utilisateur_id, action, objet_type, objet_id, groupe_id, details, adresse_ip)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$utilisateurId, $action, $objetType !== '' ? $objetType : null, $objetId, $groupeId, $details, $ip]);
    }
}
