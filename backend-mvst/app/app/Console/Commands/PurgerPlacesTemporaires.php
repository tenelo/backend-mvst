<?php

namespace App\Console\Commands;

use App\Http\Controllers\Legacy\DepartController;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Filet de securite serveur (audit performance, tache 1) : le nettoyage des
 * places temporaires expirees (table PlacesTemporaires / Departs.placesChoisies)
 * ne dependait jusqu'ici QUE d'un appel client a process_places_temporaires.php.
 * Si l'app est tuee avant cet appel, les places restaient verrouillees
 * indefiniment (aucun cron, aucun Schedule:: n'existait pour ce projet -- voir
 * diagnostic precedent). Cette commande appelle la meme logique de purge
 * (DepartController::purgerPlacesTemporaires, extraite sans modification) et
 * est planifiee toutes les 5 minutes dans routes/console.php.
 */
#[Signature('app:purger-places-temporaires')]
#[Description('Purge les places temporaires expirees (filet de securite, meme logique que process_places_temporaires.php)')]
class PurgerPlacesTemporaires extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $resultat = DepartController::purgerPlacesTemporaires();
        } catch (\Exception $e) {
            $this->error('Erreur pendant la purge : '.$e->getMessage());

            return self::FAILURE;
        }

        if (isset($resultat['message'])) {
            $this->info($resultat['message']);
        } else {
            $this->info('Places temporaires purgees : '.($resultat['nettoyees'] ?? 0));
        }

        return self::SUCCESS;
    }
}
