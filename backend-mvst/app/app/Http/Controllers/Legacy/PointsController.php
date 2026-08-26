<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PointsController extends Controller
{
    /**
     * Equivalent de decrementerPoints.php.
     * POST JSON. idUtilisateur obligatoire ; mettreAZero optionnel (defaut false).
     *
     * ATTENTION (a noter dans A_REVOIR.md) : "mettreAZero" est teste en truthy PHP
     * brut (if ($mettreAZero)), pas avec une comparaison stricte === true. Une valeur
     * comme la chaine "false" (au lieu du booleen JSON false) declencherait a tort le
     * blocage total a 0 point. Reproduit a l'identique : meme expression PHP, donc
     * meme comportement, sans conversion.
     */
    public function decrementer(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['idUtilisateur'])) {
                return response()->json(['success' => false, 'message' => 'Paramètre manquant'], 200);
            }

            $idUtilisateur = $data['idUtilisateur'];
            $mettreAZero = isset($data['mettreAZero']) ? $data['mettreAZero'] : false;

            $rows = DB::select('SELECT nom, prenoms, telephone, points FROM "Utilisateurs" WHERE "idUtilisateur" = :id', ['id' => $idUtilisateur]);
            $utilisateur = $rows[0] ?? null;

            if (! $utilisateur) {
                return response()->json(['success' => false, 'message' => 'Utilisateur introuvable'], 200);
            }

            $anciensPoints = (int) $utilisateur->points;
            $nom = $utilisateur->nom;
            $prenoms = $utilisateur->prenoms ?? '';
            $telephone = $utilisateur->telephone;

            if ($mettreAZero) {
                $nouveauxPoints = 0;
                $motif = 'Blocage automatique : 4ème série de tentatives Code Secret échouées';
            } else {
                $nouveauxPoints = max(0, $anciensPoints - 1);
                $motif = 'Suppression de ticket par administrateur (-1 point)';
            }

            DB::update('UPDATE "Utilisateurs" SET points = :points WHERE "idUtilisateur" = :id', ['points' => $nouveauxPoints, 'id' => $idUtilisateur]);

            DB::insert(
                'INSERT INTO "historique_actions" ("idUtilisateur", nom, prenoms, telephone, anciens_points, nouveaux_points, motif, date) VALUES (:id, :nom, :prenoms, :tel, :ancien, :nouveau, :motif, NOW())',
                [
                    'id' => $idUtilisateur,
                    'nom' => $nom,
                    'prenoms' => $prenoms,
                    'tel' => $telephone,
                    'ancien' => $anciensPoints,
                    'nouveau' => $nouveauxPoints,
                    'motif' => $motif,
                ]
            );

            return response()->json(['success' => true, 'points' => $nouveauxPoints], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de reinitialiserPoints.php.
     * POST JSON. Une seule route, dispatch interne sur "action"
     * (lister_tous / lister_bloques / reinitialiser / verifier), exactement comme
     * le switch($action) du fichier source.
     *
     * ATTENTION (voir A_REVOIR.md point 9) : ni lister_tous ni lister_bloques ne se
     * protegent contre limit=0 (bug logique du PHP source, non corrige ici). Ces deux
     * methodes privees capturent en plus \Throwable localement pour renvoyer un JSON
     * standard {success:false, message} en HTTP 200 au lieu de laisser une
     * \DivisionByZeroError remonter jusqu'au gestionnaire global de Laravel (qui
     * repondrait en HTTP 500 avec une page de debogage HTML). Deviation assumee de la
     * decision figee n1 (decidee le 26/08/2026) : le PHP source repond 200 + fatal
     * error HTML brut sur ce cas precis, Laravel repond 200 + JSON d'erreur. On
     * privilegie ici la coherence du contrat JSON que les apps savent lire, plutot
     * qu'une replication litterale d'un plantage PHP non reproductible a l'identique
     * de toute facon.
     */
    public function reinitialiserPoints(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $action = $data['action'] ?? '';

            return match ($action) {
                'lister_tous' => $this->listerTous($data),
                'lister_bloques' => $this->listerBloques($data),
                'reinitialiser' => $this->reinitialiser($data),
                'verifier' => $this->verifier($data),
                default => response()->json(['success' => false, 'message' => 'Action non reconnue'], 200),
            };
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * DEVIATION ASSUMEE de la decision figee n1 (HTTP 200 partout), decidee le
     * 26/08/2026 : voir A_REVOIR.md point 9. Le PHP source ne se protege pas contre
     * limit=0 (ceil($total/$limit) leve une \DivisionByZeroError, un \Error non
     * intercepte par son catch (Exception $e) -> fatal error brut en HTML, mais tout
     * de meme HTTP 200). Laravel ne peut pas laisser un \Error remonter jusqu'a son
     * gestionnaire global sans en heriter le HTTP 500 + page de debogage HTML, tres
     * eloigne du contrat JSON du reste du projet. Choix retenu : capturer ici
     * \Throwable (donc aussi les \Error comme DivisionByZeroError) et renvoyer le
     * format JSON standard {success:false, message} en HTTP 200, SANS corriger le
     * calcul lui-meme -- le bug logique (limit=0 jamais valide) reste reproduit a
     * l'identique, seule la forme de la reponse d'erreur change (JSON au lieu d'un
     * fatal error HTML).
     */
    private function listerTous(array $data): JsonResponse
    {
        try {
            $page = isset($data['page']) ? (int) $data['page'] : 1;
            $limit = isset($data['limit']) ? (int) $data['limit'] : 100;
            $offset = ($page - 1) * $limit;

            $total = DB::selectOne('SELECT COUNT(*) as total FROM "Utilisateurs"')->total;

            $utilisateurs = DB::select(
                'SELECT "idUtilisateur", nom, prenoms, telephone, residence, points, "dateDeCreation"
                 FROM "Utilisateurs"
                 ORDER BY "dateDeCreation" DESC
                 LIMIT ? OFFSET ?',
                [$limit, $offset]
            );

            return response()->json([
                'success' => true,
                'utilisateurs' => $utilisateurs,
                'total' => (int) $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * Voir le commentaire de listerTous() : meme deviation assumee de la decision
     * figee n1 pour la meme raison (limit=0 -> \DivisionByZeroError non catchee par
     * un simple catch (Exception $e)).
     */
    private function listerBloques(array $data): JsonResponse
    {
        try {
            $page = isset($data['page']) ? (int) $data['page'] : 1;
            $limit = isset($data['limit']) ? (int) $data['limit'] : 100;
            $offset = ($page - 1) * $limit;

            $total = DB::selectOne(
                'SELECT COUNT(DISTINCT h."idUtilisateur") as total
                 FROM "historique_actions" h
                 INNER JOIN (
                     SELECT "idUtilisateur", MAX(date) AS derniere_date
                     FROM "historique_actions"
                     WHERE nouveaux_points <= 0
                     GROUP BY "idUtilisateur"
                 ) derniere ON h."idUtilisateur" = derniere."idUtilisateur" AND h.date = derniere.derniere_date'
            )->total;

            $bloques = DB::select(
                'SELECT h."idUtilisateur", h.nom, h.prenoms, h.telephone, h.nouveaux_points AS points, h.motif, h.date
                 FROM "historique_actions" h
                 INNER JOIN (
                     SELECT "idUtilisateur", MAX(date) AS derniere_date
                     FROM "historique_actions"
                     WHERE nouveaux_points <= 0
                     GROUP BY "idUtilisateur"
                 ) derniere ON h."idUtilisateur" = derniere."idUtilisateur" AND h.date = derniere.derniere_date
                 ORDER BY h.date DESC
                 LIMIT ? OFFSET ?',
                [$limit, $offset]
            );

            return response()->json([
                'success' => true,
                'bloques' => $bloques,
                'total' => (int) $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => ceil($total / $limit),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 200);
        }
    }

    private function reinitialiser(array $data): JsonResponse
    {
        if (! isset($data['idUtilisateur']) || ! isset($data['points'])) {
            return response()->json(['success' => false, 'message' => 'Parametres manquants'], 200);
        }

        $idUtilisateur = $data['idUtilisateur'];
        $points = (int) $data['points'];
        $motif = $data['motif'] ?? 'Reinitialisation par administrateur';

        if ($points < 1) {
            return response()->json(['success' => false, 'message' => 'Le nombre de points doit etre superieur a 0'], 200);
        }

        $rows = DB::select('SELECT nom, prenoms, telephone, points FROM "Utilisateurs" WHERE "idUtilisateur" = :id', ['id' => $idUtilisateur]);
        $utilisateur = $rows[0] ?? null;

        if (! $utilisateur) {
            return response()->json(['success' => false, 'message' => 'Utilisateur introuvable'], 200);
        }

        $anciensPoints = (int) $utilisateur->points;
        $nom = $utilisateur->nom;
        $prenoms = $utilisateur->prenoms ?? '';
        $telephone = $utilisateur->telephone;

        DB::update('UPDATE "Utilisateurs" SET points = :points WHERE "idUtilisateur" = :id', ['points' => $points, 'id' => $idUtilisateur]);

        DB::insert(
            'INSERT INTO "historique_actions" ("idUtilisateur", nom, prenoms, telephone, anciens_points, nouveaux_points, motif, date) VALUES (:id, :nom, :prenoms, :tel, :ancien, :nouveau, :motif, NOW())',
            [
                'id' => $idUtilisateur,
                'nom' => $nom,
                'prenoms' => $prenoms,
                'tel' => $telephone,
                'ancien' => $anciensPoints,
                'nouveau' => $points,
                'motif' => "Deblocage : {$motif}",
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "{$nom} debloque avec {$points} points",
            'points' => $points,
        ], 200);
    }

    private function verifier(array $data): JsonResponse
    {
        if (! isset($data['telephone'])) {
            return response()->json(['success' => false, 'message' => 'Numero manquant'], 200);
        }

        $telephone = $data['telephone'];

        $rows = DB::select('SELECT "idUtilisateur", nom, prenoms, telephone, residence, points FROM "Utilisateurs" WHERE telephone = :tel', ['tel' => $telephone]);
        $utilisateur = $rows[0] ?? null;

        if (! $utilisateur) {
            return response()->json(['success' => false, 'message' => 'Aucun utilisateur trouve'], 200);
        }

        $historique = DB::select('SELECT * FROM "historique_actions" WHERE "idUtilisateur" = :id ORDER BY date DESC', ['id' => $utilisateur->idUtilisateur]);

        return response()->json([
            'success' => true,
            'utilisateur' => $utilisateur,
            'bloque' => (int) $utilisateur->points <= 0,
            'historique' => $historique,
        ], 200);
    }
}
