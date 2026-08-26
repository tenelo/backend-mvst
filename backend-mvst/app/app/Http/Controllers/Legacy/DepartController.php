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
            $resultat = self::purgerPlacesTemporaires();

            // Contrat HTTP inchange (deja teste/valide/bascule en prod) : la cle
            // "nettoyees" ajoutee pour la commande planifiee (tache 1) n'est PAS
            // exposee ici. Reponse strictement identique a avant l'extraction :
            // {"success":true} ou {"success":true,"message":"Aucune place temporaire"}.
            $reponseHttp = ['success' => $resultat['success']];
            if (isset($resultat['message'])) {
                $reponseHttp['message'] = $resultat['message'];
            }

            return response()->json($reponseHttp, 200);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Logique de purge extraite pour etre reutilisable depuis la commande
     * Artisan planifiee (app/Console/Commands/PurgerPlacesTemporaires.php),
     * en plus de l'endpoint HTTP ci-dessus. Comportement identique au PHP
     * source, non modifie par cette extraction : meme requetes, meme absence
     * de verrou, meme non-filtrage par age des lignes PlacesTemporaires (voir
     * A_REVOIR.md). Seule la reutilisation change, pas la logique.
     *
     * @return array{success: bool, message?: string, nettoyees?: int}
     */
    public static function purgerPlacesTemporaires(): array
    {
        $placesTemp = DB::select('SELECT "documentId", places FROM "PlacesTemporaires"');

        if (empty($placesTemp)) {
            return ['success' => true, 'message' => 'Aucune place temporaire'];
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

        return ['success' => true, 'nettoyees' => count($placesTemp)];
    }
}
