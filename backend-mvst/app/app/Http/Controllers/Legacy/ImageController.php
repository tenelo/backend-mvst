<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * Equivalent de gestionImages.php.
     * GET -> liste complete. POST multipart/form-data (PAS de JSON, seul fichier du
     * projet dans ce cas) action=ajouter/modifier/supprimer.
     *
     * ATTENTION (a noter dans A_REVOIR.md, meme decision deja validee que le point 10) :
     * aucun isset() sur titre/description/statut/id dans le PHP source pour
     * "modifier" -- lu avec ?? null ici pour eviter le warning PHP qui casse le JSON.
     *
     * Aucune validation MIME/extension sur le fichier uploade (deja signale en
     * phase 1, B4) : reproduit a l'identique, l'extension vient telle quelle du nom
     * de fichier fourni par le client.
     */
    public function gestionImages(Request $request): JsonResponse
    {
        try {
            if ($request->method() === 'GET') {
                $images = DB::select(
                    'SELECT id, titre, description, statut, lien_image FROM "Images" ORDER BY "dateDeCreation" DESC'
                );

                return response()->json(['success' => true, 'images' => $images], 200);
            }

            if ($request->method() === 'POST' && $request->has('action')) {
                $action = $request->input('action');

                if ($action === 'modifier') {
                    $id = (int) $request->input('id', 0);
                    $titre = $request->input('titre');
                    $description = $request->input('description');
                    $statut = strtolower($request->input('statut', ''));

                    DB::update(
                        'UPDATE "Images" SET titre = :titre, description = :description, statut = :statut WHERE id = :id',
                        ['titre' => $titre, 'description' => $description, 'statut' => $statut, 'id' => $id]
                    );

                    return response()->json(['success' => true, 'message' => 'Image modifiée avec succès'], 200);
                }

                if ($action === 'supprimer') {
                    $id = (int) $request->input('id', 0);

                    $rows = DB::select('SELECT lien_image FROM "Images" WHERE id = :id', ['id' => $id]);
                    $image = $rows[0] ?? null;

                    if ($image && ! empty($image->lien_image)) {
                        $chemin = public_path($image->lien_image);
                        if (file_exists($chemin)) {
                            @unlink($chemin);
                        }
                    }

                    DB::delete('DELETE FROM "Images" WHERE id = :id', ['id' => $id]);

                    return response()->json(['success' => true, 'message' => 'Supprimé avec succès'], 200);
                }

                if ($action === 'ajouter') {
                    $titre = $request->input('titre');
                    $description = $request->input('description');
                    $statut = strtolower($request->input('statut', ''));
                    $sansImage = $request->input('sans_image') === '1';

                    if ($sansImage) {
                        $lienImage = '';
                    } else {
                        $file = $request->file('lien_image');

                        if (! $file || ! $file->isValid()) {
                            return response()->json(['success' => false, 'message' => 'Erreur upload image'], 200);
                        }

                        $extension = $file->getClientOriginalExtension();
                        $nomFichier = uniqid('img_').'.'.$extension;
                        $uploadDir = public_path('uploads');

                        if (! is_dir($uploadDir)) {
                            mkdir($uploadDir, 0755, true);
                        }

                        try {
                            $file->move($uploadDir, $nomFichier);
                        } catch (\Throwable $e) {
                            // move_uploaded_file() du PHP source renvoie un booleen false sans
                            // exception ; UploadedFile::move() de Laravel leve une exception a la
                            // place. Convertie ici vers le meme message que le PHP source pour
                            // rester fidele au contrat de reponse sur ce cas d'echec.
                            return response()->json(['success' => false, 'message' => "Impossible de sauvegarder l'image"], 200);
                        }

                        $lienImage = 'uploads/'.$nomFichier;
                    }

                    DB::insert(
                        'INSERT INTO "Images" (titre, description, statut, lien_image) VALUES (:titre, :description, :statut, :lien_image)',
                        ['titre' => $titre, 'description' => $description, 'statut' => $statut, 'lien_image' => $lienImage]
                    );

                    return response()->json(['success' => true, 'message' => 'Ajouté avec succès'], 200);
                }
            }

            return response()->json(['success' => false, 'message' => 'Action non reconnue'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }
}
