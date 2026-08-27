<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // "pin" nullable : se remplit a la 1re connexion Sanctum de chaque
        // utilisateur, ne touche a aucune ligne existante immediatement.
        // varchar(255) pour accueillir un hash (bcrypt/argon), pas le PIN en clair.
        Schema::table('Utilisateurs', function (Blueprint $table) {
            $table->string('pin', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Utilisateurs', function (Blueprint $table) {
            $table->dropColumn('pin');
        });
    }
};
