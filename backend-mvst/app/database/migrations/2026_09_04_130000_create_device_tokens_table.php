<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE "DeviceTokens" (
                id serial PRIMARY KEY,
                type_compte varchar(20) NOT NULL,
                id_compte varchar(50) NOT NULL,
                token text NOT NULL,
                plateforme varchar(20),
                date_creation timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
                date_derniere_utilisation timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX idx_devicetokens_token ON "DeviceTokens"(token)');
        DB::statement('CREATE INDEX idx_devicetokens_compte ON "DeviceTokens"(type_compte, id_compte)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS "DeviceTokens"');
    }
};
