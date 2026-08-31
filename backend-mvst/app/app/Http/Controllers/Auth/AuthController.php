<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Utilisateur;
use App\Services\FirebaseAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Auth Sanctum + capture au vol du PIN (telephone + PIN, pas d'email).
 * ROUTES OUVERTES pour l'instant, aucun middleware de protection --
 * voir routes/auth.php. "role" fait foi sur Admins ("profil" ignore,
 * deja documente A_REVOIR.md).
 *
 * Convention du reste du projet reprise ici : HTTP 200 partout, resultat
 * porte par le champ JSON "success".
 *
 * /me et /logout n'etant pas derriere le middleware auth:sanctum
 * (justement parce qu'aucune route n'est protegee a ce stade), le token
 * porteur est resolu ici manuellement via
 * PersonalAccessToken::findToken(), plutot que via $request->user().
 * A revoir quand la protection sera ajoutee.
 */
class AuthController extends Controller
{
    public function __construct(private readonly FirebaseAuthService $firebase) {}

    /**
     * POST /login (Utilisateurs, app client).
     */
    public function login(Request $request): JsonResponse
    {
        return $this->connecter($request, Utilisateur::class, 'mvst-client');
    }

    /**
     * POST /admin/login (Admins, app admin). Meme logique + "role" dans la
     * reponse.
     */
    public function adminLogin(Request $request): JsonResponse
    {
        return $this->connecter($request, Admin::class, 'mvst-admin');
    }

    /**
     * POST /admin/register. Cree/finalise un compte admin cote serveur, sans
     * Firebase : la ligne Admins doit deja exister (creee par un superadmin
     * via ajouterNumeroAdmin.php) et ne pas etre finalisee (nom IS NULL).
     * Flux A (telephone jamais client MVST) : cree aussi la ligne
     * Utilisateurs correspondante, avec un nouvel idUtilisateur (UUID v4).
     * Flux B (telephone deja client MVST) : reutilise l'idUtilisateur
     * existant, pose le pin cote Utilisateurs seulement s'il est encore NULL.
     */
    public function adminRegister(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        foreach (['telephone', 'pin', 'nom', 'prenoms'] as $champ) {
            if (empty($data[$champ])) {
                return response()->json(
                    ['success' => false, 'message' => 'Parametres manquants'],
                    200
                );
            }
        }
        if (! preg_match('/^\d{4}$/', $data['pin'])) {
            return response()->json(
                ['success' => false, 'message' => 'PIN invalide'],
                200
            );
        }

        $telephone = $data['telephone'];
        $nom = $data['nom'];
        $prenoms = $data['prenoms'];
        $residence = $data['residence'] ?? '';
        $mail = $data['mail'] ?? $telephone.'@gmail.com';
        $pinHash = Hash::make($data['pin']);

        // La ligne Admins doit exister (creee par le superadmin via
        // ajouterNumeroAdmin) et ne pas etre deja finalisee.
        $adminRows = DB::select(
            'SELECT id, nom FROM "Admins" WHERE telephone = :telephone',
            ['telephone' => $telephone]
        );
        $admin = $adminRows[0] ?? null;
        if (! $admin) {
            return response()->json(
                ['success' => false, 'message' => 'Numero non autorise'],
                200
            );
        }
        if (! empty($admin->nom)) {
            return response()->json(
                ['success' => false, 'message' => 'Compte deja cree'],
                200
            );
        }

        try {
            $idUtilisateur = DB::transaction(function () use (
                $telephone, $nom, $prenoms, $residence, $mail, $pinHash
            ) {
                // Flux B : le telephone est deja client MVST -> reutiliser
                // son idUtilisateur existant (meme identite dans les 2 tables).
                $userRows = DB::select(
                    'SELECT "idUtilisateur" FROM "Utilisateurs" WHERE telephone = :telephone FOR UPDATE',
                    ['telephone' => $telephone]
                );
                $user = $userRows[0] ?? null;

                if ($user) {
                    $idUtilisateur = $user->idUtilisateur;
                    // Poser le PIN cote Utilisateurs s'il est encore NULL
                    // (coherence avec la capture au vol du login client).
                    DB::update(
                        'UPDATE "Utilisateurs" SET pin = :pin WHERE telephone = :telephone AND pin IS NULL',
                        ['pin' => $pinHash, 'telephone' => $telephone]
                    );
                } else {
                    // Flux A : nouveau -> generer un UUID unique et creer la
                    // ligne Utilisateurs (toutes colonnes NOT NULL remplies).
                    do {
                        $idUtilisateur = (string) Str::uuid();
                        $exists = DB::select(
                            'SELECT 1 FROM "Utilisateurs" WHERE "idUtilisateur" = :id',
                            ['id' => $idUtilisateur]
                        );
                    } while (count($exists) > 0);

                    DB::insert(
                        'INSERT INTO "Utilisateurs"
                          ("idUtilisateur","idAuth",nom,prenoms,residence,telephone,points,mail,pin,"dateDeCreation")
                         VALUES (:idU,:idA,:nom,:prenoms,:residence,:telephone,:points,:mail,:pin,NOW())',
                        [
                            'idU' => $idUtilisateur,
                            'idA' => $idUtilisateur,
                            'nom' => $nom,
                            'prenoms' => $prenoms,
                            'residence' => $residence,
                            'telephone' => $telephone,
                            'points' => 3,
                            'mail' => $mail,
                            'pin' => $pinHash,
                        ]
                    );
                }

                // Finaliser la ligne Admins (toujours, flux A comme B).
                DB::update(
                    'UPDATE "Admins" SET
                        "idUtilisateur" = :idU,
                        "idAuth"        = :idA,
                        nom             = :nom,
                        prenoms         = :prenoms,
                        residence       = :residence,
                        mail            = :mail,
                        pin             = :pin,
                        "dateDeCreation" = NOW()
                     WHERE telephone = :telephone AND nom IS NULL',
                    [
                        'idU' => $idUtilisateur,
                        'idA' => $idUtilisateur,
                        'nom' => $nom,
                        'prenoms' => $prenoms,
                        'residence' => $residence,
                        'mail' => $mail,
                        'pin' => $pinHash,
                        'telephone' => $telephone,
                    ]
                );

                return $idUtilisateur;
            });
        } catch (\Throwable $e) {
            return response()->json(
                ['success' => false, 'message' => 'Erreur creation : '.$e->getMessage()],
                200
            );
        }

        // Recharger l'Admin finalise et emettre un token (meme forme que login).
        $compte = Admin::where('telephone', $telephone)->first();
        $token = $compte->createToken('mvst-admin')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'utilisateur' => $this->formaterCompte($compte),
        ], 200);
    }

    /**
     * Logique commune login/admin-login : validation format, capture au vol
     * du PIN si colonne NULL (verif Firebase puis hachage+stockage), sinon
     * verif Hash::check(), emission d'un token Sanctum.
     *
     * @param  class-string<Utilisateur|Admin>  $modele
     */
    private function connecter(Request $request, string $modele, string $nomToken): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $telephone = $data['telephone'] ?? null;
        $pin = isset($data['pin']) ? (string) $data['pin'] : null;

        if (empty($telephone) || $pin === null || ! preg_match('/^\d{4}$/', $pin)) {
            return response()->json(['success' => false, 'message' => 'telephone et pin (4 chiffres) requis'], 200);
        }

        $compte = $modele::where('telephone', $telephone)->first();

        if (! $compte) {
            return response()->json(['success' => false, 'message' => 'Compte introuvable'], 200);
        }

        if ($compte->pin === null) {
            // Capture au vol : le compte n'est pas encore migre vers Sanctum,
            // on demande a Firebase de confirmer le PIN une derniere fois.
            if (! $this->firebase->verifierTelephonePin($telephone, $pin)) {
                return response()->json(['success' => false, 'message' => 'Identifiants invalides'], 200);
            }

            $compte->pin = Hash::make($pin);
            $compte->save();
        } else {
            if (! Hash::check($pin, $compte->pin)) {
                return response()->json(['success' => false, 'message' => 'Identifiants invalides'], 200);
            }
        }

        $token = $compte->createToken($nomToken)->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'utilisateur' => $this->formaterCompte($compte),
        ], 200);
    }

    /**
     * POST /logout. Revoque le token porte par la requete (Authorization:
     * Bearer ...). Sans middleware auth:sanctum, resolution manuelle du
     * token -- si aucun token valide n'est fourni, ne fait rien
     * (success:false), comme demande ("elle ne fait rien sans token valide").
     */
    public function logout(Request $request): JsonResponse
    {
        $token = PersonalAccessToken::findToken((string) $request->bearerToken());

        if (! $token) {
            return response()->json(['success' => false, 'message' => 'Token invalide ou manquant'], 200);
        }

        $token->delete();

        return response()->json(['success' => true], 200);
    }

    /**
     * GET /me. Renvoie le compte (Utilisateur ou Admin) associe au token
     * porte par la requete. Utile pour tester le flux ; pas protege pour
     * l'instant (n'importe qui peut l'appeler, mais sans token valide il ne
     * renvoie rien d'exploitable).
     */
    public function me(Request $request): JsonResponse
    {
        $token = PersonalAccessToken::findToken((string) $request->bearerToken());

        if (! $token) {
            return response()->json(['success' => false, 'message' => 'Token invalide ou manquant'], 200);
        }

        $compte = $token->tokenable;

        if (! $compte) {
            return response()->json(['success' => false, 'message' => 'Compte introuvable pour ce token'], 200);
        }

        return response()->json(['success' => true, 'utilisateur' => $this->formaterCompte($compte)], 200);
    }

    /**
     * POST /reset-pin. Reutilise l'OTP existant cote app (flux
     * pin_forgot.dart) : Laravel NE VERIFIE PAS l'OTP lui-meme ici, il fait
     * confiance au fait que l'app ne l'appelle qu'apres validation OTP
     * reussie cote Firebase. Point a durcir plus tard (voir A_REVOIR.md).
     *
     * Portee : comptes "Utilisateurs" uniquement (flux app client). Les
     * comptes "Admins" n'ont pas d'equivalent ici -- pas demande, a
     * clarifier si un flux de reinitialisation admin est aussi necessaire.
     */
    public function resetPin(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $telephone = $data['telephone'] ?? null;
        $nouveauPin = isset($data['nouveau_pin']) ? (string) $data['nouveau_pin'] : null;

        if (empty($telephone) || $nouveauPin === null || ! preg_match('/^\d{4}$/', $nouveauPin)) {
            return response()->json(['success' => false, 'message' => 'telephone et nouveau_pin (4 chiffres) requis'], 200);
        }

        $utilisateur = Utilisateur::where('telephone', $telephone)->first();

        if (! $utilisateur) {
            return response()->json(['success' => false, 'message' => 'Compte introuvable'], 200);
        }

        $utilisateur->pin = Hash::make($nouveauPin);
        $utilisateur->save();

        // Deconnecte les autres appareils : tous les tokens existants du
        // compte sont revoques apres un changement de PIN.
        $utilisateur->tokens()->delete();

        return response()->json(['success' => true], 200);
    }

    /**
     * Formate un compte (Utilisateur ou Admin) pour une reponse JSON.
     * Construit a la main (pas de toArray()/toJson() direct sur le
     * modele) : garantit que "pin" ne peut jamais fuiter, meme si $hidden
     * etait un jour retire par erreur des modeles.
     */
    private function formaterCompte(Utilisateur|Admin $compte): array
    {
        if ($compte instanceof Admin) {
            return [
                'id' => $compte->id,
                'idUtilisateur' => $compte->idUtilisateur,
                'nom' => $compte->nom,
                'prenoms' => $compte->prenoms,
                'telephone' => $compte->telephone,
                'gare' => $compte->gare,
                'role' => $compte->role,
            ];
        }

        return [
            'id' => $compte->id,
            'idUtilisateur' => $compte->idUtilisateur,
            'nom' => $compte->nom,
            'prenoms' => $compte->prenoms,
            'telephone' => $compte->telephone,
            'points' => (int) $compte->points,
        ];
    }
}
