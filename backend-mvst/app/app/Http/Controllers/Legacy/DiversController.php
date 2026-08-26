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

    /**
     * Equivalent de heuresDepart.php.
     * GET -> liste. POST action=ajouter/modifier/supprimer.
     * Meme absence d'isset() sur les champs POST que gares.php/infosGares.php
     * (lot 4) : voir A_REVOIR.md.
     */
    public function heuresDepart(Request $request): JsonResponse
    {
        try {
            if ($request->method() === 'GET') {
                $heures = DB::select('SELECT * FROM "HeuresDeDeparts" ORDER BY heure ASC');

                return response()->json(['success' => true, 'heures' => $heures], 200);
            }

            $data = json_decode($request->getContent(), true);
            $action = $data['action'] ?? '';

            if ($action === 'ajouter') {
                $type = $data['type'] ?? 'standard';
                DB::insert('INSERT INTO "HeuresDeDeparts" (heure, type) VALUES (:heure, :type)', ['heure' => $data['heure'] ?? null, 'type' => $type]);

                return response()->json(['success' => true, 'message' => 'Heure ajoutée'], 200);
            }

            if ($action === 'modifier') {
                $type = $data['type'] ?? 'standard';
                DB::update(
                    'UPDATE "HeuresDeDeparts" SET heure = :heure, type = :type WHERE id = :id',
                    ['heure' => $data['heure'] ?? null, 'type' => $type, 'id' => (int) ($data['id'] ?? 0)]
                );

                return response()->json(['success' => true, 'message' => 'Heure modifiée'], 200);
            }

            if ($action === 'supprimer') {
                DB::delete('DELETE FROM "HeuresDeDeparts" WHERE id = :id', ['id' => (int) ($data['id'] ?? 0)]);

                return response()->json(['success' => true, 'message' => 'Heure supprimée'], 200);
            }

            return response()->json(['success' => false, 'message' => 'Action non reconnue'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de datesDisponibles.php.
     * Aucune verification de methode HTTP dans le PHP source (voir A_REVOIR.md /
     * inventaire phase 1) : accepte GET/POST/etc. de la meme facon, seul le JSON
     * body avec "action" compte. OPTIONS court-circuite avant tout (CORS preflight,
     * corps vide, avant meme d'ouvrir la connexion DB).
     *
     * En-tetes CORS (Access-Control-Allow-*) presents sur TOUTES les reponses, y
     * compris les erreurs, comme dans le fichier source.
     */
    /**
     * Type de retour Symfony\Component\HttpFoundation\Response : c'est l'ancetre
     * commun de Illuminate\Http\Response (reponse OPTIONS a corps vide) et
     * Illuminate\Http\JsonResponse (toutes les autres reponses) -- ces deux
     * classes Illuminate sont soeurs, pas parent/enfant.
     */
    public function datesDisponibles(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        if ($request->method() === 'OPTIONS') {
            return response('', 200)
                ->header('Content-Type', 'application/json')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type');
        }

        try {
            $input = json_decode($request->getContent(), true);
            $action = $input['action'] ?? '';

            $response = match ($action) {
                'lire' => $this->datesDisponiblesLire(),
                'sauvegarder' => $this->datesDisponiblesSauvegarder($input),
                default => response()->json(['success' => false, 'message' => 'Action non reconnue'], 200),
            };
        } catch (\Exception $e) {
            // Contrairement aux autres fichiers du projet, datesDisponibles.php ne
            // renvoie PAS $e->getMessage() au client (seulement un message generique) ;
            // l'erreur reelle part dans les logs serveur. Seul fichier du projet a
            // faire ca. Reproduit a l'identique (Log::error au lieu de error_log()).
            \Illuminate\Support\Facades\Log::error('Erreur datesDisponibles.php: '.$e->getMessage());
            $response = response()->json(['success' => false, 'message' => 'Erreur serveur'], 200);
        }

        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }

    private function datesDisponiblesLire(): JsonResponse
    {
        $rows = DB::select('SELECT valeur FROM "DatesDisponibles" WHERE cle = :cle', ['cle' => 'nbJours']);
        $result = $rows[0] ?? null;

        if ($result) {
            return response()->json(['success' => true, 'nbJours' => (int) $result->valeur], 200);
        }

        return response()->json(['success' => true, 'nbJours' => 6], 200);
    }

    private function datesDisponiblesSauvegarder(array $input): JsonResponse
    {
        if (! isset($input['nbJours']) || ! is_numeric($input['nbJours'])) {
            return response()->json(['success' => false, 'message' => 'Valeur nbJours invalide'], 200);
        }

        $nbJours = (int) $input['nbJours'];

        if ($nbJours < 1 || $nbJours > 30) {
            return response()->json(['success' => false, 'message' => 'La valeur doit être entre 1 et 30'], 200);
        }

        DB::insert(
            'INSERT INTO "DatesDisponibles" (cle, valeur) VALUES (:cle, :valeur) ON CONFLICT (cle) DO UPDATE SET valeur = :valeur2, date_modification = CURRENT_TIMESTAMP',
            ['cle' => 'nbJours', 'valeur' => $nbJours, 'valeur2' => $nbJours]
        );

        // Notification a socket-mvst via le reseau Docker interne, exactement comme
        // le PHP source : appel HTTP synchrone, erreur totalement avalee par "@".
        // socket-mvst lui-meme n'est ni lu ni modifie.
        $nodeUrl = 'http://socket-mvst:3000/emit-config-dates?nbJours='.$nbJours;
        @file_get_contents($nodeUrl);

        return response()->json(['success' => true, 'message' => 'Configuration mise à jour', 'nbJours' => $nbJours], 200);
    }
}
