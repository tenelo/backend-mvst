<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_tickets_doc_place_valide');
        DB::statement('CREATE UNIQUE INDEX idx_tickets_doc_place_valide ON "Tickets" ("documentId", place) WHERE statut = \'valide\'');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_tickets_doc_place_valide');
        DB::statement('CREATE INDEX idx_tickets_doc_place_valide ON "Tickets" ("documentId", place) WHERE statut = \'valide\'');
    }
};
