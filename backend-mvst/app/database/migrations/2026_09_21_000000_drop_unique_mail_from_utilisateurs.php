<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // "mail" n'est plus une identite (le telephone l'est) : le nouveau flux
        // d'inscription OTP envoie mail='' pour chaque compte, ce qui violait
        // cette contrainte UNIQUE des le 2e compte sans email. NOT NULL reste
        // inchange ('' le satisfait deja).
        DB::statement('ALTER TABLE "Utilisateurs" DROP CONSTRAINT IF EXISTS "Utilisateurs_mail_key"');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE "Utilisateurs" ADD CONSTRAINT "Utilisateurs_mail_key" UNIQUE (mail)');
    }
};
