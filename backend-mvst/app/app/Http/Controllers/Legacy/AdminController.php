<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * Equivalent de ajouterAdmin.php.
     * POST JSON. idUtilisateur+telephone obligatoires ; idAuth/nom/prenoms/residence/mail optionnels.
     *
     * ATTENTION (comportement PHP repris a l'identique, signale a la demande) :
     * idAuth/nom/prenoms/residence/mail sont TOUJOURS ecrits dans l'UPDATE (defaut ''
     * si absents du payload). Un appel partiel ECRASE donc les valeurs existantes avec
     * des chaines vides. rowCount() n'est jamais verifie : la reponse est success=true
     * meme si aucune ligne ne correspondait au telephone fourni.
     */
    public function ajouterAdmin(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['idUtilisateur']) || ! isset($data['telephone'])) {
                return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 200);
            }

            DB::update(
                'UPDATE "Admins" SET
                    "idUtilisateur" = :idUtilisateur,
                    "idAuth"        = :idAuth,
                    nom             = :nom,
                    prenoms         = :prenoms,
                    residence       = :residence,
                    mail            = :mail,
                    "dateDeCreation" = NOW()
                WHERE telephone = :telephone',
                [
                    'idUtilisateur' => $data['idUtilisateur'],
                    'idAuth' => $data['idAuth'] ?? '',
                    'nom' => $data['nom'] ?? '',
                    'prenoms' => $data['prenoms'] ?? '',
                    'residence' => $data['residence'] ?? '',
                    'mail' => $data['mail'] ?? '',
                    'telephone' => $data['telephone'],
                ]
            );

            return response()->json(['success' => true, 'message' => 'Admin mis à jour'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de ajouterNumeroAdmin.php.
     * POST JSON. telephone+role+gare obligatoires. role limite a admin/superadmin.
     */
    public function ajouterNumero(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['telephone']) || ! isset($data['role']) || ! isset($data['gare'])) {
                return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 200);
            }

            $existe = DB::select('SELECT id FROM "Admins" WHERE telephone = :telephone', ['telephone' => $data['telephone']]);
            if (count($existe) > 0) {
                return response()->json(['success' => false, 'message' => 'Ce numero est deja enregistre'], 200);
            }

            $rolesValides = ['admin', 'superadmin'];
            if (! in_array($data['role'], $rolesValides)) {
                return response()->json(['success' => false, 'message' => 'Role invalide'], 200);
            }

            DB::insert(
                'INSERT INTO "Admins" (telephone, role, gare) VALUES (:telephone, :role, :gare)',
                ['telephone' => $data['telephone'], 'role' => $data['role'], 'gare' => $data['gare']]
            );

            return response()->json(['success' => true, 'message' => 'Numero ajoute avec succes'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de modifierNumeroAdmin.php.
     * POST JSON. id+telephone+role+gare obligatoires. Ne modifie que les comptes pas
     * encore finalises (nom IS NULL).
     *
     * ATTENTION (signale a la demande) : contrairement a ajouterNumeroAdmin.php, ce
     * fichier NE VERIFIE PAS que "role" fait partie de ['admin','superadmin'] — une
     * valeur de role arbitraire est acceptee tant que le compte n'est pas finalise.
     * Incoherence reprise a l'identique, pas corrigee.
     */
    public function modifierNumero(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['id']) || ! isset($data['telephone']) || ! isset($data['role']) || ! isset($data['gare'])) {
                return response()->json(['success' => false, 'message' => 'Paramètres manquants'], 200);
            }

            $doublon = DB::select(
                'SELECT id FROM "Admins" WHERE telephone = :telephone AND id != :id',
                ['telephone' => $data['telephone'], 'id' => $data['id']]
            );
            if (count($doublon) > 0) {
                return response()->json(['success' => false, 'message' => 'Ce numéro est déjà utilisé par un autre admin'], 200);
            }

            $affected = DB::update(
                'UPDATE "Admins" SET telephone = :telephone, role = :role, gare = :gare, profil = :role WHERE id = :id AND nom IS NULL',
                ['telephone' => $data['telephone'], 'role' => $data['role'], 'gare' => $data['gare'], 'id' => $data['id']]
            );

            if ($affected === 0) {
                return response()->json(['success' => false, 'message' => 'Modification impossible. Le compte a déjà été créé.'], 200);
            }

            return response()->json(['success' => true, 'message' => 'Informations mises à jour'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de supprimerNumeroAdmin.php.
     * POST JSON. telephone obligatoire.
     *
     * ATTENTION (signale a la demande) : rowCount() n'est jamais verifie. La reponse
     * est success=true meme si aucun admin ne correspondait au telephone fourni.
     */
    public function supprimerNumero(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['telephone'])) {
                return response()->json(['success' => false, 'message' => 'Paramètre manquant'], 200);
            }

            DB::delete('DELETE FROM "Admins" WHERE telephone = :telephone', ['telephone' => $data['telephone']]);

            return response()->json(['success' => true, 'message' => 'Numéro supprimé'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de verifierAdmin.php.
     * POST JSON. telephone obligatoire.
     *
     * ATTENTION (signale a la demande) : la branche "admin non trouve" ne porte PAS de
     * cle "message", contrairement a la quasi-totalite des autres reponses d'echec du
     * projet ({success:false, existe:false} seulement). Reproduit a l'identique.
     */
    public function verifier(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (! isset($data['telephone'])) {
                return response()->json(['success' => false, 'message' => 'Paramètre manquant'], 200);
            }

            $rows = DB::select(
                'SELECT "idUtilisateur", gare, role, nom FROM "Admins" WHERE telephone = :telephone',
                ['telephone' => $data['telephone']]
            );
            $admin = $rows[0] ?? null;

            if (! $admin) {
                return response()->json(['success' => false, 'existe' => false], 200);
            }

            return response()->json([
                'success' => true,
                'existe' => true,
                'gare' => $admin->gare,
                'uid' => $admin->idUtilisateur,
                'role' => $admin->role,
                'compteExiste' => ! empty($admin->nom),
            ], 200);
        } catch (\PDOException $e) {
            return response()->json(['success' => false, 'message' => 'PDO: '.$e->getMessage()], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur: '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de verifierTelephoneAdmin.php.
     * POST JSON. telephone obligatoire.
     *
     * ATTENTION (signale a la demande) : "success" vaut true dans TOUS les cas normaux,
     * y compris quand le numero n'est pas du tout admin (autorise=false, existe=false).
     * "success" indique seulement que la requete a fonctionne, pas que le numero est
     * autorise : c'est le couple autorise/existe qu'il faut lire cote app.
     */
    public function verifierTelephone(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (! isset($data['telephone'])) {
                return response()->json(['success' => false, 'message' => 'Paramètre manquant'], 200);
            }

            $rows = DB::select('SELECT id, nom FROM "Admins" WHERE telephone = :telephone', ['telephone' => $data['telephone']]);
            $admin = $rows[0] ?? null;

            if (! $admin) {
                return response()->json(['success' => true, 'autorise' => false, 'existe' => false], 200);
            }

            if (! empty($admin->nom)) {
                return response()->json(['success' => true, 'autorise' => true, 'existe' => true], 200);
            }

            return response()->json(['success' => true, 'autorise' => true, 'existe' => false], 200);
        } catch (\PDOException $e) {
            return response()->json(['success' => false, 'message' => 'PDO: '.$e->getMessage()], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur: '.$e->getMessage()], 200);
        }
    }

    /**
     * Equivalent de recupererGare.php.
     * POST JSON. idUtilisateur obligatoire.
     */
    public function recupererGare(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (! isset($data['idUtilisateur'])) {
                return response()->json(['success' => false, 'message' => 'Paramètre manquant'], 200);
            }

            $rows = DB::select(
                'SELECT gare FROM "Admins" WHERE "idUtilisateur" = :idUtilisateur LIMIT 1',
                ['idUtilisateur' => $data['idUtilisateur']]
            );
            $result = $rows[0] ?? null;

            if ($result) {
                return response()->json(['success' => true, 'gare' => $result->gare], 200);
            }

            return response()->json(['success' => false, 'message' => 'Admin non trouvé'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }
}
