<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiversController extends Controller
{
    /**
     * Equivalent de recuperationHeure.php.
     * POST JSON. Parametre optionnel "type" (defaut "standard").
     * Le triple catch (PDOException/Exception/Error) est repris a l'identique
     * du fichier source, y compris les prefixes de message differents selon
     * le type d'exception.
     */
    public function recuperationHeure(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $type = $data['type'] ?? 'standard';

            $heures = DB::select(
                'SELECT heure FROM "HeuresDeDeparts" WHERE type = :type ORDER BY heure ASC',
                ['type' => $type]
            );

            return response()->json(['success' => true, 'heures' => $heures], 200);
        } catch (\PDOException $e) {
            return response()->json(['success' => false, 'message' => 'PDO: '.$e->getMessage()], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur: '.$e->getMessage()], 200);
        } catch (\Error $e) {
            return response()->json(['success' => false, 'message' => 'Fatal: '.$e->getMessage()], 200);
        }
    }
}
