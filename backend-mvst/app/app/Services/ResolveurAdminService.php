<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Resolution de l'Admin courant depuis le bearer token, pour proteger les
 * endpoints de gestion des admins (AdminController) sans dupliquer la
 * logique deja ecrite dans TicketController::resoudreAdmin() (laissee telle
 * quelle, pas refactoree, pour ne pas risquer le dashboard deja en prod).
 *
 * Convention projet reprise ici : ne leve jamais d'exception ni de reponse
 * HTTP -- retourne simplement null en cas d'echec, a l'appelant de
 * construire le {"success":false,...} habituel en HTTP 200.
 */
class ResolveurAdminService
{
    /**
     * L'Admin porte par le bearer token, ou null si le token est
     * absent/invalide, ou s'il pointe sur un compte Utilisateur (client)
     * plutot qu'un Admin.
     */
    public function resoudreAdmin(Request $request): ?Admin
    {
        $token = PersonalAccessToken::findToken((string) $request->bearerToken());
        if (! $token) {
            return null;
        }

        $compte = $token->tokenable;
        if (! ($compte instanceof Admin)) {
            return null;
        }

        return $compte;
    }

    /**
     * L'Admin porte par le bearer token, uniquement si son role vaut
     * 'superadmin' ; null dans tous les autres cas (token absent/invalide,
     * compte non-Admin, ou Admin de role 'admin' standard).
     */
    public function exigerSuperadmin(Request $request): ?Admin
    {
        $admin = $this->resoudreAdmin($request);
        if (! $admin || $admin->role !== 'superadmin') {
            return null;
        }

        return $admin;
    }
}
