<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE "Parametres" (cle VARCHAR(255) PRIMARY KEY, valeur VARCHAR(255) NOT NULL)');
        DB::statement("INSERT INTO \"Parametres\" (cle, valeur) VALUES ('purge_delai_minutes', '5')");
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "Parametres"');
    }
};
