<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LignePrixController extends Controller
{
    /**
     * Equivalent de api_lignes.php.
     * GET (liste, filtrable par type/sans_prix/all) ou POST action=ajouter/modifier/supprimer.
     * CORS + OPTIONS geres manuellement (comme datesDisponibles.php).
     *
     * ATTENTION MAJEURE (a noter dans A_REVOIR.md) : le fichier source n'a AUCUN
     * try/catch, sur tout le fichier (GET et POST). Toute exception PDO (contrainte
     * violee, colonne invalide, etc.) provoquerait un fatal error PHP brut (HTML,
     * chemin de fichier expose) avec malgre tout HTTP 200. Meme classe de probleme
     * que le point 9 (lot 3, reinitialiserPoints limit=0) et le point 10 (lot 4,
     * warnings sur champs manquants) : suivant la meme decision deja validee, un
     * catch (\Throwable) global a ete ajoute ici pour renvoyer un JSON propre
     * {"success":false,"error":...} en HTTP 200 (cle "error", pas "message" : ce
     * fichier n'utilise QUE "error" sur ses propres branches d'echec, jamais
     * "message" -- coherence avec la convention locale du fichier, pas la
     * convention globale du projet).
     *
     * Autres particularites reproduites a l'identique (pas des deviations) :
     * - "modifier" ne verifie jamais rowCount() : renvoie toujours {"success":true}
     *   (sans cle "message"), meme si l'id n'existe pas.
     * - "supprimer" renvoie {"success": rowCount() > 0} -- un id inexistant donne
     *   {"success":false} SANS cle "error" ni "message" du tout.
     * - "ajouter" ne renvoie pas de cle "message" non plus, seulement {"success","id"}.
     */
    public function apiLignes(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        if ($request->method() === 'OPTIONS') {
            return response('', 200)
                ->header('Content-Type', 'application/json; charset=utf-8')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type');
        }

        try {
            $method = $request->method();
            $input = json_decode($request->getContent(), true) ?? [];
            $action = $request->query('action') ?? ($input['action'] ?? '');

            if ($method === 'GET') {
                $type = trim($request->query('type', 'standard'));
                $sansPrix = $request->query('sans_prix') === '1';

                if ($type === 'all') {
                    $rows = DB::select('SELECT * FROM "Lignes" ORDER BY id ASC');
                } elseif ($sansPrix) {
                    $rows = DB::select('SELECT * FROM "Lignes" WHERE type = :type AND prix <= 0 ORDER BY id ASC', ['type' => $type]);
                } else {
                    $rows = DB::select('SELECT * FROM "Lignes" WHERE type = :type ORDER BY id ASC', ['type' => $type]);
                }

                $lignes = array_map(function ($r) {
                    $r->id = (int) $r->id;
                    $r->prix = (int) $r->prix;

                    return $r;
                }, $rows);

                $response = response()->json(['success' => true, 'lignes' => $lignes], 200);
            } elseif ($method === 'POST') {
                if ($action === 'ajouter') {
                    $depart = trim($input['depart'] ?? '');
                    $destination = trim($input['destination'] ?? '');
                    $ligne = trim($input['ligne'] ?? '');
                    $prix = (int) ($input['prix'] ?? 0);
                    $type = trim($input['type'] ?? 'standard');

                    if (empty($depart) || empty($destination) || $prix <= 0) {
                        $response = response()->json(['success' => false, 'error' => 'Paramètres invalides'], 200);
                    } else {
                        if (empty($ligne)) {
                            $ligne = "{$depart} {$destination}";
                        }

                        $row = DB::selectOne(
                            'INSERT INTO "Lignes" (depart, destination, ligne, prix, type)
                             VALUES (:depart, :destination, :ligne, :prix, :type)
                             RETURNING id',
                            ['depart' => $depart, 'destination' => $destination, 'ligne' => $ligne, 'prix' => $prix, 'type' => $type]
                        );

                        $response = response()->json(['success' => true, 'id' => (int) $row->id], 200);
                    }
                } elseif ($action === 'modifier') {
                    $id = (int) ($input['id'] ?? 0);
                    $depart = trim($input['depart'] ?? '');
                    $destination = trim($input['destination'] ?? '');
                    $ligne = trim($input['ligne'] ?? '');
                    $prix = (int) ($input['prix'] ?? 0);

                    if ($id <= 0 || empty($depart) || empty($destination) || $prix <= 0) {
                        $response = response()->json(['success' => false, 'error' => 'Paramètres invalides'], 200);
                    } else {
                        if (empty($ligne)) {
                            $ligne = "{$depart} {$destination}";
                        }

                        DB::update(
                            'UPDATE "Lignes"
                             SET depart = :depart, destination = :destination, ligne = :ligne, prix = :prix
                             WHERE id = :id',
                            ['id' => $id, 'depart' => $depart, 'destination' => $destination, 'ligne' => $ligne, 'prix' => $prix]
                        );

                        $response = response()->json(['success' => true], 200);
                    }
                } elseif ($action === 'supprimer') {
                    $id = (int) ($input['id'] ?? 0);

                    if ($id <= 0) {
                        $response = response()->json(['success' => false, 'error' => 'ID invalide'], 200);
                    } else {
                        $affected = DB::delete('DELETE FROM "Lignes" WHERE id = :id', ['id' => $id]);
                        $response = response()->json(['success' => $affected > 0], 200);
                    }
                } else {
                    $response = response()->json(['success' => false, 'error' => 'action inconnue'], 200);
                }
            } else {
                $response = response()->json(['success' => false, 'error' => 'Méthode non supportée'], 200);
            }
        } catch (\Throwable $e) {
            $response = response()->json(['success' => false, 'error' => $e->getMessage()], 200);
        }

        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }

    /**
     * Equivalent de prixTickets.php.
     * GET -> liste. POST action=ajouter/modifier/supprimer.
     */
    public function prixTickets(Request $request): JsonResponse
    {
        try {
            if ($request->method() === 'GET') {
                $prix = DB::select('SELECT * FROM "PrixDesTickets" ORDER BY id ASC');

                return response()->json(['success' => true, 'prix' => $prix], 200);
            }

            $data = json_decode($request->getContent(), true);
            $action = $data['action'] ?? '';

            if ($action === 'ajouter') {
                $type = $data['type'] ?? 'standard';
                DB::insert(
                    'INSERT INTO "PrixDesTickets" (axe, prix, type) VALUES (:axe, :prix, :type)',
                    ['axe' => $data['axe'] ?? null, 'prix' => (int) ($data['prix'] ?? 0), 'type' => $type]
                );

                return response()->json(['success' => true, 'message' => 'Prix ajouté'], 200);
            }

            if ($action === 'modifier') {
                $type = $data['type'] ?? 'standard';
                DB::update(
                    'UPDATE "PrixDesTickets" SET axe = :axe, prix = :prix, type = :type WHERE id = :id',
                    ['axe' => $data['axe'] ?? null, 'prix' => (int) ($data['prix'] ?? 0), 'type' => $type, 'id' => (int) ($data['id'] ?? 0)]
                );

                return response()->json(['success' => true, 'message' => 'Prix modifié'], 200);
            }

            if ($action === 'supprimer') {
                DB::delete('DELETE FROM "PrixDesTickets" WHERE id = :id', ['id' => (int) ($data['id'] ?? 0)]);

                return response()->json(['success' => true, 'message' => 'Prix supprimé'], 200);
            }

            return response()->json(['success' => false, 'message' => 'Action non reconnue'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de getPrixDesTickets.php.
     * POST JSON. Parametre optionnel "type" (defaut "standard").
     *
     * ATTENTION (deja signalee en phase 1, reproduite a l'identique) : lit la table
     * "Lignes" (pas "PrixDesTickets" malgre le nom du fichier) et renvoie la cle
     * "heures" pour des donnees de prix. Ne pas renommer.
     */
    public function getPrixDesTickets(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $type = $data['type'] ?? 'standard';

            $prix = DB::select(
                'SELECT ligne AS axe, prix FROM "Lignes" WHERE type = :type AND prix > 0 ORDER BY ligne ASC',
                ['type' => $type]
            );

            return response()->json(['success' => true, 'heures' => $prix], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }
}
