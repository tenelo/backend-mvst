<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ImageController extends Controller
{
    /**
     * Equivalent de getImages.php.
     * GET, sans parametre. Liste les images actives (statut = 'actif').
     */
    public function getImages(): JsonResponse
    {
        try {
            $images = DB::select(
                "SELECT id, titre, description, lien_image, statut FROM \"Images\" WHERE statut = 'actif' ORDER BY \"dateDeCreation\" DESC"
            );

            return response()->json(['success' => true, 'images' => $images], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }
}
