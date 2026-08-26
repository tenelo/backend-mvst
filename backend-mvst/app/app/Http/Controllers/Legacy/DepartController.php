<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartController extends Controller
{
    /**
     * Equivalent de departsParGare.php.
     * POST JSON. date+gare obligatoires. Lecture seule.
     */
    public function departsParGare(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['date']) || ! isset($data['gare'])) {
                return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 200);
            }

            $departs = DB::select(
                'SELECT
                    "heureDeDepart",
                    "documentId",
                    "dateDeDepart",
                    depart,
                    destination,
                    "typeVoyage",
                    (SELECT COUNT(*) FROM "Tickets" t
                     WHERE t."documentId" = d."documentId") AS "nombreDePlacesChoisies"
                FROM "Departs" d
                WHERE "dateDeDepart" = :date
                AND depart = :gare
                GROUP BY "documentId", "heureDeDepart", "dateDeDepart", depart, destination, "typeVoyage"
                ORDER BY "heureDeDepart" ASC',
                ['date' => $data['date'], 'gare' => $data['gare']]
            );

            return response()->json(['success' => true, 'departs' => $departs], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de process_places_temporaires.php.
     * POST, aucun parametre attendu. Purge les reservations temporaires expirees
     * (table PlacesTemporaires) : pour chaque entree, si aucun ticket "valide"
     * n'occupe reellement cette place, elle est retiree du JSON
     * Departs.placesChoisies (resynchronisation) ; dans tous les cas la ligne
     * PlacesTemporaires est supprimee.
     *
     * REGLE ABSOLUE (rappelee dans le code, pas seulement dans A_REVOIR.md) :
     * ceci reproduit EXACTEMENT la logique du PHP source, y compris le fait
     * qu'elle duplique une partie de ce que fait deja mvst-socket/handlers/places.js.
     * Aucun verrou ajoute, aucune tentative de "reparer" la concurrence. mvst-socket
     * n'est ni lu ni modifie par ce code.
     */
    public function processPlacesTemporaires(): JsonResponse
    {
        try {
            $placesTemp = DB::select('SELECT "documentId", places FROM "PlacesTemporaires"');

            if (empty($placesTemp)) {
                return response()->json(['success' => true, 'message' => 'Aucune place temporaire'], 200);
            }

            DB::beginTransaction();

            foreach ($placesTemp as $temp) {
                $documentId = $temp->documentId;
                $place = (int) $temp->places;

                $result = DB::selectOne(
                    'SELECT COUNT(*) as total FROM "Tickets" WHERE "documentId" = :documentId AND place = :place AND statut = \'valide\'',
                    ['documentId' => $documentId, 'place' => $place]
                );

                if ((int) $result->total === 0) {
                    $rows = DB::select('SELECT "placesChoisies" FROM "Departs" WHERE "documentId" = :documentId', ['documentId' => $documentId]);
                    $row = $rows[0] ?? null;

                    if ($row && $row->placesChoisies !== null && $row->placesChoisies !== '') {
                        $decoded = json_decode($row->placesChoisies, true);
                        $placesActuelles = is_array($decoded) ? $decoded : [];
                        $placesRestantes = array_values(array_diff($placesActuelles, [$place]));
                        $nouvellesPlaces = json_encode($placesRestantes);

                        DB::update(
                            'UPDATE "Departs" SET "placesChoisies" = :places WHERE "documentId" = :documentId',
                            ['places' => $nouvellesPlaces, 'documentId' => $documentId]
                        );
                    }
                }

                DB::delete(
                    'DELETE FROM "PlacesTemporaires" WHERE "documentId" = :documentId AND places = :place',
                    ['documentId' => $documentId, 'place' => $place]
                );
            }

            DB::commit();

            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }
}
