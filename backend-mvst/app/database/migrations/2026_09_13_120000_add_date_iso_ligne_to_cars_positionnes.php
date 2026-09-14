<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Colonnes ajoutees a l'origine hors migration (SQL direct) : cette
        // migration les acte pour qu'un environnement neuf les reproduise.
        // IF NOT EXISTS partout => sans effet sur une base qui les a deja.
        DB::statement('ALTER TABLE "CarsPositionnes" ADD COLUMN IF NOT EXISTS date_iso date');
        DB::statement('ALTER TABLE "CarsPositionnes" ADD COLUMN IF NOT EXISTS ligne character varying(255)');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_carsposi_date_iso ON "CarsPositionnes" (date_iso)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_carsposi_documentid ON "CarsPositionnes" ("documentId")');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_carsposi_id ON "CarsPositionnes" (id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_carsposi_ligne ON "CarsPositionnes" (ligne)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_carsposi_date_iso');
        DB::statement('DROP INDEX IF EXISTS idx_carsposi_documentid');
        DB::statement('DROP INDEX IF EXISTS idx_carsposi_id');
        DB::statement('DROP INDEX IF EXISTS idx_carsposi_ligne');
        DB::statement('ALTER TABLE "CarsPositionnes" DROP COLUMN IF EXISTS ligne');
        DB::statement('ALTER TABLE "CarsPositionnes" DROP COLUMN IF EXISTS date_iso');
    }
};
