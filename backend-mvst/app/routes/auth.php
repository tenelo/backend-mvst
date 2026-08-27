<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// Auth Sanctum (telephone + PIN, capture au vol). ROUTES OUVERTES pour
// l'instant, aucun middleware de protection -- chargees comme
// routes/legacy.php, hors des groupes "web"/"api" (ni session, ni CSRF, ni
// throttle). Protection a ajouter dans un chantier ulterieur.

Route::post('login', [AuthController::class, 'login']);
Route::post('admin/login', [AuthController::class, 'adminLogin']);
Route::post('logout', [AuthController::class, 'logout']);
Route::get('me', [AuthController::class, 'me']);
Route::post('reset-pin', [AuthController::class, 'resetPin']);
