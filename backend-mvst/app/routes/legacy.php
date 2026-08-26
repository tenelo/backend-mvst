<?php

use App\Http\Controllers\Legacy\AdminController;
use App\Http\Controllers\Legacy\DiversController;
use App\Http\Controllers\Legacy\GareController;
use App\Http\Controllers\Legacy\ImageController;
use App\Http\Controllers\Legacy\LignePrixController;
use App\Http\Controllers\Legacy\PointsController;
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
