<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_placestemp_docid_places');
        DB::statement('CREATE UNIQUE INDEX idx_placestemp_docid_places ON "PlacesTemporaires" ("documentId", places)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_placestemp_docid_places');
        DB::statement('CREATE INDEX idx_placestemp_docid_places ON "PlacesTemporaires" ("documentId", places)');
    }
};
