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

## Lot 4 — Gares / Divers config

### 10. `gares.php` / `infosGares.php` / `heuresDepart.php` — aucun isset() sur les champs POST, warning PHP qui casse le JSON

- Laravel : `App\Http\Controllers\Legacy\GareController::gares` /
  `::infosGares`, `App\Http\Controllers\Legacy\DiversController::heuresDepart`
- Routes : `POST /gares.php`, `POST /infosGares.php`, `POST /heuresDepart.php`

Contrairement a la quasi-totalite des endpoints des lots 1 a 3, ces 3
fichiers CRUD (actions `ajouter`/`modifier`) **ne font aucun `isset()`** sur
les champs attendus (`gare`, `ville`/`description`/`telephone`, `heure`)
avant de les utiliser. Consequence verifiee par test sur le PHP source
(`gares.php`, action `ajouter` sans le champ `gare`) :

```
<br />
<b>Warning</b>:  Undefined array key "gare" in /var/www/html/gares.php on line 29<br />
{"success":true,"message":"Gare ajoutée"}
```

**Le corps de reponse n'est donc pas du JSON valide** : un warning PHP est
prefixe avant le JSON. Un client qui fait `json_decode()` sur cette reponse
echouerait purement et simplement. La ligne est quand meme inseree en base
avec `gare = NULL` (colonne nullable), et `(int)$data['id']` absent devient
`0` pour `modifier`/`supprimer` (une requete `WHERE id = 0` qui ne touche
simplement aucune ligne, sans erreur).

**Decision (26/08/2026)** : validee. Les champs manquants sont lus avec
`?? null` (ou `?? 0` pour les id), ce qui **evite le warning** et produit un
JSON propre et valide `{"success":true,"message":"..."}`, avec la meme
consequence en base (colonne inseree a NULL, ou requete no-op sur id=0).
Aucune tentative de reproduire le warning PHP inline lui-meme : le
mecanisme de warning de Laravel (converti en `\ErrorException`, capture
differemment de PHP-FPM) rend cette reproduction non fiable de toute facon,
et un JSON casse n'est pas un comportement a "repliquer" au sens utile du
terme -- ce n'est pas un contrat, c'est un accident du PHP source.

**Deviation assumee de la decision figee n1** (meme logique qu'au point 9,
lot 3) : le PHP source produit un corps de reponse invalide (warning HTML +
JSON) sur un appel avec champ manquant ; Laravel produit un JSON valide
`{"success":true,...}` avec la colonne concernee a NULL/0. Le code HTTP
reste 200 des deux cotes. On privilegie la coherence du contrat JSON plutot
que la reproduction litterale d'un artefact d'erreur PHP non intentionnel.

### 11. Bug de production decouvert incidemment (hors perimetre de la migration) : sequence `InfosGares_id_seq` desynchronisee

En testant `infosGares.php` (action `ajouter`), le PHP source **et** Laravel
ont echoue de la meme facon avec une erreur de cle dupliquee :

```
SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates
unique constraint "InfosGares_pkey" DETAIL: Key (id)=(2) already exists.
```

Verification en base (lecture seule) : `MAX(id)` de `InfosGares` vaut `14`,
mais la sequence `InfosGares_id_seq` (`last_value`) est a `2`. La sequence
est desynchronisee de la table, probablement suite a une insertion passee
avec des `id` explicites qui a court-circuite la sequence.

**Consequence** : `infosGares.php` (action `ajouter`) etait **cassee en
production**, cote PHP existant, independamment de toute migration Laravel.
Ce n'etait pas une regression introduite ici (confirme par le test identique
cote PHP source), mais un bug pre-existant.

**Resolu (26/08/2026)** : la sequence a ete corrigee directement par
l'utilisateur (hors du perimetre de cette migration, aucune action de ma
part sur la base). Verifie en lecture seule apres correction :
`MAX(id) = 14` et `InfosGares_id_seq.last_value = 14` — synchronises.

## Lot 5 — Lignes / Prix

### 12. `api_lignes.php` — aucun try/catch dans tout le fichier

- Laravel : `App\Http\Controllers\Legacy\LignePrixController::apiLignes`
- Route : `ANY /api_lignes.php`

Contrairement a tous les autres fichiers vus jusqu'ici, `api_lignes.php` n'a
**aucun `try`/`catch`**, ni sur le GET ni sur le POST. Toute exception PDO
(contrainte violee, valeur trop longue, etc.) provoque un fatal error PHP
brut. **Confirme par test reel** : un `type` de 100 caracteres envoye a
l'action `ajouter` (colonne `type varchar(50)`) donne, cote PHP source :

```
<b>Fatal error</b>:  Uncaught PDOException: SQLSTATE[22001]: String data,
right truncated: 7 ERROR:  value too long for type character varying(50)
in /var/www/html/api_lignes.php:50
```

HTTP 200 malgre tout (meme mecanisme que le point 9). Verifie qu'aucune
ligne partielle n'est inseree des deux cotes (transaction implicite d'une
seule requete, echec propre).

**Deviation appliquee, meme decision que les points 9 et 10** (deja validee
par vous, pas re-demandee ici) : un `catch (\Throwable $e)` global entoure
tout le corps de la methode et renvoie `{"success":false,"error":...}` en
HTTP 200. La cle utilisee est **`error`**, pas `message` : ce fichier
n'utilise que `error` sur ses propres branches d'echec (decision figee n2),
donc le catch de secours suit la meme convention locale. Confirme par le
meme test : Laravel renvoie proprement
`{"success":false,"error":"SQLSTATE[22001]: ... (Connection: pgsql, ...)"}`
en HTTP 200 au lieu de planter.

Particularites reproduites a l'identique (pas des deviations, juste notees
pour memoire) : `ajouter` ne renvoie que `{"success","id"}` (pas de
`message`) ; `modifier` ne verifie jamais si une ligne a ete touchee et
renvoie toujours `{"success":true}` seul ; `supprimer` renvoie
`{"success": rowCount()>0}` sans aucune cle `error`/`message` en cas
d'echec. Confirme par test (id inexistant sur modifier -> `success:true`
quand meme ; sur supprimer -> `success:false` seul).

### 13. `prixTickets.php` — meme absence d'isset() que le point 10 (lot 4)

- Laravel : `App\Http\Controllers\Legacy\LignePrixController::prixTickets`
- Route : `POST /prixTickets.php`

`ajouter`/`modifier` accedent directement a `$data['axe']`/`$data['prix']`/
`$data['id']` sans `isset()`, exactement comme `gares.php`/`infosGares.php`/
`heuresDepart.php` (point 10, lot 4). Meme decision deja validee appliquee
sans re-demander : lecture avec `?? null`/`?? 0`, JSON propre en sortie.

### 14. Confirmation chiffree de la divergence `Lignes` / `PrixDesTickets` (deja signalee en phase 1, point D3)

Donnees reelles fournies par l'utilisateur le 26/08/2026 (lecture seule,
aucune ligne modifiee) :

| Axe | Type | `Lignes.prix` | `PrixDesTickets.prix` |
|---|---|---|---|
| Ferké → Abidjan | standard | 8100 (id 2) | 8000 (id 1) |
| Abidjan → Ferké | standard | 8100 (id 5) | 8000 (id 6) |
| Ferké → Abidjan | vip | 10000 (id 8) | 10000 (id 7) |
| Abidjan → Ferké | vip | 10000 (id 11) | 10000 (id 8) |

Les deux tables ont **divergé en production** sur l'axe Ferké-Abidjan en
`standard` (8100 vs 8000). Ce n'est pas une consequence de la migration :
etat constate avant toute modification. Les deux tables sont conservees
telles quelles (decision deja actee : "les deux systemes de tarification
conserves"), sans tentative de reconciliation. Question de phase 1 (D3)
toujours ouverte : laquelle des deux tables fait foi cote app ?

## Lot 6 — Images / Suggestions

### 15. `api_suggestions.php` — aucun try/catch dans tout le fichier

- Laravel : `App\Http\Controllers\Legacy\SuggestionController::apiSuggestions`
- Route : `ANY /api_suggestions.php`

Meme cas que le point 12 (`api_lignes.php`) : aucun `try`/`catch` nulle
part dans le fichier source. **Confirme par test reel** : un `telephone` de
30 caracteres envoye a l'action `add` (colonne `telephone varchar(20)`)
plante le PHP source avec un fatal error brut identique dans sa forme a
celui du point 12. Meme deviation deja validee appliquee sans re-demander :
`catch (\Throwable $e)` global, JSON `{"success":false,"error":...}` en
HTTP 200 (cle `error`, coherente avec la convention locale du fichier).
Verifie qu'aucune ligne partielle n'est inseree.

### 16. `gestionImages.php` — absence d'isset(), mais consequence differente du point 10

- Laravel : `App\Http\Controllers\Legacy\ImageController::gestionImages`
- Route : `POST /gestionImages.php`

Meme absence d'`isset()` sur les champs POST que le point 10 (`titre`,
`description`, `statut`, `id` non verifies avant usage), mais la
consequence differe : la colonne `titre` est `NOT NULL` (contrairement a
`gare` dans `Gares`), donc un `titre` manquant provoque en realite une
**vraie exception PDO catchee normalement** (ce fichier a un try/catch,
contrairement aux points 10/12/15), pas un warning qui casse le JSON.
Confirme par test : PHP source renvoie un warning + JSON valide contenant
l'erreur de contrainte ; Laravel renvoie directement le JSON (sans le
warning), avec un message d'erreur equivalent mais plus verbeux (deja
documente comme acceptable pour toute la classe de differences
QueryException vs PDOException). Aucune deviation supplementaire requise
ici : le comportement observable (JSON d'erreur, HTTP 200) est deja
coherent des deux cotes.

### 17. `gestionImages.php` — CORRECTIF applique : middlewares globaux Laravel casseraient un champ vide explicite

- Fichier : `bootstrap/app.php` (config globale, pas juste ce controleur)

**Bug reel trouve et corrige**, pas une deviation assumee. `gestionImages.php`
est le seul endpoint du projet lu via `$request->input()` (multipart/
form-data, un upload de fichier ne peut pas passer par du JSON brut comme
tous les autres endpoints). Consequence non anticipee : les middlewares
globaux Laravel `ConvertEmptyStringsToNull` et `TrimStrings` s'appliquent
aux champs `$request->input()`, contrairement au JSON lu via
`getContent()` (utilise partout ailleurs dans le projet, jamais affecte).

**Confirme par test** : envoyer `titre=""` (chaine vide explicite, pas un
champ absent) sur `ajouter` :
- PHP source : insere `titre = ''` avec succes (`id=43` cree lors du test).
- Laravel (avant correctif) : `ConvertEmptyStringsToNull` transforme la
  chaine vide en `NULL` avant meme d'atteindre le controleur -> violation
  de la contrainte `NOT NULL` sur `titre` -> **echec la ou le PHP source
  reussit**. Vraie regression fonctionnelle, pas une simple difference
  cosmetique.

**Correctif** : dans `bootstrap/app.php`, `convertEmptyStringsToNull()` et
`trimStrings()` sont configures avec une exception (`skipWhen`) qui
desactive ces deux middlewares uniquement pour les requetes vers
`gestionImages.php` ; tous les autres endpoints du projet ne sont pas
concernes (ils ne passaient de toute facon jamais par ces middlewares,
grace a la lecture via `getContent()`). **Reteste apres correctif** :
`titre=""` insere desormais correctement une chaine vide des deux cotes.
Non-regression verifiee sur `listeAdmins.php` et `verifierTelephone.php`.

---

*Ce fichier sera complete au fil des lots suivants (Departs, Tickets,
etc.) a chaque fois qu'un comportement du PHP source meritera d'etre
signale avant d'etre eventuellement corrige.*
