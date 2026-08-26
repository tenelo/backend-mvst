<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    /**
     * Equivalent de listeAdmins.php.
     * GET, sans parametre. Liste tous les admins.
     */
    public function liste(): JsonResponse
    {
        try {
            $admins = DB::select(
                'SELECT id, telephone, role, gare, nom, prenoms, mail, "dateDeCreation" FROM "Admins" ORDER BY "dateDeCreation" DESC'
            );

            return response()->json(['success' => true, 'admins' => $admins], 200);
        } catch (\Exception $e) {
            // HTTP 200 volontaire meme en erreur : reproduit le comportement du PHP d'origine.
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }
}
