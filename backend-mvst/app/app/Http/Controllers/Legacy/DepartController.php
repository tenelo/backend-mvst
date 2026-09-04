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

            // Contrat HTTP etendu le 28/08/2026 (cf. A_REVOIR.md) : "delaiMinutes"
            // et "nettoyees" sont desormais exposes pour l'observabilite de la
            // purge (delai configurable en base). "success"/"message" restent le
            // socle inchange consomme par l'app cliente.
            $reponseHttp = ['success' => $resultat['success']];
            if (isset($resultat['message'])) {
                $reponseHttp['message'] = $resultat['message'];
            }
            if (isset($resultat['delaiMinutes'])) {
                $reponseHttp['delaiMinutes'] = $resultat['delaiMinutes'];
            }
            if (isset($resultat['nettoyees'])) {
                $reponseHttp['nettoyees'] = $resultat['nettoyees'];
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
     * en plus de l'endpoint HTTP ci-dessus.
     *
     * Corrige le 28/08/2026 (cf. A_REVOIR.md) : filtre desormais sur
     * "dateDeCreation" (age minimum configurable avant purge, au lieu
     * d'aucun filtre) et verrouille la ligne "Departs" concernee (FOR
     * UPDATE) le temps de chaque siege traite individuellement (au lieu
     * d'une seule grosse transaction sans verrou sur tout le lot). Une
     * ligne en echec (erreur SQL isolee) est loguee et n'interrompt plus
     * le traitement des autres lignes du lot.
     *
     * Delai de purge lu depuis la table "Parametres"
     * (cle "purge_delai_minutes"), avec 5 minutes en repli si la ligne est
     * absente.
     *
     * @return array{success: bool, message?: string, nettoyees?: int, delaiMinutes?: int}
     */
    public static function purgerPlacesTemporaires(): array
    {
        $delaiRow = DB::selectOne('SELECT valeur FROM "Parametres" WHERE cle = :cle', ['cle' => 'purge_delai_minutes']);
        $delaiMinutes = $delaiRow ? (int) $delaiRow->valeur : 5;

        $placesTemp = DB::select(
            'SELECT "documentId", places FROM "PlacesTemporaires" WHERE "dateDeCreation" <= NOW() - make_interval(mins => :delai)',
            ['delai' => $delaiMinutes]
        );

        if (empty($placesTemp)) {
            return ['success' => true, 'message' => 'Aucune place temporaire a purger', 'delaiMinutes' => $delaiMinutes];
        }

        $nettoyees = 0;

        foreach ($placesTemp as $temp) {
            $documentId = $temp->documentId;
            $place = (int) $temp->places;

            DB::beginTransaction();
            try {
                $result = DB::selectOne(
                    'SELECT COUNT(*) as total FROM "Tickets" WHERE "documentId" = :documentId AND place = :place AND statut = \'valide\'',
                    ['documentId' => $documentId, 'place' => $place]
                );

                if ((int) $result->total === 0) {
                    $rows = DB::select(
                        'SELECT "placesChoisies" FROM "Departs" WHERE "documentId" = :documentId FOR UPDATE',
                        ['documentId' => $documentId]
                    );
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

                DB::commit();
                $nettoyees++;
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::error('Erreur purge place temporaire', [
                    'documentId' => $documentId,
                    'place' => $place,
                    'erreur' => $e->getMessage(),
                ]);
            }
        }

        return ['success' => true, 'nettoyees' => $nettoyees, 'delaiMinutes' => $delaiMinutes];
    }

    /**
     * Equivalent de process_places_temporaires.php, meme patron applique aux
     * Departs vides : POST, aucun parametre attendu.
     */
    public function processDepartsVides(): JsonResponse
    {
        try {
            $resultat = self::purgerDepartsVides();

            return response()->json([
                'success' => $resultat['success'],
                'supprimes' => $resultat['supprimes'],
            ], 200);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Supprime les Departs "vides" (placesChoisies NULL ou '[]') qui n'ont
     * AUCUN ticket rattache, quel que soit son statut -- jamais seulement
     * statut='valide' : la contrainte FK Tickets_documentId_fkey est en
     * NO ACTION, mais le critere metier lui-meme doit deja exclure tout
     * Departs ayant ne serait-ce qu'un ticket historique/invalide (verifie
     * en amont : aucun des candidats actuels n'a de ticket d'aucun statut,
     * mais rien ne garantit que ce sera toujours le cas).
     *
     * Meme patron que purgerPlacesTemporaires : un Departs a la fois, sa
     * propre transaction, verrou FOR UPDATE, erreur isolee ne bloque pas le
     * reste du lot. Ajout par rapport au patron places : re-verification du
     * critere SOUS le verrou juste avant le DELETE (la ligne a pu changer
     * entre la selection initiale et ce point -- nouvelle place choisie ou
     * ticket cree entre-temps -- irreversible, donc pas de confiance dans
     * une lecture perimee ici).
     *
     * @return array{success: bool, supprimes: int}
     */
    public static function purgerDepartsVides(): array
    {
        $departsVides = DB::select(
            'SELECT "documentId" FROM "Departs" d
             WHERE (d."placesChoisies" IS NULL OR d."placesChoisies" = \'[]\')
               AND NOT EXISTS (SELECT 1 FROM "Tickets" t WHERE t."documentId" = d."documentId")'
        );

        if (empty($departsVides)) {
            return ['success' => true, 'supprimes' => 0];
        }

        $supprimes = 0;

        foreach ($departsVides as $depart) {
            $documentId = $depart->documentId;

            DB::beginTransaction();
            try {
                $row = DB::selectOne(
                    'SELECT "placesChoisies" FROM "Departs" WHERE "documentId" = :documentId FOR UPDATE',
                    ['documentId' => $documentId]
                );

                if (! $row || ! ($row->placesChoisies === null || $row->placesChoisies === '[]')) {
                    DB::rollBack();

                    continue;
                }

                $ticketExiste = DB::selectOne(
                    'SELECT COUNT(*) as total FROM "Tickets" WHERE "documentId" = :documentId',
                    ['documentId' => $documentId]
                );

                if ((int) $ticketExiste->total > 0) {
                    DB::rollBack();

                    continue;
                }

                DB::delete('DELETE FROM "Departs" WHERE "documentId" = :documentId', ['documentId' => $documentId]);

                DB::commit();
                $supprimes++;
            } catch (\Throwable $e) {
                DB::rollBack();
                \Log::error('Erreur purge depart vide', [
                    'documentId' => $documentId,
                    'erreur' => $e->getMessage(),
                ]);
            }
        }

        return ['success' => true, 'supprimes' => $supprimes];
    }
}
