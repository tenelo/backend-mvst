<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS "CarsPositionnes" (
                id serial PRIMARY KEY,
                depart character varying(255) NOT NULL,
                destination character varying(255) NOT NULL,
                date character varying(255) NOT NULL,
                heure character varying(255) NOT NULL,
                type character varying(30) NOT NULL,
                "numeroCar" integer NOT NULL,
                "documentId" character varying(255) NOT NULL,
                "idAdmin" character varying(255),
                "nomAdmin" character varying(255),
                "dateCreation" timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_carspos_unique ON "CarsPositionnes" (depart, destination, date, heure, type, "numeroCar")');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_carspos_docid ON "CarsPositionnes" ("documentId")');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_carspos_creneau ON "CarsPositionnes" (depart, destination, date, heure, type)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "CarsPositionnes"');
    }
};
