<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verification ponctuelle d'un couple (telephone, PIN) aupres de Firebase,
 * pour la "capture au vol" du PIN : tant que la colonne "pin" est NULL sur
 * un compte (Utilisateurs/Admins), on demande a Firebase de confirmer que
 * ce PIN est bien le bon avant de le hacher et de le stocker localement.
 *
 * Reprend exactement le format de compte deja utilise par
 * mvst-socket/handlers/reinitialiser_pin.js (jamais modifie par ce
 * chantier) : email = "<telephone>@gmail.com", password = "<pin>mv".
 *
 * Voie choisie (diagnostic prealable) : appel HTTP direct a l'API REST
 * Firebase Auth (identitytoolkit), PAS de SDK Admin. Necessite uniquement
 * la cle Web API Firebase (config('services.firebase.web_api_key'), voir
 * .env FIREBASE_WEB_API_KEY -- valeur fournie par l'utilisateur, jamais
 * inventee ni commitee ici).
 */
class FirebaseAuthService
{
    private const ENDPOINT = 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword';

    private const TIMEOUT_SECONDES = 8;

    /**
     * true si Firebase confirme le couple telephone/pin (HTTP 200), false
     * dans tous les autres cas : mauvais PIN, compte Firebase inexistant,
     * cle Web API absente, erreur reseau/timeout. Ne leve jamais
     * d'exception vers l'appelant -- un probleme de verification doit se
     * traduire par un refus de connexion, pas par une erreur serveur.
     *
     * Ne journalise jamais le PIN ni le mot de passe Firebase construit a
     * partir de lui (conformement a la consigne "ne jamais logger le pin
     * en clair").
     */
    public function verifierTelephonePin(string $telephone, string $pin): bool
    {
        $webApiKey = config('services.firebase.web_api_key');

        if (empty($webApiKey)) {
            Log::warning('FirebaseAuthService: FIREBASE_WEB_API_KEY absente, verification impossible.');

            return false;
        }

        $email = $telephone.'@gmail.com';
        $password = $pin.'mv';

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDES)->post(
                self::ENDPOINT.'?key='.rawurlencode($webApiKey),
                [
                    'email' => $email,
                    'password' => $password,
                    'returnSecureToken' => true,
                ]
            );

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('FirebaseAuthService: echec appel signInWithPassword ('.$e->getMessage().')');

            return false;
        }
    }
}
