<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Filet de securite serveur (audit performance, tache 1) : purge les places
// temporaires expirees toutes les 5 minutes, independamment de tout appel
// client a process_places_temporaires.php. Necessite que le cron systeme
// "* * * * * php artisan schedule:run" tourne sur le VPS pour ce projet --
// voir le rapport de la tache 1 pour l'etat constate de ce cron.
//
// PAS de ->withoutOverlapping() : ce garde-fou s'appuie sur un verrou en
// cache (table "cache_locks" avec CACHE_STORE=database) qui n'existe pas sur
// cette base -- absente volontairement, les migrations du squelette Laravel
// n'ont jamais ete executees ici (cf. taches Sanctum precedentes). Confirme
// par test : schedule:list plante sur "relation cache_locks does not exist"
// des qu'un ->withoutOverlapping() est present. Risque de chevauchement jugé
// negligeable : la purge traite quelques lignes en quelques millisecondes,
// toutes les 5 minutes.
Schedule::command('app:purger-places-temporaires')
    ->everyFiveMinutes();
