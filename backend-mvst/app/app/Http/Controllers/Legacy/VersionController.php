<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VersionController extends Controller
{
    /**
     * POST /versions.php  { "app": "client" | "admin" }
     * Renvoie la version minimale requise (blocage dur) et la version
     * recommandee (suggestion douce) pour l'app demandee. Public.
     */
    public function versions(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true) ?? [];
            $app = $data['app'] ?? 'client';
            if (! in_array($app, ['client', 'admin'], true)) {
                $app = 'client';
            }

            $cleMin = 'version_min_'.$app;
            $cleRec = 'version_rec_'.$app;

            $rows = DB::select(
                'SELECT cle, valeur FROM "Parametres" WHERE cle IN (:min, :rec)',
                ['min' => $cleMin, 'rec' => $cleRec]
            );

            $map = [];
            foreach ($rows as $r) {
                $map[$r->cle] = $r->valeur;
            }

            return response()->json([
                'success' => true,
                'version_minimale' => $map[$cleMin] ?? '1.0.0',
                'version_recommandee' => $map[$cleRec] ?? '1.0.0',
            ], 200);
        } catch (\Exception $e) {
            // En cas d'erreur, ne jamais bloquer l'app : versions permissives.
            return response()->json([
                'success' => true,
                'version_minimale' => '1.0.0',
                'version_recommandee' => '1.0.0',
            ], 200);
        }
    }
}
