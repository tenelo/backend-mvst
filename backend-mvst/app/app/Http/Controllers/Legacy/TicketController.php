<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
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
}
