<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Services\ResolveurAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private ResolveurAdminService $resolveur)
    {
    }

    /**
     * Envoi d'une notification de diffusion ciblee.
     * Reserve superadmin OU admin avec peutGererLesNotificationsPush = true.
     * Relaie vers le socket (mvst-socket /notif-diffusion/envoyer) et renvoie sa reponse.
     * POST JSON : { cible, titre, message, gare?, idUtilisateurs? }
     */
    public function envoyerDiffusion(Request $request): JsonResponse
    {
        $admin = $this->resolveur->resoudreAdmin($request);
        if (! $admin || ($admin->role !== 'superadmin' && ! $admin->peutGererLesNotificationsPush)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé'], 200);
        }

        try {
            $data = json_decode($request->getContent(), true);

            $cible = $data['cible'] ?? null;
            $titre = $data['titre'] ?? null;
            $message = $data['message'] ?? null;

            if (! $cible || ! $titre || ! $message) {
                return response()->json(['success' => false, 'message' => 'cible, titre et message requis'], 200);
            }

            $payload = [
                'cible' => $cible,
                'titre' => $titre,
                'message' => $message,
            ];
            if (isset($data['gare'])) {
                $payload['gare'] = $data['gare'];
            }
            if (isset($data['idUtilisateurs'])) {
                $payload['idUtilisateurs'] = $data['idUtilisateurs'];
            }

            $contexte = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode($payload),
                    'timeout' => 15,
                ],
            ]);

            $reponse = @file_get_contents('http://socket-mvst:3000/notif-diffusion/envoyer', false, $contexte);

            if ($reponse === false) {
                return response()->json(['success' => false, 'message' => 'Service de notification injoignable'], 200);
            }

            $decoded = json_decode($reponse, true);
            if (! is_array($decoded)) {
                return response()->json(['success' => false, 'message' => 'Réponse du service invalide'], 200);
            }

            return response()->json($decoded, 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }
}
