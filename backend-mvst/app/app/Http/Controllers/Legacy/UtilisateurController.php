<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UtilisateurController extends Controller
{
    /**
     * Equivalent de get_utilisateur.php.
     * GET, query string "id" (en realite l'idUtilisateur, pas la cle numerique).
     */
    public function get(Request $request): JsonResponse
    {
        $id = $request->query('id', '');
        if (! $id) {
            return response()->json(['success' => false, 'message' => 'id manquant'], 200);
        }

        try {
            $rows = DB::select(
                'SELECT nom, prenoms, telephone, residence, points, mail, "dateDeCreation" FROM "Utilisateurs" WHERE "idUtilisateur" = :id LIMIT 1',
                ['id' => $id]
            );
            $row = $rows[0] ?? null;

            if ($row) {
                return response()->json([
                    'success' => true,
                    'utilisateur' => [
                        'nom' => $row->nom,
                        'prenoms' => $row->prenoms,
                        'telephone' => $row->telephone,
                        'residence' => $row->residence,
                        'points' => $row->points,
                        'mail' => $row->mail,
                        'dateDeCreation' => $row->dateDeCreation,
                    ],
                ], 200);
            }

            return response()->json(['success' => false, 'message' => 'Utilisateur non trouvé'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de verifierUtilisateur.php.
     * POST JSON. idUtilisateur obligatoire.
     */
    public function verifier(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (! isset($data['idUtilisateur'])) {
                return response()->json(['success' => false, 'message' => 'Paramètre manquant'], 200);
            }

            $rows = DB::select(
                'SELECT nom, prenoms, telephone, points FROM "Utilisateurs" WHERE "idUtilisateur" = :idUtilisateur',
                ['idUtilisateur' => $data['idUtilisateur']]
            );
            $utilisateur = $rows[0] ?? null;

            if (! $utilisateur) {
                return response()->json(['success' => false, 'message' => 'Utilisateur introuvable'], 200);
            }

            return response()->json([
                'success' => true,
                'nom' => $utilisateur->nom,
                'prenoms' => $utilisateur->prenoms,
                'telephone' => $utilisateur->telephone,
                'points' => (int) $utilisateur->points,
            ], 200);
        } catch (\PDOException $e) {
            return response()->json(['success' => false, 'message' => 'PDO: '.$e->getMessage()], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur: '.$e->getMessage()], 200);
        } catch (\Error $e) {
            return response()->json(['success' => false, 'message' => 'Fatal: '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de verifierTelephone.php.
     * POST JSON. telephone obligatoire.
     */
    public function verifierTelephone(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['telephone'])) {
                return response()->json(['success' => false, 'message' => 'Paramètre manquant'], 200);
            }

            $rows = DB::select('SELECT id, points FROM "Utilisateurs" WHERE telephone = :telephone', ['telephone' => $data['telephone']]);
            $utilisateur = $rows[0] ?? null;

            $existe = (bool) $utilisateur;
            $bloque = $utilisateur && (int) $utilisateur->points <= 0;

            return response()->json([
                'success' => true,
                'existe' => $existe,
                'bloque' => $bloque,
                'points' => $utilisateur ? (int) $utilisateur->points : 0,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de insert_utilisateur.php.
     * POST JSON. idUtilisateur+nom+prenoms+telephone obligatoires.
     *
     * ATTENTION (a noter dans A_REVOIR.md) : si "mail" est absent, une adresse est
     * generee automatiquement ("<telephone>@gmail.com") sans aucune verification
     * qu'elle est valide ou reellement disponible sur ce domaine.
     */
    public function insert(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['idUtilisateur']) || ! isset($data['nom']) || ! isset($data['prenoms']) || ! isset($data['telephone'])) {
                return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 200);
            }

            $idUtilisateur = $data['idUtilisateur'];
            $idAuth = $data['idAuth'] ?? '';
            $nom = $data['nom'];
            $prenoms = $data['prenoms'];
            $residence = $data['residence'] ?? '';
            $telephone = $data['telephone'];
            $points = $data['points'] ?? 3;
            $mail = $data['mail'] ?? $telephone.'@gmail.com';

            $doublon = DB::select(
                'SELECT "idUtilisateur" FROM "Utilisateurs" WHERE telephone = :telephone OR "idUtilisateur" = :idUtilisateur',
                ['telephone' => $telephone, 'idUtilisateur' => $idUtilisateur]
            );
            if (count($doublon) > 0) {
                return response()->json(['success' => false, 'message' => 'Utilisateur déjà existant'], 200);
            }

            $result = DB::insert(
                'INSERT INTO "Utilisateurs" ("idUtilisateur", "idAuth", nom, prenoms, residence, telephone, points, mail, "dateDeCreation")
                 VALUES (:idUtilisateur, :idAuth, :nom, :prenoms, :residence, :telephone, :points, :mail, NOW())',
                [
                    'idUtilisateur' => $idUtilisateur,
                    'idAuth' => $idAuth,
                    'nom' => $nom,
                    'prenoms' => $prenoms,
                    'residence' => $residence,
                    'telephone' => $telephone,
                    'points' => $points,
                    'mail' => $mail,
                ]
            );

            if ($result) {
                return response()->json(['success' => true, 'message' => 'Utilisateur créé avec succès'], 200);
            }

            return response()->json(['success' => false, 'message' => "Erreur lors de l'insertion"], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de update_utilisateur.php.
     * POST JSON. idUtilisateur obligatoire ; nom/prenoms/residence optionnels
     * (au moins un requis). Le telephone n'est volontairement pas modifiable ici.
     *
     * ATTENTION (a noter dans A_REVOIR.md) : aucune verification que idUtilisateur
     * correspond a une ligne existante. DB::update() renvoie le nombre de lignes
     * affectees mais le PHP source ne le verifie pas (il ne teste que le booleen de
     * succes de l'execution) : success=true meme si 0 ligne a ete modifiee. Reproduit
     * a l'identique (le test du "vrai" retour de execute() n'est pas rowCount()).
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['idUtilisateur'])) {
                return response()->json(['success' => false, 'message' => 'ID utilisateur manquant'], 200);
            }

            $idUtilisateur = $data['idUtilisateur'];

            $fields = [];
            $params = ['idUtilisateur' => $idUtilisateur];

            if (isset($data['nom'])) {
                $fields[] = 'nom = :nom';
                $params['nom'] = $data['nom'];
            }
            if (isset($data['prenoms'])) {
                $fields[] = 'prenoms = :prenoms';
                $params['prenoms'] = $data['prenoms'];
            }
            if (isset($data['residence'])) {
                $fields[] = 'residence = :residence';
                $params['residence'] = $data['residence'];
            }

            if (empty($fields)) {
                return response()->json(['success' => false, 'message' => 'Aucune donnée à mettre à jour'], 200);
            }

            $sql = 'UPDATE "Utilisateurs" SET '.implode(', ', $fields).' WHERE "idUtilisateur" = :idUtilisateur';
            DB::update($sql, $params);

            return response()->json(['success' => true, 'message' => 'Profil mis à jour avec succès'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
    }
}
