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
        // "pin" nullable, meme logique que sur Utilisateurs : se remplit a la
        // 1re connexion Sanctum de chaque admin, varchar(255) pour un hash.
        // Contrainte UNIQUE sur telephone : verifiee au prealable (0 doublon
        // constate sur les 3 lignes actuelles d'Admins, cf. diagnostic prealable).
        Schema::table('Admins', function (Blueprint $table) {
            $table->string('pin', 255)->nullable();
            $table->unique('telephone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Admins', function (Blueprint $table) {
            $table->dropUnique(['telephone']);
            $table->dropColumn('pin');
        });
    }
};
