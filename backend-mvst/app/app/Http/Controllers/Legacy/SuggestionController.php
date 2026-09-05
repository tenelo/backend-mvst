<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuggestionController extends Controller
{
    /**
     * Equivalent de api_suggestions.php.
     * GET action=get_all/get_by_user (query string) ou POST JSON
     * action=add/update_statut/delete/admin_delete. CORS + OPTIONS geres
     * manuellement (comme datesDisponibles.php / api_lignes.php).
     *
     * ATTENTION MAJEURE (a noter dans A_REVOIR.md, meme decision deja validee
     * qu'aux points 9/10/12) : le fichier source n'a AUCUN try/catch, sur tout le
     * fichier (GET et POST). Un catch (\Throwable) global a ete ajoute pour
     * renvoyer un JSON propre {"success":false,"error":...} en HTTP 200 (cle
     * "error", jamais "message" : ce fichier n'utilise que "error" comme
     * api_lignes.php).
     */
    public function apiSuggestions(Request $request): \Symfony\Component\HttpFoundation\Response
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
                if ($action === 'get_all') {
                    $dateDebut = $request->query('date_debut');
                    $dateFin = $request->query('date_fin');
                    $where = [];
                    $params = [];

                    if ($dateDebut) {
                        $where[] = 'createdat >= :date_debut';
                        $params['date_debut'] = $dateDebut.' 00:00:00';
                    }
                    if ($dateFin) {
                        $where[] = 'createdat <= :date_fin';
                        $params['date_fin'] = $dateFin.' 23:59:59';
                    }

                    $sql = 'SELECT * FROM "Suggestions"';
                    if (! empty($where)) {
                        $sql .= ' WHERE '.implode(' AND ', $where);
                    }
                    $sql .= ' ORDER BY createdat DESC';

                    $rows = DB::select($sql, $params);
                    $response = response()->json(['success' => true, 'suggestions' => $this->formatRows($rows)], 200);
                } elseif ($action === 'get_by_user') {
                    $idutilisateur = trim($request->query('idutilisateur', ''));

                    if (empty($idutilisateur)) {
                        $response = response()->json(['success' => false, 'error' => 'idutilisateur requis'], 200);
                    } else {
                        $rows = DB::select(
                            'SELECT * FROM "Suggestions" WHERE idutilisateur = :id ORDER BY createdat DESC',
                            ['id' => $idutilisateur]
                        );
                        $response = response()->json(['success' => true, 'suggestions' => $this->formatRows($rows)], 200);
                    }
                } else {
                    $response = response()->json(['success' => false, 'error' => 'action inconnue'], 200);
                }
            } elseif ($method === 'POST') {
                if ($action === 'add') {
                    $nom = trim($input['nom'] ?? '');
                    $telephone = trim($input['telephone'] ?? '');
                    $message = trim($input['message'] ?? '');
                    $categorie = trim($input['categorie'] ?? 'Autre');
                    $idutilisateur = trim($input['idutilisateur'] ?? '');

                    if (empty($message) || empty($idutilisateur)) {
                        $response = response()->json(['success' => false, 'error' => 'Message et identifiant requis'], 200);
                    } else {
                        if (empty($nom)) {
                            $nom = 'Utilisateur';
                        }
                        if (empty($telephone)) {
                            $telephone = '-';
                        }

                        $row = DB::selectOne(
                            'INSERT INTO "Suggestions" (nom, telephone, message, categorie, idutilisateur)
                             VALUES (:nom, :telephone, :message, :categorie, :idutilisateur)
                             RETURNING id, createdat',
                            ['nom' => $nom, 'telephone' => $telephone, 'message' => $message, 'categorie' => $categorie, 'idutilisateur' => $idutilisateur]
                        );

                        $this->notifierNouvelleSuggestion($message, $categorie);

                        $response = response()->json(['success' => true, 'id' => (int) $row->id, 'createdat' => $row->createdat], 200);
                    }
                } elseif ($action === 'update_statut') {
                    $id = (int) ($input['id'] ?? 0);
                    $statut = trim($input['statut'] ?? '');

                    if ($id <= 0 || ! in_array($statut, ['en_attente', 'lu', 'traite'])) {
                        $response = response()->json(['success' => false, 'error' => 'Paramètres invalides'], 200);
                    } else {
                        DB::update('UPDATE "Suggestions" SET statut = :statut WHERE id = :id', ['statut' => $statut, 'id' => $id]);
                        $response = response()->json(['success' => true], 200);
                    }
                } elseif ($action === 'delete') {
                    $id = (int) ($input['id'] ?? 0);
                    $idutilisateur = trim($input['idutilisateur'] ?? '');

                    if ($id <= 0 || empty($idutilisateur)) {
                        $response = response()->json(['success' => false, 'error' => 'Paramètres invalides'], 200);
                    } else {
                        $affected = DB::delete(
                            'DELETE FROM "Suggestions" WHERE id = :id AND idutilisateur = :uid',
                            ['id' => $id, 'uid' => $idutilisateur]
                        );
                        $response = response()->json(['success' => $affected > 0], 200);
                    }
                } elseif ($action === 'admin_delete') {
                    $id = (int) ($input['id'] ?? 0);

                    if ($id <= 0) {
                        $response = response()->json(['success' => false, 'error' => 'ID invalide'], 200);
                    } else {
                        $rows = DB::select('SELECT idutilisateur FROM "Suggestions" WHERE id = :id', ['id' => $id]);
                        $row = $rows[0] ?? null;
                        $idutilisateur = $row ? $row->idutilisateur : '';

                        $affected = DB::delete('DELETE FROM "Suggestions" WHERE id = :id', ['id' => $id]);

                        $response = response()->json(['success' => $affected > 0, 'idutilisateur' => $idutilisateur], 200);
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

    private function formatRows(array $rows): array
    {
        return array_map(function ($r) {
            $r->id = (int) $r->id;

            return $r;
        }, $rows);
    }

    private function notifierNouvelleSuggestion(string $message, string $categorie): void
    {
        try {
            $contexte = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode(['message' => $message, 'categorie' => $categorie]),
                    'timeout' => 2,
                ],
            ]);

            @file_get_contents('http://socket-mvst:3000/notif-suggestions/nouvelle', false, $contexte);
        } catch (\Throwable $e) {
            // Best-effort : la notif est perdue ce coup-ci, la suggestion reste enregistree.
        }
    }
}
