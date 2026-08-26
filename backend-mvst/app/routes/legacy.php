<?php

use App\Http\Controllers\Legacy\AdminController;
use App\Http\Controllers\Legacy\DiversController;
use App\Http\Controllers\Legacy\ImageController;
use Illuminate\Support\Facades\Route;

// Routes reproduisant a l'identique les endpoints de php-mvst/app/.
// Chaque route porte le nom exact du fichier PHP d'origine (extension .php
// comprise) pour que le contrat d'URL reste inchange pour les apps Flutter.
// Regroupement par lot, dans l'ordre de migration convenu.

// ─── Lot pilote (validation du format) ─────────────────────────────────────

// Admins
Route::get('listeAdmins.php', [AdminController::class, 'liste']);

// Images
Route::get('getImages.php', [ImageController::class, 'getImages']);

// Divers (config)
Route::post('recuperationHeure.php', [DiversController::class, 'recuperationHeure']);
