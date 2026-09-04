<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE "Admins" ADD COLUMN "peutGererSuggestions" boolean NOT NULL DEFAULT false');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE "Admins" DROP COLUMN IF EXISTS "peutGererSuggestions"');
    }
};
