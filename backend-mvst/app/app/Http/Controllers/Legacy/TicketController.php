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

            $places = DB::select(
                'SELECT nom, telephone, depart, destination, place
                 FROM "Tickets"
                 WHERE "documentId" = :documentId
                 ORDER BY place ASC',
                ['documentId' => $data['documentId']]
            );

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
     * Cree les tickets definitifs en boucle sur places[], dans une transaction.
     * AUCUN verrou, AUCUNE verification que les places sont libres : reproduit a
     * l'identique la limitation deja documentee (A_REVOIR.md / rapport phase 1, B2).
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
            $statut = $data['statut'] ?? 'valide';
            $etatScanne = $data['etatScanne'] ?? 'nonScanné';
            $datePourCalcule = substr($data['datePourCalcule'] ?? '', 0, 10);
            $typeVoyage = $data['typeVoyage'] ?? 'standard';

            if (empty($places) || ! is_array($places)) {
                return response()->json(['success' => false, 'message' => 'Aucune place fournie'], 200);
            }

            // Revalidation serveur du prix : la valeur du client (prixDuTicket) est
            // entierement ignoree, jamais lue. Rejet explicite si aucun tarif actif
            // (prix > 0) ne correspond a l'axe/type exact demande.
            $ligneReelle = DB::selectOne(
                'SELECT prix FROM "Lignes" WHERE depart = :depart AND destination = :destination AND type = :type AND prix > 0 LIMIT 1',
                ['depart' => $depart, 'destination' => $destination, 'type' => $typeVoyage]
            );

            if (! $ligneReelle) {
                return response()->json(['success' => false, 'message' => 'Aucun tarif ne correspond à cet axe/type de voyage'], 200);
            }

            $prixDuTicket = (int) $ligneReelle->prix;

            DB::beginTransaction();

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

            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de misAjourEtatScanne.php.
     * POST JSON. documentId+idUtilisateur+place obligatoires.
     *
     * ATTENTION (a noter dans A_REVOIR.md) : rowCount() jamais verifie. Reponse
     * success=true meme si aucune ligne ne correspondait (documentId/idUtilisateur/
     * place incoherents) -- c'est l'action de scan elle-meme, la plus sensible du
     * projet cote ecriture.
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

            DB::update(
                'UPDATE "Tickets"
                 SET "etatScanne" = \'scanné\', "scanneDate" = :scanneDate
                 WHERE "documentId" = :documentId
                 AND "idUtilisateur" = :idUtilisateur
                 AND place = :place',
                ['scanneDate' => $scanneDate, 'documentId' => $documentId, 'idUtilisateur' => $idUtilisateur, 'place' => $place]
            );

            return response()->json(['success' => true, 'message' => 'Ticket mis à jour'], 200);
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
}
