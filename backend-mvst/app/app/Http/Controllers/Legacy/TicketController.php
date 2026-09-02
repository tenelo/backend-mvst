<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    /**
     * Plafond serveur sur "limit" pour recuperation_mes_tickets.php (audit
     * performance, tache 3). N'affecte aucun autre endpoint.
     */
    private const LIMIT_MAX_TICKETS = 100;

    /**
     * Equivalent de etatTicket.php.
     * POST JSON. documentId+place obligatoires.
     * LIMIT 1 sans ORDER BY dans le PHP source : reproduit a l'identique (le
     * choix de la ligne retournee n'est pas garanti si plusieurs correspondent,
     * mais ca ne devrait pas arriver en pratique).
     */
    public function etatTicket(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (! isset($data['documentId']) || ! isset($data['place'])) {
                return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 200);
            }

            $rows = DB::select(
                'SELECT "etatScanne" FROM "Tickets" WHERE "documentId" = :documentId AND place = :place LIMIT 1',
                ['documentId' => $data['documentId'], 'place' => $data['place']]
            );
            $ticket = $rows[0] ?? null;

            if ($ticket) {
                return response()->json(['success' => true, 'etatScanne' => $ticket->etatScanne], 200);
            }

            return response()->json(['success' => false, 'message' => 'Ticket non trouvé'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de mesTicketsScannes.php.
     * POST JSON. documentId+gare obligatoires.
     */
    public function mesTicketsScannes(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (! isset($data['documentId']) || ! isset($data['gare'])) {
                return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 200);
            }

            $tickets = DB::select(
                'SELECT * FROM "Tickets"
                 WHERE "documentId" = :documentId
                 AND depart = :gare
                 AND "etatScanne" = \'scanné\'
                 ORDER BY "scanneDate" DESC',
                ['documentId' => $data['documentId'], 'gare' => $data['gare']]
            );

            return response()->json(['success' => true, 'tickets' => $tickets], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de superadmin_mesTicketsScannes.php.
     * POST JSON. documentId obligatoire (pas de filtre gare -- version large).
     */
    public function superadminMesTicketsScannes(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (! isset($data['documentId'])) {
                return response()->json(['success' => false, 'message' => 'Paramètre manquant'], 200);
            }

            $tickets = DB::select(
                'SELECT * FROM "Tickets"
                 WHERE "documentId" = :documentId
                 AND "etatScanne" = \'scanné\'
                 ORDER BY "scanneDate" DESC',
                ['documentId' => $data['documentId']]
            );

            return response()->json(['success' => true, 'tickets' => $tickets], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de ticketsAscanner.php.
     * POST JSON. gare obligatoire (pas de documentId).
     */
    public function ticketsAscanner(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (! isset($data['gare'])) {
                return response()->json(['success' => false, 'message' => 'Paramètre manquant : gare'], 200);
            }

            $dateDuJour = date('Y-m-d');

            $tickets = DB::select(
                'SELECT * FROM "Tickets"
                 WHERE "datePourCalcule" >= :dateDuJour
                 AND depart = :gare
                 ORDER BY "datePourCalcule" ASC, heure ASC',
                ['dateDuJour' => $dateDuJour, 'gare' => $data['gare']]
            );

            return response()->json(['success' => true, 'tickets' => $tickets], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de superadmin_ticketsAscanner.php.
     * GET, aucun parametre (pas de filtre gare -- version large).
     */
    public function superadminTicketsAscanner(): JsonResponse
    {
        try {
            $dateDuJour = date('Y-m-d');

            $tickets = DB::select(
                'SELECT * FROM "Tickets"
                 WHERE "datePourCalcule" >= :dateDuJour
                 ORDER BY "datePourCalcule" ASC, heure ASC',
                ['dateDuJour' => $dateDuJour]
            );

            return response()->json(['success' => true, 'tickets' => $tickets], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de ticketsDuJour.php.
     * POST JSON. documentId+gare obligatoires.
     * Nom trompeur (deja signale phase 1) : ne filtre par aucune date.
     */
    public function ticketsDuJour(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (! isset($data['documentId']) || ! isset($data['gare'])) {
                return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 200);
            }

            $tickets = DB::select(
                'SELECT * FROM "Tickets"
                 WHERE "documentId" = :documentId
                 AND depart = :gare
                 ORDER BY "dateDeCreation" DESC',
                ['documentId' => $data['documentId'], 'gare' => $data['gare']]
            );

            return response()->json(['success' => true, 'tickets' => $tickets], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de ticketsDuJourScannes.php.
     * POST JSON. date+gare obligatoires.
     */
    public function ticketsDuJourScannes(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (! isset($data['date']) || ! isset($data['gare'])) {
                return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 200);
            }

            $tickets = DB::select(
                'SELECT * FROM "Tickets"
                 WHERE "scanneDate"::date = :date
                 AND "etatScanne" = \'scanné\'
                 AND depart = :gare
                 ORDER BY "scanneDate" DESC',
                ['date' => $data['date'], 'gare' => $data['gare']]
            );

            return response()->json(['success' => true, 'tickets' => $tickets], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de tableauAdmin.php.
     * POST JSON. annee+gare obligatoires.
     */
    public function tableauAdmin(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (! isset($data['annee']) || ! isset($data['gare'])) {
                return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 200);
            }

            $tickets = DB::select(
                'SELECT t.* FROM "Tickets" t
                 JOIN "Departs" d ON t."documentId" = d."documentId"
                 WHERE d.annee = :annee
                 AND t.depart = :gare
                 ORDER BY t."dateDeCreation" DESC',
                ['annee' => $data['annee'], 'gare' => $data['gare']]
            );

            return response()->json(['success' => true, 'tickets' => $tickets], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de placesAssises.php.
     * POST JSON. documentId obligatoire.
     * Pas de filtre sur "statut" (deja signale phase 1, point D5) : inclut tous
     * les tickets quel que soit leur statut, pas seulement "valide".
     */
    public function placesAssises(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (! isset($data['documentId'])) {
                return response()->json(['success' => false, 'message' => 'Paramètre manquant'], 200);
            }

            $documentId = $data['documentId'];

            $places = DB::select(
                'SELECT nom, telephone, depart, destination, place
                 FROM "Tickets"
                 WHERE "documentId" = :documentId AND statut = \'valide\'
                 ORDER BY place ASC',
                ['documentId' => $documentId]
            );

            $placesVendues = array_map(fn ($p) => (int) $p->place, $places);

            $departRow = DB::selectOne(
                'SELECT "placesChoisies" FROM "Departs" WHERE "documentId" = :documentId',
                ['documentId' => $documentId]
            );

            if ($departRow && $departRow->placesChoisies !== null && $departRow->placesChoisies !== '') {
                $decoded = json_decode($departRow->placesChoisies, true);
                $placesEnCours = is_array($decoded) ? $decoded : [];

                foreach ($placesEnCours as $place) {
                    $place = (int) $place;
                    if (! in_array($place, $placesVendues, true)) {
                        $places[] = (object) [
                            'nom' => null,
                            'telephone' => null,
                            'depart' => null,
                            'destination' => null,
                            'place' => $place,
                        ];
                    }
                }
            }

            return response()->json(['success' => true, 'places' => $places], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de ajouterTickets.php.
     * POST JSON. documentId+places(array) obligatoires (isset verifie) ; les autres
     * champs (idUtilisateur, nom, telephone, date, heure, depart, destination,
     * prixDuTicket, datePourCalcule) sont lus SANS isset() dans le PHP source (deja
     * signale en phase 1, B11) : lus ici avec ?? null (meme decision deja validee
     * qu'aux points 10/13/16), un champ absent devient NULL en base au lieu de
     * casser le JSON avec un warning.
     *
     * SECURITE (audit performance, tache 2, 26/08/2026) : "prixDuTicket" n'est
     * PLUS pris depuis le payload client. Il est recalcule cote serveur a partir
     * de "Lignes" (depart+destination+type), la table qui correspond structurellement
     * aux champs recus (depart/destination separes, comme "Lignes", contrairement a
     * "PrixDesTickets" qui n'a qu'une colonne "axe" texte). Choix documente, pas
     * tranche definitivement : la question de la table de reference (Lignes vs
     * PrixDesTickets, deja ouverte en phase 1 point D3, les deux ayant des prix
     * divergents en prod) reste a valider.
     *
     * Correction de l'enonce de la tache : contrairement a ce qui etait suppose,
     * LignePrixController ne trie/normalise PAS depart/destination pour dedupliquer
     * les deux sens d'un trajet -- verifie par lecture du code (aucun tri) et par
     * les donnees reelles ("Ferké→Abidjan" id=2 et "Abidjan→Ferké" id=5 sont deux
     * lignes distinctes, prix differents possibles). Le lookup ci-dessous filtre
     * donc par depart/destination EXACTS, dans le sens recu, sans normalisation.
     *
     * SECURITE (correctif double-vente, 28/08/2026, cf. A_REVOIR.md) : la
     * limitation "aucun verrou, aucune verification que les places sont
     * libres" (rapport phase 1, B2) n'est plus vraie. Chaque place demandee
     * doit desormais figurer dans Departs.placesChoisies pour ce documentId
     * (verifie sous verrou FOR UPDATE, coherent avec choisirPlace/
     * libererPlaces/purgerPlacesTemporaires cote mvst-socket), sinon rejet
     * explicite avant toute ecriture. "statut" n'est plus non plus lu depuis
     * le payload client (toujours 'valide' en dur) : un statut arbitraire
     * aurait pu casser le filtre WHERE statut='valide' utilise par la purge
     * et par l'index UNIQUE anti double-vente. La violation de cet index
     * (idx_tickets_doc_place_valide) est interceptee explicitement pour un
     * message clair, au lieu de l'erreur SQL brute par defaut du fichier.
     */
    public function ajouterTickets(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['documentId']) || ! isset($data['places'])) {
                return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 200);
            }

            $documentId = $data['documentId'];
            $idUtilisateur = $data['idUtilisateur'] ?? null;
            $nom = $data['nom'] ?? null;
            $telephone = $data['telephone'] ?? null;
            $date = $data['date'] ?? null;
            $heure = $data['heure'] ?? null;
            $depart = $data['depart'] ?? null;
            $destination = $data['destination'] ?? null;
            $places = $data['places'];
            // statut n'est plus lu du payload client : un Ticket cree ici est
            // toujours 'valide' (reservation = paiement, tant que le paiement
            // en ligne n'est pas integre). Empeche une valeur inattendue de
            // casser le filtre WHERE statut='valide' (purge + index UNIQUE).
            $statut = 'valide';
            $etatScanne = $data['etatScanne'] ?? 'nonScanné';
            $datePourCalcule = substr($data['datePourCalcule'] ?? '', 0, 10);
            $typeVoyage = $data['typeVoyage'] ?? 'standard';

            if (empty($places) || ! is_array($places)) {
                return response()->json(['success' => false, 'message' => 'Aucune place fournie'], 200);
            }

            $ligneReelle = DB::selectOne(
                'SELECT prix FROM "Lignes" WHERE depart = :depart AND destination = :destination AND type = :type AND prix > 0 LIMIT 1',
                ['depart' => $depart, 'destination' => $destination, 'type' => $typeVoyage]
            );

            if (! $ligneReelle) {
                return response()->json(['success' => false, 'message' => 'Aucun tarif ne correspond à cet axe/type de voyage'], 200);
            }

            $prixDuTicket = (int) $ligneReelle->prix;

            DB::beginTransaction();

            // Verification de possession : chaque place demandee doit etre
            // presente dans Departs.placesChoisies pour ce documentId.
            // Verrou tenu pour la duree de la transaction, coherent avec
            // choisirPlace/libererPlaces/purgerPlacesTemporaires.
            $departRow = DB::selectOne(
                'SELECT "placesChoisies" FROM "Departs" WHERE "documentId" = :documentId FOR UPDATE',
                ['documentId' => $documentId]
            );

            $placesDetenues = [];
            if ($departRow && $departRow->placesChoisies !== null && $departRow->placesChoisies !== '') {
                $decoded = json_decode($departRow->placesChoisies, true);
                $placesDetenues = is_array($decoded) ? $decoded : [];
            }

            $placesNonDetenues = array_filter($places, fn ($p) => ! in_array((int) $p, $placesDetenues, true));

            if (! empty($placesNonDetenues)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Place(s) non réservée(s) ou expirée(s) : '.implode(', ', $placesNonDetenues),
                ], 200);
            }

            foreach ($places as $place) {
                DB::insert(
                    'INSERT INTO "Tickets"
                        ("documentId", "idUtilisateur", nom, telephone, date, heure,
                         depart, destination, "prixDuTicket", place, "etatScanne",
                         statut, "datePourCalcule", "scanneDate", "dateDeCreation", "typeVoyage")
                    VALUES
                        (:documentId, :idUtilisateur, :nom, :telephone, :date, :heure,
                         :depart, :destination, :prixDuTicket, :place, :etatScanne,
                         :statut, :datePourCalcule, \'\', NOW(), :typeVoyage)',
                    [
                        'documentId' => $documentId,
                        'idUtilisateur' => $idUtilisateur,
                        'nom' => $nom,
                        'telephone' => $telephone,
                        'date' => $date,
                        'heure' => $heure,
                        'depart' => $depart,
                        'destination' => $destination,
                        'prixDuTicket' => $prixDuTicket,
                        'place' => (int) $place,
                        'etatScanne' => $etatScanne,
                        'statut' => $statut,
                        'datePourCalcule' => $datePourCalcule,
                        'typeVoyage' => $typeVoyage,
                    ]
                );
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Tickets ajoutés avec succès'], 200);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if (str_contains($e->getMessage(), 'idx_tickets_doc_place_valide')) {
                return response()->json(['success' => false, 'message' => 'Une ou plusieurs places viennent d\'être vendues à quelqu\'un d\'autre. Merci de réessayer.'], 200);
            }

            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de misAjourEtatScanne.php.
     * POST JSON. documentId+idUtilisateur+place obligatoires.
     *
     * CORRIGE (audit anti-double-scan, cf. A_REVOIR.md) : l'UPDATE etait
     * auparavant inconditionnel (jamais de verification de rowCount), un
     * ticket deja scanne pouvait etre re-scanne indefiniment, avec la meme
     * reponse success:true a chaque fois. Desormais l'UPDATE est
     * conditionnel (statut='valide' AND etatScanne='nonScanné') et le
     * rowCount affecte est utilise pour distinguer 3 cas : scan reussi,
     * deja scanne, ou introuvable (via un SELECT cible en cas de 0 ligne
     * modifiee).
     */
    public function misAjourEtatScanne(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['documentId']) || ! isset($data['idUtilisateur']) || ! isset($data['place'])) {
                return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 200);
            }

            $documentId = $data['documentId'];
            $idUtilisateur = $data['idUtilisateur'];
            $place = (int) $data['place'];
            $scanneDate = date('Y-m-d H:i:s');

            // UPDATE conditionnel : ne marque QUE si le ticket est valide et
            // pas deja scanne. rowCount = nb de lignes reellement modifiees.
            $affected = DB::update(
                'UPDATE "Tickets"
                 SET "etatScanne" = \'scanné\', "scanneDate" = :scanneDate
                 WHERE "documentId" = :documentId
                   AND "idUtilisateur" = :idUtilisateur
                   AND place = :place
                   AND statut = \'valide\'
                   AND "etatScanne" = \'nonScanné\'',
                [
                    'scanneDate' => $scanneDate,
                    'documentId' => $documentId,
                    'idUtilisateur' => $idUtilisateur,
                    'place' => $place,
                ]
            );

            if ($affected > 0) {
                // Scan reussi (premiere fois).
                return response()->json([
                    'success' => true,
                    'etat' => 'scanne',
                    'message' => 'Ticket validé',
                ], 200);
            }

            // 0 ligne modifiee : soit deja scanne, soit introuvable. On
            // distingue les deux par un SELECT cible.
            $rows = DB::select(
                'SELECT "etatScanne" FROM "Tickets"
                 WHERE "documentId" = :documentId
                   AND "idUtilisateur" = :idUtilisateur
                   AND place = :place
                   AND statut = \'valide\'
                 LIMIT 1',
                [
                    'documentId' => $documentId,
                    'idUtilisateur' => $idUtilisateur,
                    'place' => $place,
                ]
            );

            if (empty($rows)) {
                return response()->json([
                    'success' => false,
                    'etat' => 'introuvable',
                    'message' => 'Ticket introuvable',
                ], 200);
            }

            // La ligne existe mais n'a pas ete modifiee -> deja scannee.
            return response()->json([
                'success' => false,
                'etat' => 'deja_scanne',
                'message' => 'Ticket déjà scanné',
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de suppressionTickets.php.
     * POST JSON, deux modes : "dates" (array, liste des tickets) ou
     * action="supprimer"+"id" (DELETE). Le PHP source ne rejette pas les methodes
     * non-POST au niveau HTTP, il retombe juste sur "Action non reconnue" : route
     * enregistree en ANY pour rester fidele.
     *
     * ATTENTION (deja signalee phase 1, B5) : la clause IN(...) est construite avec
     * un nombre de placeholders egal a count($dates), sans plafond. Reproduit a
     * l'identique. Le DELETE ne resynchronise jamais Departs.placesChoisies /
     * PlacesTemporaires (deja signale phase 1, B2) : aucune correction ajoutee.
     */
    public function suppressionTickets(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $method = $request->method();

            if ($method === 'POST' && isset($data['dates'])) {
                $dates = $data['dates'];
                $placeholders = implode(',', array_fill(0, count($dates), '?'));

                $tickets = DB::select(
                    "SELECT * FROM \"Tickets\" WHERE date IN ($placeholders) ORDER BY \"dateDeCreation\" DESC",
                    $dates
                );

                return response()->json(['success' => true, 'tickets' => $tickets], 200);
            }

            if ($method === 'POST' && isset($data['action']) && $data['action'] === 'supprimer') {
                $id = (int) ($data['id'] ?? 0);

                DB::delete('DELETE FROM "Tickets" WHERE id = :id', ['id' => $id]);

                return response()->json(['success' => true, 'message' => 'Ticket supprimé'], 200);
            }

            return response()->json(['success' => false, 'message' => 'Action non reconnue'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de recuperation_mes_tickets.php.
     * POST JSON. idUtilisateur obligatoire ; offset/limit optionnels (defaut 0/30).
     *
     * PLAFOND SERVEUR (audit performance, tache 3, 26/08/2026) : "limit" reste
     * a 30 par defaut (comportement inchange), mais ne peut plus depasser
     * self::LIMIT_MAX_TICKETS quelle que soit la valeur envoyee par le client
     * (deja signale en phase 1 comme non borne). "offset" n'est pas touche.
     */
    public function recuperationMesTickets(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['idUtilisateur'])) {
                return response()->json(['success' => false, 'message' => 'Paramètre manquant : idUtilisateur'], 200);
            }

            $idUtilisateur = $data['idUtilisateur'];
            $offset = isset($data['offset']) ? (int) $data['offset'] : 0;
            $limit = isset($data['limit']) ? (int) $data['limit'] : 30;
            $limit = min($limit, self::LIMIT_MAX_TICKETS);

            $total = (int) DB::selectOne(
                'SELECT COUNT(*) as total FROM "Tickets" WHERE "idUtilisateur" = :idUtilisateur',
                ['idUtilisateur' => $idUtilisateur]
            )->total;

            $tickets = DB::select(
                'SELECT
                    "documentId",
                    "idUtilisateur",
                    nom,
                    telephone,
                    date,
                    heure,
                    depart,
                    destination,
                    place,
                    "etatScanne",
                    "prixDuTicket",
                    statut,
                    "datePourCalcule"::text,
                    "typeVoyage"
                FROM "Tickets"
                WHERE "idUtilisateur" = :idUtilisateur
                ORDER BY "dateDeCreation" DESC
                LIMIT :limit OFFSET :offset',
                ['idUtilisateur' => $idUtilisateur, 'limit' => $limit, 'offset' => $offset]
            );

            return response()->json(['success' => true, 'tickets' => $tickets, 'total' => $total], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de recuperation_mes_tickets_tableau.php.
     * Variante de recuperationMesTickets avec limit par defaut 100 et colonnes
     * supplementaires (scanneDate, dateDeCreation). Lecture seule.
     */
    public function recuperationMesTicketsTableau(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['idUtilisateur'])) {
                return response()->json(['success' => false, 'message' => 'Paramètre manquant : idUtilisateur'], 200);
            }

            $idUtilisateur = $data['idUtilisateur'];
            $offset = isset($data['offset']) ? (int) $data['offset'] : 0;
            $limit = isset($data['limit']) ? (int) $data['limit'] : 100;

            $total = (int) DB::selectOne(
                'SELECT COUNT(*) as total FROM "Tickets" WHERE "idUtilisateur" = :idUtilisateur',
                ['idUtilisateur' => $idUtilisateur]
            )->total;

            $tickets = DB::select(
                'SELECT
                    t."documentId",
                    t."idUtilisateur",
                    t.nom,
                    t.telephone,
                    t.date,
                    t.heure,
                    t.depart,
                    t.destination,
                    t.place,
                    t."etatScanne",
                    t."prixDuTicket",
                    t.statut,
                    t."datePourCalcule"::text,
                    t."scanneDate",
                    t."dateDeCreation"::text,
                    t."typeVoyage"
                FROM "Tickets" t
                WHERE t."idUtilisateur" = :idUtilisateur
                ORDER BY t."dateDeCreation" DESC NULLS LAST
                LIMIT :limit OFFSET :offset',
                ['idUtilisateur' => $idUtilisateur, 'limit' => $limit, 'offset' => $offset]
            );

            return response()->json(['success' => true, 'tickets' => $tickets, 'total' => $total], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de graphiques.php.
     * POST JSON. type=jour/moisAnnee/annee + gare obligatoires. Lecture seule.
     */
    public function graphiques(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $type = $data['type'] ?? '';
            $gare = $data['gare'] ?? '';

            if ($type === 'jour') {
                $sql = 'SELECT t.* FROM "Tickets" t
                        JOIN "Departs" d ON t."documentId" = d."documentId"
                        WHERE d."dateDeDepart" = :valeur
                        AND t.depart = :gare';
                $valeur = $data['date'] ?? null;
            } elseif ($type === 'moisAnnee') {
                $sql = 'SELECT t.* FROM "Tickets" t
                        JOIN "Departs" d ON t."documentId" = d."documentId"
                        WHERE d."moisAnnee" = :valeur
                        AND t.depart = :gare';
                $valeur = $data['moisAnnee'] ?? null;
            } elseif ($type === 'annee') {
                $sql = 'SELECT t.* FROM "Tickets" t
                        JOIN "Departs" d ON t."documentId" = d."documentId"
                        WHERE d.annee = :valeur
                        AND t.depart = :gare';
                $valeur = $data['annee'] ?? null;
            } else {
                return response()->json(['success' => false, 'message' => 'Type non reconnu'], 200);
            }

            $tickets = DB::select($sql, ['valeur' => $valeur, 'gare' => $gare]);

            return response()->json(['success' => true, 'tickets' => $tickets], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * synthese_gare.php (nouvel endpoint, pas une migration PHP). POST JSON.
     * gare+date ("YYYY-MM-DD") obligatoires. Lecture seule.
     *
     * Date de reference = date de VOYAGE (Tickets.datePourCalcule, type date
     * natif), pas Departs.dateDeDepart (varchar libre en francais,
     * ex. "samedi_9_mai_2026" -- non exploitable pour un filtre fiable,
     * verifie en amont). "departsDuJour" ne peut donc lister que les
     * departs ayant deja au moins un Ticket valide pour cette date (jointure
     * Departs/Tickets sur documentId) : un depart programme sans aucune
     * vente ce jour-la n'apparaitra pas dans la liste. "tauxEmbarquement"
     * est un pourcentage (0-100), pas un ratio (0-1).
     */
    public function syntheseGare(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['gare']) || ! isset($data['date'])) {
                return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 200);
            }

            $gare = $data['gare'];
            $date = (new \DateTime($data['date']))->format('Y-m-d');
            $debutSemaine = (new \DateTime($data['date']))->modify('-6 days')->format('Y-m-d');
            $debutMois = (new \DateTime($data['date']))->modify('-29 days')->format('Y-m-d');

            $bandeauRow = DB::selectOne(
                'SELECT
                    COUNT(*) AS vendus,
                    COALESCE(SUM("prixDuTicket"), 0) AS recettes,
                    COUNT(*) FILTER (WHERE "etatScanne" = \'scanné\') AS scannes
                 FROM "Tickets"
                 WHERE statut = \'valide\'
                   AND depart = :gare
                   AND "datePourCalcule" = :date',
                ['gare' => $gare, 'date' => $date]
            );

            $vendus = (int) $bandeauRow->vendus;
            $scannes = (int) $bandeauRow->scannes;
            $tauxEmbarquement = $vendus > 0 ? round($scannes / $vendus * 100, 1) : 0;

            $acheteursJour = DB::selectOne(
                'SELECT COUNT(DISTINCT "idUtilisateur") AS n FROM "Tickets"
                 WHERE statut = \'valide\' AND depart = :gare AND "datePourCalcule" = :date',
                ['gare' => $gare, 'date' => $date]
            );
            $acheteursSemaine = DB::selectOne(
                'SELECT COUNT(DISTINCT "idUtilisateur") AS n FROM "Tickets"
                 WHERE statut = \'valide\' AND depart = :gare AND "datePourCalcule" BETWEEN :debut AND :fin',
                ['gare' => $gare, 'debut' => $debutSemaine, 'fin' => $date]
            );
            $acheteursMois = DB::selectOne(
                'SELECT COUNT(DISTINCT "idUtilisateur") AS n FROM "Tickets"
                 WHERE statut = \'valide\' AND depart = :gare AND "datePourCalcule" BETWEEN :debut AND :fin',
                ['gare' => $gare, 'debut' => $debutMois, 'fin' => $date]
            );

            $departsRows = DB::select(
                'SELECT d."documentId", d."heureDeDepart", d.destination, d."typeVoyage",
                        COUNT(*) AS vendus,
                        COUNT(*) FILTER (WHERE t."etatScanne" = \'scanné\') AS embarques
                 FROM "Departs" d
                 JOIN "Tickets" t ON t."documentId" = d."documentId"
                 WHERE d.depart = :gare
                   AND t.statut = \'valide\'
                   AND t."datePourCalcule" = :date
                 GROUP BY d."documentId", d."heureDeDepart", d.destination, d."typeVoyage"
                 ORDER BY d."heureDeDepart" ASC',
                ['gare' => $gare, 'date' => $date]
            );

            $prochainsDeparts = array_map(fn ($d) => [
                'heureDeDepart' => $d->heureDeDepart,
                'destination' => $d->destination,
                'typeVoyage' => $d->typeVoyage,
                'documentId' => $d->documentId,
                'vendus' => (int) $d->vendus,
                'embarques' => (int) $d->embarques,
            ], $departsRows);

            return response()->json([
                'success' => true,
                'bandeau' => [
                    'vendus' => $vendus,
                    'recettes' => (int) $bandeauRow->recettes,
                    'scannes' => $scannes,
                    'tauxEmbarquement' => $tauxEmbarquement,
                ],
                'acheteurs' => [
                    'jour' => (int) $acheteursJour->n,
                    'semaine' => (int) $acheteursSemaine->n,
                    'mois' => (int) $acheteursMois->n,
                ],
                'departsDuJour' => $prochainsDeparts,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }
}
