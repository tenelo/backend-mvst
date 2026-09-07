<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS "NotificationsDiffusion" (
                id serial PRIMARY KEY,
                "idAdmin" character varying(255),
                "nomAdmin" character varying(255),
                cible character varying(30) NOT NULL,
                gare character varying(255),
                "idUtilisateurs" text,
                titre character varying(255) NOT NULL,
                message text NOT NULL,
                destinataires integer NOT NULL DEFAULT 0,
                envoyes integer NOT NULL DEFAULT 0,
                "dateEnvoi" timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_notifdiff_admin ON "NotificationsDiffusion" ("idAdmin")');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_notifdiff_date ON "NotificationsDiffusion" ("dateEnvoi" DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "NotificationsDiffusion"');
    }
};
