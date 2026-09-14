<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS "AlertesAffluence" (
                id serial PRIMARY KEY,
                "documentId" character varying(255) NOT NULL,
                type character varying(30) NOT NULL,
                depart character varying(255),
                heure character varying(255),
                vendus integer NOT NULL,
                seuil integer NOT NULL,
                "dateAlerte" timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_alertes_affluence_docid ON "AlertesAffluence" ("documentId")');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "AlertesAffluence"');
    }
};
