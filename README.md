# Gestion des interventions du service technique — CHU

Application web PHP 8 / MySQL 8 (PDO) avec Bootstrap 5, conforme au cahier des charges
(ci-joint aux PDF de conception). Interface responsive, traçabilité complète, journal en
ajout seulement, fiche d'intervention en PDF (Dompdf) **et** page d'impression navigateur.

## 1. Installation (Windows / WampServer)

1. Copier le dossier `gestion-interventions` dans `C:\wamp64\www\`.
2. Créer la base et ses 11 tables :
   `C:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe -u root < database/schema.sql`
3. Adapter les identifiants MySQL dans `config/config.local.php` (exclu de Git —
   copier `config.exemple.php` si besoin).
4. Créer le premier administrateur (clé en main, aucune inscription publique) :
   `php scripts/creer_admin.php "email@exemple.com" 'MotDePasse123456'`
   (min. 12 caractères ; seul le hachage est enregistré).
5. Ouvrir `http://localhost/gestion-interventions/public/login.php`.

Dependances PHP : `composer require dompdf/dompdf` (déjà installé dans `vendor/`,
exclu de Git : relancer la commande si le dossier vendor est absent).

## 2. Utilisation

- **Espace personnel** : interventions sans groupe, visibles de vous seul et de l'administrateur.
- **Groupes** : tout utilisateur peut en créer un (il en devient créateur **et** chef).
  Le créateur nomme/retire les chefs ; les chefs gèrent les membres (par e-mail, comptes actifs).
- **RG12** : si un groupe ne compte plus aucun chef actif (retrait, perte de chef,
  désactivation de compte), il est archivé automatiquement ; l'administrateur peut le restaurer.
- **Statuts** : signalée → en cours → résolue / non résolue / en attente de matériel.
  Les clôtures exigent un commentaire.
- **Besoins en matériel** : cycle signalé → commandé → reçu (pas de gestion de stock).
- **Fiche** : PDF (Dompdf, bleu marine et or) ou page d'impression navigateur.
- **Admin** : comptes (création, désactivation, réinitialisation par mot de passe temporaire),
  référentiels, supervision des groupes, journal complet.

## 3. Sécurité

- `password_hash` / `password_verify` ; minimum 12 caractères ; changement imposé
  côté serveur avant tout accès métier (`doit_changer_mot_de_passe`).
- Sessions PHP durcies : ID régénéré après connexion, inactivité 30 min
  (`SESSION_INACTIVITE_MINUTES`), cookies HttpOnly / SameSite=Lax / Secure si HTTPS.
- `auth_version` : pour chaque requête, le compte doit être actif et la version de la
  session correspondre — un compte désactivé est bloqué à la prochaine requête,
  et un changement de mot de passe invalide les autres sessions.
- Limitation des tentatives : 5 échecs en 15 minutes par e-mail **ou** adresse IP
  (`tentatives_connexion`), messages d'erreur génériques.
- CSRF : jeton de session vérifié sur tous les POST.
- PDO + requêtes préparées partout ; exceptions ; émulation désactivée.
- Journal d'activités en ajout seulement, sans mot de passe ni secret.
- Secrets uniquement dans `config/config.local.php` (dans `.gitignore`).

## 4. Tests (cahier des charges, section 10) et résultats

| Test | Résultat attendu | Vérifié |
|---|---|---|
| Utilisateur non-admin sur page d'admin | 403 + événement journalisé | ✔ |
| Compte désactivé pendant sa session | prochaine requête refusée | ✔ (auth_version) |
| Première connexion avec mot de passe temporaire | redirection obligatoire vers le changement | ✔ |
| Changement de mot de passe | sessions précédentes invalidées | ✔ |
| Mauvais mot de passe / e-mail inconnu | message générique identique | ✔ |
| Plus de 5 échecs en 15 min | blocage temporaire | ✔ |
| Retrait du dernier chef | groupe archivé automatiquement, restauration possible | ✔ (RG12) |
| Suppression d'intervention | masquée, auteur et date au journal | ✔ |
| Service « Autre » vide | refusé (validation + CHECK SQL) | ✔ |
| Fiche PDF / impression par non-membre | accès refusé | ✔ |

## 5. Mise en production — procédure recommandée

1. **HTTPS** partout : certificat renouvelé, `APP_ENV = 'production'`, `display_errors = 0`.
2. **MySQL** : créer un utilisateur applicatif dédié (pas root), mot de passe fort,
   privilège sur `gestion_interventions` uniquement.
3. **Sauvegardes** : `mysqldump` quotidien planifié, rétention sur un support séparé,
   tester la restauration régulièrement.
4. **Git** : ne jamais committer `config/config.local.php` ni `vendor/`.
5. **Journal & RG11** : aucune opération de modification/vidage n'existe dans le code ;
   privilégier un compte MySQL en lecture seule pour les audits.
6. Ne jamais saisir de données de patient (RG19) ; l'analyse de conformité aux règles
   locales de protection des données reste à la charge de l'établissement.

## 6. Limites assumées

- Aucune application n'est parfaitement sécurisée : la protection repose sur les
  contrôles listés ci-dessus et le principe du moindre privilège côté serveur.
- Notifications e-mail/SMS non prévues (v1) ; statistiques avancées possibles plus tard
  grâce aux référentiels.
