<?php

use App\Http\Controllers\Legacy\AdminController;
use App\Http\Controllers\Legacy\DepartController;
use App\Http\Controllers\Legacy\DiversController;
use App\Http\Controllers\Legacy\GareController;
use App\Http\Controllers\Legacy\ImageController;
use App\Http\Controllers\Legacy\LignePrixController;
use App\Http\Controllers\Legacy\PointsController;
use App\Http\Controllers\Legacy\SuggestionController;
use App\Http\Controllers\Legacy\TicketController;
use App\Http\Controllers\Legacy\UtilisateurController;
use Illuminate\Support\Facades\Route;

// Routes reproduisant a l'identique les endpoints de php-mvst/app/.
// Chaque route porte le nom exact du fichier PHP d'origine (extension .php
// comprise) pour que le contrat d'URL reste inchange pour les apps Flutter.
// Regroupement par lot, dans l'ordre de migration convenu.

// ─── Lot pilote (valide) ────────────────────────────────────────────────────

// Admins
Route::get('listeAdmins.php', [AdminController::class, 'liste']);

// Images
Route::get('getImages.php', [ImageController::class, 'getImages']);

// Divers (config)
Route::post('recuperationHeure.php', [DiversController::class, 'recuperationHeure']);

// ─── Lot 1 : Admins ─────────────────────────────────────────────────────────

Route::post('ajouterAdmin.php', [AdminController::class, 'ajouterAdmin']);
Route::post('ajouterNumeroAdmin.php', [AdminController::class, 'ajouterNumero']);
Route::post('modifierNumeroAdmin.php', [AdminController::class, 'modifierNumero']);
Route::post('supprimerNumeroAdmin.php', [AdminController::class, 'supprimerNumero']);
Route::post('verifierAdmin.php', [AdminController::class, 'verifier']);
Route::post('verifierTelephoneAdmin.php', [AdminController::class, 'verifierTelephone']);
Route::post('recupererGare.php', [AdminController::class, 'recupererGare']);

// ─── Lot 2 : Utilisateurs ───────────────────────────────────────────────────

Route::get('get_utilisateur.php', [UtilisateurController::class, 'get']);
Route::post('verifierUtilisateur.php', [UtilisateurController::class, 'verifier']);
Route::post('verifierTelephone.php', [UtilisateurController::class, 'verifierTelephone']);
Route::post('insert_utilisateur.php', [UtilisateurController::class, 'insert']);
Route::post('update_utilisateur.php', [UtilisateurController::class, 'update']);

// ─── Lot 3 : Points ─────────────────────────────────────────────────────────

Route::post('decrementerPoints.php', [PointsController::class, 'decrementer']);
Route::post('reinitialiserPoints.php', [PointsController::class, 'reinitialiserPoints']);

// ─── Lot 4 : Gares / Divers config ──────────────────────────────────────────

Route::match(['get', 'post'], 'gares.php', [GareController::class, 'gares']);
Route::match(['get', 'post'], 'infosGares.php', [GareController::class, 'infosGares']);
Route::get('tarifsAxes_et_infos_gare.php', [GareController::class, 'tarifsAxesEtInfosGare']);
Route::match(['get', 'post'], 'heuresDepart.php', [DiversController::class, 'heuresDepart']);
Route::any('datesDisponibles.php', [DiversController::class, 'datesDisponibles']);

// ─── Lot 5 : Lignes / Prix ──────────────────────────────────────────────────

Route::any('api_lignes.php', [LignePrixController::class, 'apiLignes']);
Route::match(['get', 'post'], 'prixTickets.php', [LignePrixController::class, 'prixTickets']);
Route::post('getPrixDesTickets.php', [LignePrixController::class, 'getPrixDesTickets']);

// ─── Lot 6 : Images / Suggestions ───────────────────────────────────────────

// TrimStrings/ConvertEmptyStringsToNull exclus pour cette route dans
// bootstrap/app.php (seul endpoint du projet lu en multipart via $request->input()).
Route::match(['get', 'post'], 'gestionImages.php', [ImageController::class, 'gestionImages']);
Route::any('api_suggestions.php', [SuggestionController::class, 'apiSuggestions']);

// ─── Lot 7 : Departs ─────────────────────────────────────────────────────────

Route::post('departsParGare.php', [DepartController::class, 'departsParGare']);
Route::match(['get', 'post'], 'process_places_temporaires.php', [DepartController::class, 'processPlacesTemporaires']);
Route::match(['get', 'post'], 'process_departs_vides.php', [DepartController::class, 'processDepartsVides']);
Route::get('config_nettoyage_departs.php', [DepartController::class, 'configNettoyageDeparts']);

// ─── Lot 8a : Tickets — lectures ────────────────────────────────────────────

Route::post('etatTicket.php', [TicketController::class, 'etatTicket']);
Route::post('mesTicketsScannes.php', [TicketController::class, 'mesTicketsScannes']);
Route::post('superadmin_mesTicketsScannes.php', [TicketController::class, 'superadminMesTicketsScannes']);
Route::post('ticketsAscanner.php', [TicketController::class, 'ticketsAscanner']);
Route::get('superadmin_ticketsAscanner.php', [TicketController::class, 'superadminTicketsAscanner']);
Route::post('ticketsDuJour.php', [TicketController::class, 'ticketsDuJour']);
Route::post('ticketsDuJourScannes.php', [TicketController::class, 'ticketsDuJourScannes']);
Route::post('tableauAdmin.php', [TicketController::class, 'tableauAdmin']);
Route::post('placesAssises.php', [TicketController::class, 'placesAssises']);

// ─── Lot 8b : Tickets — pagination / ecritures ──────────────────────────────

Route::post('recuperation_mes_tickets.php', [TicketController::class, 'recuperationMesTickets']);
Route::post('recuperation_mes_tickets_tableau.php', [TicketController::class, 'recuperationMesTicketsTableau']);
Route::post('graphiques.php', [TicketController::class, 'graphiques']);
Route::post('ajouterTickets.php', [TicketController::class, 'ajouterTickets']);
Route::post('misAjourEtatScanne.php', [TicketController::class, 'misAjourEtatScanne']);
Route::any('suppressionTickets.php', [TicketController::class, 'suppressionTickets']);

// ─── Nouvel endpoint : synthese du jour par gare (pas une migration PHP) ────

Route::post('synthese_gare.php', [TicketController::class, 'syntheseGare']);
Route::post('vue_par_depart.php', [TicketController::class, 'vueParDepart']);
Route::post('tendances_gare.php', [TicketController::class, 'tendancesGare']);
