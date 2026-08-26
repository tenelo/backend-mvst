# A revoir — comportements PHP douteux repliques a l'identique

Ce fichier recense les comportements du backend PHP source (`php-mvst/app/`)
qui ont ete **volontairement repliques a l'identique** dans Laravel pendant la
phase de migration, meme quand ils semblent etre des bugs ou des incoherences.
Consigne de la phase de migration : repliquer, ne pas corriger. Ces points
sont a trancher ensemble avant toute evolution du comportement.

Chaque entree indique : le fichier PHP source, l'endroit repris dans Laravel,
le comportement, et pourquoi il est douteux.

## Lot 1 — Admins

### 1. `ajouterAdmin.php` — ecrasement par chaines vides sur mise a jour partielle

- Laravel : `App\Http\Controllers\Legacy\AdminController::ajouterAdmin`
- Route : `POST /ajouterAdmin.php`

Les champs `idAuth`, `nom`, `prenoms`, `residence`, `mail` sont **toujours**
ecrits dans la clause `UPDATE`, avec `''` comme valeur par defaut si absents
du payload. Consequence : un appel qui ne fournit que `idUtilisateur` et
`telephone` **efface** silencieusement les valeurs deja enregistrees pour
ces 5 champs (elles deviennent des chaines vides, pas `NULL`).

De plus, `rowCount()` n'est jamais verifie : la reponse est
`{"success":true,"message":"Admin mis à jour"}` meme si aucune ligne
n'a ete modifiee (telephone inconnu).

**A trancher** : est-ce le comportement voulu (mise a jour toujours
"complete", jamais partielle), ou faut-il passer a une mise a jour
conditionnelle (seuls les champs presents dans le payload sont modifies,
comme le fait deja `update_utilisateur.php` / lot 2) ?

### 2. `modifierNumeroAdmin.php` — role non valide contrairement a la creation

- Laravel : `App\Http\Controllers\Legacy\AdminController::modifierNumero`
- Route : `POST /modifierNumeroAdmin.php`

`ajouterNumeroAdmin.php` (creation d'un admin) verifie que `role` fait
partie de `['admin', 'superadmin']` avant d'inserer. `modifierNumeroAdmin.php`
(modification d'un admin pas encore finalise, condition `nom IS NULL`) ne
fait **aucune** verification equivalente sur `role` : une valeur arbitraire
est acceptee et ecrite telle quelle en base tant que le compte n'est pas
finalise.

**A trancher** : ajouter la meme liste blanche `['admin', 'superadmin']`
sur `modifierNumeroAdmin.php` (bug a corriger), ou est-ce intentionnel
(un superadmin pourrait vouloir un role personnalise a ce stade) ?

### 3. `supprimerNumeroAdmin.php` — succes meme si rien n'a ete supprime

- Laravel : `App\Http\Controllers\Legacy\AdminController::supprimerNumero`
- Route : `POST /supprimerNumeroAdmin.php`

`rowCount()` non verifie : `{"success":true,"message":"Numéro supprimé"}`
meme si le `telephone` fourni ne correspondait a aucun admin. Impact limite
(operation idempotente, sans consequence visible cote app), mais signale
par coherence avec les points 1 et 5.

### 4. `verifierAdmin.php` — reponse d'echec sans cle `message`

- Laravel : `App\Http\Controllers\Legacy\AdminController::verifier`
- Route : `POST /verifierAdmin.php`

Quand aucun admin ne correspond au `telephone` fourni, la reponse est
`{"success":false,"existe":false}` — **sans** cle `message`, contrairement a
la quasi-totalite des autres reponses d'echec du projet. Repris a l'identique
pour ne pas casser un eventuel test cote app sur l'absence de cette cle.

### 5. `verifierTelephoneAdmin.php` — `success` ne signale pas l'autorisation

- Laravel : `App\Http\Controllers\Legacy\AdminController::verifierTelephone`
- Route : `POST /verifierTelephoneAdmin.php`

`success` vaut `true` dans tous les cas "normaux", **y compris** quand le
numero n'est pas du tout admin (`autorise:false, existe:false`). `success`
signale seulement que la requete SQL a fonctionne, pas que le numero est
autorise. Piege potentiel si une partie du code app ne lit que `success`
au lieu de `autorise`/`existe`. A surveiller particulierement si l'app
cliente est un jour modifiee ou reecrite.

## Lot 2 — Utilisateurs

### 6. `insert_utilisateur.php` — email genere automatiquement, non verifie

- Laravel : `App\Http\Controllers\Legacy\UtilisateurController::insert`
- Route : `POST /insert_utilisateur.php`

Si `mail` est absent du payload, la valeur `"<telephone>@gmail.com"` est
utilisee comme adresse email, sans aucune verification qu'elle est valide,
reellement possedee par l'utilisateur, ou meme d'un format d'email correct
au sens strict. Sans consequence grave en pratique (le doublon est detecte
sur `telephone`/`idUtilisateur`, pas sur `mail`), mais si un client fournit
explicitement un `mail` qui entre en conflit avec la contrainte UNIQUE de la
colonne, l'erreur PostgreSQL brute remonte telle quelle dans `message`
(pas de message convivial du type "email deja utilise").

**A trancher** : garder la generation automatique telle quelle, ou
retirer/ameliorer ce comportement lors d'une passe de nettoyage ulterieure ?

### 7. `update_utilisateur.php` — succes meme si `idUtilisateur` n'existe pas

- Laravel : `App\Http\Controllers\Legacy\UtilisateurController::update`
- Route : `POST /update_utilisateur.php`

Le PHP source ne teste que le booleen renvoye par `PDOStatement::execute()`
(`true` des que la requete s'execute sans erreur SQL), jamais `rowCount()`.
Consequence : `{"success":true,"message":"Profil mis à jour avec succès"}`
est renvoye meme quand `idUtilisateur` ne correspond a aucune ligne (0 ligne
modifiee). Meme famille de probleme que les points 1 et 3 du lot Admins.
Confirme par test (`idUtilisateur` fictif "ghost_user_xyz").

## Lot 3 — Points

### 8. `decrementerPoints.php` — "mettreAZero" teste en truthy PHP brut

- Laravel : `App\Http\Controllers\Legacy\PointsController::decrementer`
- Route : `POST /decrementerPoints.php`

Le code source fait `if ($mettreAZero)` directement sur la valeur JSON
recue, sans comparaison stricte `=== true`. Consequence : envoyer
`"mettreAZero": "false"` (chaine de caracteres) au lieu de `false` (booleen
JSON) declenche a tort le blocage total a 0 point ("Blocage automatique...")
au lieu d'un simple decrement de 1. **Confirme par test** (`mettreAZero`
envoye comme la chaine `"false"` -> points mis a 0 des deux cotes, PHP et
Laravel). Comme le controleur Laravel est ecrit en PHP, l'expression
`if ($mettreAZero)` a ete reprise telle quelle : le piege est donc reproduit
automatiquement, sans effort de "traduction".

**A trancher** : corriger en amont (cote app Flutter, s'assurer de toujours
envoyer un vrai booleen JSON), ou blinder cote serveur avec un cast strict
`filter_var($mettreAZero, FILTER_VALIDATE_BOOLEAN)` — ce qui serait une
correction, pas une replication ?

### 9. `reinitialiserPoints.php` (lister_tous / lister_bloques) — plantage sur limit=0, ET deviation de la decision HTTP 200

- Laravel : `App\Http\Controllers\Legacy\PointsController::listerTous` /
  `listerBloques`
- Route : `POST /reinitialiserPoints.php` (actions `lister_tous`,
  `lister_bloques`)

**Le plus important des points signales sur ce lot.** Ni `lister_tous` ni
`lister_bloques` ne se protegent contre `limit=0`. Le calcul
`ceil($total / $limit)` (`totalPages`) leve alors une `\DivisionByZeroError`
en PHP 8. Cette classe herite de `\Error`, **pas** de `\Exception` : le
`catch (Exception $e)` du fichier source (et de son equivalent Laravel) ne
l'intercepte pas.

Comportement du PHP source, confirme par test : la reponse est un fatal
error PHP brut au format HTML (chemin de fichier serveur expose dans le
message), mais avec tout de meme le code **HTTP 200** (php-fpm avait deja
envoye l'entete de statut avant que l'erreur ne survienne).

Comportement de Laravel, confirme par test avec le meme payload
(`{"action":"lister_tous","limit":0}`) : l'erreur remonte au gestionnaire
d'exceptions global de Laravel, qui repond en **HTTP 500** avec sa propre
page de debogage HTML (tres differente du fatal error brut du PHP source,
et contraire a la decision figee n1 "toujours HTTP 200").

Aucun garde-fou n'a ete ajoute dans le code Laravel (pas de correction du
bug, conformement a la consigne "repliquer, pas ameliorer"), mais il est
techniquement impossible de reproduire un fatal error PHP brut au sens
strict a l'identique (200 + HTML "Uncaught DivisionByZeroError...") sans
intercepter deliberement cette erreur, ce qui reviendrait a la traiter
differemment du PHP source de toute facon.

**Decision (26/08/2026)** : option (b) retenue. `listerTous()` et
`listerBloques()` (methodes privees de `PointsController`) capturent
localement `\Throwable` (donc aussi `\DivisionByZeroError`) et renvoient le
format JSON standard `{"success":false,"message":"Division by zero"}` en
HTTP 200, au lieu de laisser l'erreur remonter au gestionnaire global de
Laravel (qui aurait repondu HTTP 500 + page de debogage HTML).

**Deviation assumee de la decision figee n1**, documentee explicitement :
- PHP source, sur `limit=0` : HTTP 200 + fatal error brut en HTML (chemin de
  fichier serveur expose).
- Laravel, sur `limit=0` : HTTP 200 + JSON `{"success":false,"message":"Division by zero"}`.

Le code HTTP reste 200 dans les deux cas (donc la decision n1 est respectee
au sens strict), mais le corps de reponse differe volontairement : on
privilegie ici la coherence du contrat JSON que les apps savent lire plutot
qu'une replication litterale d'un fatal error PHP de toute facon impossible
a reproduire a l'identique (chemins de fichiers differents entre les deux
projets). **Le bug logique lui-meme n'est pas corrige** : `limit=0` reste
une valeur jamais validee ni rejetee explicitement, elle declenche juste une
erreur geree proprement au lieu d'un plantage brut. Verifie par test :
memes reponses `{"success":true,...}` qu'avant sur les appels normaux (non
regression), et reponse JSON propre confirmee sur `limit=0` pour les deux
actions.

---

*Ce fichier sera complete au fil des lots suivants (Tickets, Places,
Departs, etc.) a chaque fois qu'un comportement du PHP source meritera
d'etre signale avant d'etre eventuellement corrige.*
