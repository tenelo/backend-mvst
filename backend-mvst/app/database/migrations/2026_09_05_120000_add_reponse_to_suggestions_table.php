<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE "Suggestions" ADD COLUMN reponse text NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE "Suggestions" DROP COLUMN IF EXISTS reponse');
    }
};
