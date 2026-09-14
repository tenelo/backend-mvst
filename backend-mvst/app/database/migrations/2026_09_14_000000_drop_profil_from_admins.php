<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE "Admins" DROP COLUMN IF EXISTS "profil"');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE "Admins" ADD COLUMN IF NOT EXISTS "profil" VARCHAR(255)');
        DB::statement('UPDATE "Admins" SET "profil" = "role" WHERE "profil" IS NULL');
    }
};
