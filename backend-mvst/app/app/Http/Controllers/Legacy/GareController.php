<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GareController extends Controller
{
    /**
     * Equivalent de gares.php.
     * GET -> liste. POST action=ajouter/modifier/supprimer.
     *
     * ATTENTION (a noter dans A_REVOIR.md) : contrairement a la quasi-totalite des
     * endpoints des lots precedents, aucun isset() n'est fait sur "gare"/"id" avant
     * utilisation. Un champ absent devient simplement NULL (colonnes nullables) ou
     * (int)null = 0 pour id, sans message d'erreur "parametre manquant". Reproduit
     * a l'identique.
     */
    public function gares(Request $request): JsonResponse
    {
        try {
            if ($request->method() === 'GET') {
                $gares = DB::select('SELECT * FROM "Gares" ORDER BY id ASC');

                return response()->json(['success' => true, 'gares' => $gares], 200);
            }

            $data = json_decode($request->getContent(), true);
            $action = $data['action'] ?? '';

            if ($action === 'ajouter') {
                DB::insert('INSERT INTO "Gares" (gare) VALUES (:gare)', ['gare' => $data['gare'] ?? null]);

                return response()->json(['success' => true, 'message' => 'Gare ajoutée'], 200);
            }

            if ($action === 'modifier') {
                DB::update('UPDATE "Gares" SET gare = :gare WHERE id = :id', ['gare' => $data['gare'] ?? null, 'id' => (int) ($data['id'] ?? 0)]);

                return response()->json(['success' => true, 'message' => 'Gare modifiée'], 200);
            }

            if ($action === 'supprimer') {
                DB::delete('DELETE FROM "Gares" WHERE id = :id', ['id' => (int) ($data['id'] ?? 0)]);

                return response()->json(['success' => true, 'message' => 'Gare supprimée'], 200);
            }

            return response()->json(['success' => false, 'message' => 'Action non reconnue'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de infosGares.php.
     * GET -> liste. POST action=ajouter/modifier/supprimer.
     * Meme absence d'isset() que gares.php : voir A_REVOIR.md.
     */
    public function infosGares(Request $request): JsonResponse
    {
        try {
            if ($request->method() === 'GET') {
                $infos = DB::select('SELECT * FROM "InfosGares" ORDER BY id ASC');

                return response()->json(['success' => true, 'infos' => $infos], 200);
            }

            $data = json_decode($request->getContent(), true);
            $action = $data['action'] ?? '';

            if ($action === 'ajouter') {
                DB::insert(
                    'INSERT INTO "InfosGares" (ville, description, telephone) VALUES (:ville, :description, :telephone)',
                    [
                        'ville' => $data['ville'] ?? null,
                        'description' => $data['description'] ?? null,
                        'telephone' => $data['telephone'] ?? null,
                    ]
                );

                return response()->json(['success' => true, 'message' => 'Informations ajoutées'], 200);
            }

            if ($action === 'modifier') {
                DB::update(
                    'UPDATE "InfosGares" SET ville = :ville, description = :description, telephone = :telephone WHERE id = :id',
                    [
                        'ville' => $data['ville'] ?? null,
                        'description' => $data['description'] ?? null,
                        'telephone' => $data['telephone'] ?? null,
                        'id' => (int) ($data['id'] ?? 0),
                    ]
                );

                return response()->json(['success' => true, 'message' => 'Informations modifiées'], 200);
            }

            if ($action === 'supprimer') {
                DB::delete('DELETE FROM "InfosGares" WHERE id = :id', ['id' => (int) ($data['id'] ?? 0)]);

                return response()->json(['success' => true, 'message' => 'Information supprimée'], 200);
            }

            return response()->json(['success' => false, 'message' => 'Action non reconnue'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de tarifsAxes_et_infos_gare.php.
     * GET, query string "type" (tarifs|gares).
     *
     * ATTENTION (deja signale en phase 1, reproduit a l'identique) : la reponse pour
     * type=gares utilise quand meme la cle "tarifs" pour porter les infos de gare
     * (ville/description/telephone), pas de cle "infos" ou "gares". Ne pas renommer.
     *
     * CACHE HTTP (audit performance, tache 4, 26/08/2026) : Cache-Control ajoute
     * sur les deux reponses de succes (tarifs et gares), jamais sur une erreur.
     */
    public function tarifsAxesEtInfosGare(Request $request): JsonResponse
    {
        try {
            $type = $request->query('type', '');

            if ($type === 'tarifs') {
                $tarifs = DB::select('SELECT axe, prix FROM "PrixDesTickets" ORDER BY axe ASC');

                return response()->json(['success' => true, 'tarifs' => $tarifs], 200)
                    ->header('Cache-Control', 'public, max-age=600');
            }

            if ($type === 'gares') {
                $gares = DB::select('SELECT ville, description, telephone FROM "InfosGares" ORDER BY ville ASC');

                return response()->json(['success' => true, 'tarifs' => $gares], 200)
                    ->header('Cache-Control', 'public, max-age=600');
            }

            return response()->json(['success' => false, 'message' => 'Paramètre type manquant'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }
}
