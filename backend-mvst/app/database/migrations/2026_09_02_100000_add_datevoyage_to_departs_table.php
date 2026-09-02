<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE "Departs" ADD COLUMN "dateVoyage" date NULL');

        DB::statement(<<<'SQL'
            UPDATE "Departs" d
            SET "dateVoyage" = sub.date_calculee
            FROM (
              WITH parts AS (
                SELECT "documentId", string_to_array("dateDeDepart",'_') AS p FROM "Departs"
              ),
              mapped AS (
                SELECT "documentId",
                  CASE WHEN array_length(p,1)=4 THEN lower(p[3]) END AS mois_txt,
                  CASE WHEN array_length(p,1)=4 THEN p[2] END AS jour_txt,
                  CASE WHEN array_length(p,1)=4 THEN p[4] END AS annee_txt
                FROM parts
              ),
              mois AS (
                SELECT *,
                  CASE mois_txt
                    WHEN 'janvier' THEN 1 WHEN 'février' THEN 2 WHEN 'mars' THEN 3
                    WHEN 'avril' THEN 4 WHEN 'mai' THEN 5 WHEN 'juin' THEN 6
                    WHEN 'juillet' THEN 7 WHEN 'août' THEN 8 WHEN 'septembre' THEN 9
                    WHEN 'octobre' THEN 10 WHEN 'novembre' THEN 11 WHEN 'décembre' THEN 12
                    ELSE NULL END AS mois_num
                FROM mapped
              )
              SELECT "documentId", make_date(annee_txt::int, mois_num, jour_txt::int) AS date_calculee
              FROM mois
              WHERE mois_num IS NOT NULL AND jour_txt ~ '^[0-9]{1,2}$' AND annee_txt ~ '^[0-9]{4}$'
            ) sub
            WHERE d."documentId" = sub."documentId"
        SQL);

        DB::statement('CREATE INDEX idx_departs_datevoyage ON "Departs"("dateVoyage")');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_departs_datevoyage');
        DB::statement('ALTER TABLE "Departs" DROP COLUMN IF EXISTS "dateVoyage"');
    }
};
