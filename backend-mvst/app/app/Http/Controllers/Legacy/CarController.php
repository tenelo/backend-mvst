<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Services\ResolveurAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarController extends Controller
{
    public function __construct(private ResolveurAdminService $resolveur)
    {
    }

    /**
     * Lit une valeur entiere depuis Parametres, avec repli si absente/illisible.
     */
    private function paramInt(string $cle, int $repli): int
    {
        $row = DB::selectOne('SELECT valeur FROM "Parametres" WHERE cle = :cle', ['cle' => $cle]);
        if (! $row || ! is_numeric($row->valeur)) {
            return $repli;
        }

        return (int) $row->valeur;
    }

    /**
     * Seuil de remplissage d'un car pour un type donne = capacite - places reservees.
     * Un car est "plein" quand ses tickets valides atteignent ce seuil.
     */
    private function seuilPlein(string $type): int
    {
        if ($type === 'vip') {
            return $this->paramInt('capacite_vip', 50) - $this->paramInt('places_reservees_vip', 4);
        }

        return $this->paramInt('capacite_standard', 70) - $this->paramInt('places_reservees_standard', 5);
    }

    /**
     * Compte les tickets VALIDES sur un documentId.
     * Pour le car 1 (documentId historique, mixte possible), on filtre sur le type
     * demande via Tickets.typeVoyage (source fiable, contrairement a Departs.typeVoyage).
     * Pour les cars >= 2 (documentId suffixe _std_carN/_vip_carN), le type est garanti
     * par construction : on peut compter tous les tickets valides du documentId, mais
     * on garde le filtre par coherence (sans effet negatif).
     */
    private function compterVendus(string $documentId, string $type): int
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS n FROM "Tickets"
             WHERE "documentId" = :docid AND statut = :statut AND "typeVoyage" = :type',
            ['docid' => $documentId, 'statut' => 'valide', 'type' => $type]
        );

        return (int) ($row->n ?? 0);
    }

    /**
     * Resout le premier car NON plein d'un creneau/type pour un client.
     * Ordre : car 1 (historique) puis car 2, 3... (CarsPositionnes, numeroCar croissant).
     * Public (appele par le client au moment de reserver).
     * POST JSON : { depart, destination, date, heure, type }
     * Reponse : { success, complet, numeroCar?, documentId? }
     */
    public function resoudreCar(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $depart = $data['depart'] ?? null;
            $destination = $data['destination'] ?? null;
            $date = $data['date'] ?? null;
            $heure = $data['heure'] ?? null;
            $type = $data['type'] ?? null;

            if (! $depart || ! $destination || ! $date || ! $heure || ! $type) {
                return response()->json(['success' => false, 'message' => 'depart, destination, date, heure et type requis'], 200);
            }

            if ($type !== 'standard' && $type !== 'vip') {
                return response()->json(['success' => false, 'message' => 'type invalide (standard|vip)'], 200);
            }

            $seuil = $this->seuilPlein($type);
            $documentIdBase = $depart.'-'.$destination.'_'.$date.'_'.$heure.'_h';

            // Car 1 (historique). numeroCar = 1, documentId de base.
            if ($this->compterVendus($documentIdBase, $type) < $seuil) {
                return response()->json([
                    'success' => true,
                    'complet' => false,
                    'numeroCar' => 1,
                    'documentId' => $documentIdBase,
                ], 200);
            }

            // Cars >= 2, positionnes, dans l'ordre du numeroCar.
            $cars = DB::select(
                'SELECT "numeroCar", "documentId"
                 FROM "CarsPositionnes"
                 WHERE depart = :depart AND destination = :destination
                   AND date = :date AND heure = :heure AND type = :type
                 ORDER BY "numeroCar" ASC',
                [
                    'depart' => $depart,
                    'destination' => $destination,
                    'date' => $date,
                    'heure' => $heure,
                    'type' => $type,
                ]
            );

            foreach ($cars as $car) {
                if ($this->compterVendus($car->documentId, $type) < $seuil) {
                    return response()->json([
                        'success' => true,
                        'complet' => false,
                        'numeroCar' => (int) $car->numeroCar,
                        'documentId' => $car->documentId,
                    ], 200);
                }
            }

            // Tous pleins : on renvoie quand meme le DERNIER car (le plus grand numero),
            // ou le car 1 s'il n'y a aucun car positionne, pour que le client ait
            // toujours une grille a ouvrir (il verra les places prises, non cliquables).
            if (count($cars) > 0) {
                $dernier = $cars[count($cars) - 1];
                return response()->json([
                    'success' => true,
                    'complet' => true,
                    'numeroCar' => (int) $dernier->numeroCar,
                    'documentId' => $dernier->documentId,
                    'message' => 'Toutes les places de ce départ sont prises',
                ], 200);
            }

            return response()->json([
                'success' => true,
                'complet' => true,
                'numeroCar' => 1,
                'documentId' => $documentIdBase,
                'message' => 'Toutes les places de ce départ sont prises',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Liste l'etat de tous les cars d'un creneau/type (car 1 inclus).
     * Reserve superadmin OU admin avec peutGererLesNotificationsPush.
     * POST JSON : { depart, destination, date, heure, type }
     */
    public function listerCars(Request $request): JsonResponse
    {
        $admin = $this->resolveur->resoudreAdmin($request);
        if (! $admin || ($admin->role !== 'superadmin' && ! $admin->peutGererLesNotificationsPush)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé'], 200);
        }

        try {
            $data = json_decode($request->getContent(), true);

            $depart = $data['depart'] ?? null;
            if ($admin->role !== 'superadmin') {
                $depart = $admin->gare;
            }
            $destination = $data['destination'] ?? null;
            $date = $data['date'] ?? null;
            $heure = $data['heure'] ?? null;
            $type = $data['type'] ?? null;

            if (! $depart || ! $destination || ! $date || ! $heure || ! $type) {
                return response()->json(['success' => false, 'message' => 'depart, destination, date, heure et type requis'], 200);
            }

            if ($type !== 'standard' && $type !== 'vip') {
                return response()->json(['success' => false, 'message' => 'type invalide (standard|vip)'], 200);
            }

            $seuil = $this->seuilPlein($type);
            $documentIdBase = $depart.'-'.$destination.'_'.$date.'_'.$heure.'_h';
            $capacite = $type === 'vip' ? $this->paramInt('capacite_vip', 50) : $this->paramInt('capacite_standard', 70);

            $liste = [];

            // Car 1 (toujours present dans la liste, meme sans ticket).
            $vendus1 = $this->compterVendus($documentIdBase, $type);
            $liste[] = [
                'numeroCar' => 1,
                'documentId' => $documentIdBase,
                'vendus' => $vendus1,
                'capacite' => $capacite,
                'seuil' => $seuil,
                'plein' => $vendus1 >= $seuil,
            ];

            // Cars >= 2 positionnes.
            $cars = DB::select(
                'SELECT "numeroCar", "documentId"
                 FROM "CarsPositionnes"
                 WHERE depart = :depart AND destination = :destination
                   AND date = :date AND heure = :heure AND type = :type
                 ORDER BY "numeroCar" ASC',
                [
                    'depart' => $depart,
                    'destination' => $destination,
                    'date' => $date,
                    'heure' => $heure,
                    'type' => $type,
                ]
            );

            foreach ($cars as $car) {
                $vendus = $this->compterVendus($car->documentId, $type);
                $liste[] = [
                    'numeroCar' => (int) $car->numeroCar,
                    'documentId' => $car->documentId,
                    'vendus' => $vendus,
                    'capacite' => $capacite,
                    'seuil' => $seuil,
                    'plein' => $vendus >= $seuil,
                ];
            }

            return response()->json(['success' => true, 'type' => $type, 'cars' => $liste], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Liste TOUS les cars positionnes (tous creneaux), filtres par date et/ou type,
     * pour l'onglet admin de supervision. Chaque car est enrichi de vendus/seuil/plein
     * (meme calcul que listerCars) et des colonnes date_iso / ligne.
     * Reserve superadmin OU admin avec peutGererLesNotificationsPush.
     * POST JSON : { date? (yyyy-mm-dd, un seul jour), dateDebut? (yyyy-mm-dd, a partir de), type? (standard|vip) }
     */
    public function listerTousCars(Request $request): JsonResponse
    {
        $admin = $this->resolveur->resoudreAdmin($request);
        if (! $admin || ($admin->role !== 'superadmin' && ! $admin->peutGererLesNotificationsPush)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé'], 200);
        }

        try {
            $data = json_decode($request->getContent(), true) ?? [];

            $date = $data['date'] ?? null;
            $dateDebut = $data['dateDebut'] ?? null;
            $type = $data['type'] ?? null;

            $conditions = [];
            $params = [];

            if ($admin->role !== 'superadmin') {
                $conditions[] = 'depart = :gare';
                $params['gare'] = $admin->gare;
            }

            if ($date !== null && $date !== '') {
                $conditions[] = 'date_iso = :date';
                $params['date'] = $date;
            } elseif ($dateDebut !== null && $dateDebut !== '') {
                $conditions[] = 'date_iso >= :dateDebut';
                $params['dateDebut'] = $dateDebut;
            }

            if ($type === 'standard' || $type === 'vip') {
                $conditions[] = 'type = :type';
                $params['type'] = $type;
            }

            $where = count($conditions) > 0 ? 'WHERE '.implode(' AND ', $conditions) : '';

            $rows = DB::select(
                'SELECT id, depart, destination, date, heure, type, "numeroCar",
                        "documentId", "idAdmin", "nomAdmin", "dateCreation", date_iso, ligne
                 FROM "CarsPositionnes"
                 '.$where.'
                 ORDER BY date_iso ASC NULLS LAST, heure ASC, type ASC, "numeroCar" ASC',
                $params
            );

            $cars = [];
            foreach ($rows as $row) {
                $seuil = $this->seuilPlein($row->type);
                $capacite = $row->type === 'vip'
                    ? $this->paramInt('capacite_vip', 50)
                    : $this->paramInt('capacite_standard', 70);
                $vendus = $this->compterVendus($row->documentId, $row->type);

                $cars[] = [
                    'id' => (int) $row->id,
                    'depart' => $row->depart,
                    'destination' => $row->destination,
                    'date' => $row->date,
                    'heure' => $row->heure,
                    'type' => $row->type,
                    'numeroCar' => (int) $row->numeroCar,
                    'documentId' => $row->documentId,
                    'vendus' => $vendus,
                    'capacite' => $capacite,
                    'seuil' => $seuil,
                    'plein' => $vendus >= $seuil,
                    'idAdmin' => $row->idAdmin,
                    'nomAdmin' => $row->nomAdmin,
                    'dateCreation' => $row->dateCreation,
                    'date_iso' => $row->date_iso,
                    'ligne' => $row->ligne,
                ];
            }

            return response()->json([
                'success' => true,
                'total' => count($cars),
                'cars' => $cars,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Retire un car positionne (uniquement un car >= 2, présent dans CarsPositionnes).
     * PROTECTION STRICTE : refuse le retrait si au moins un Ticket valide existe sur
     * le documentId du car (meme logique anti-billet-fantome que le chantier choix de place).
     * Le car 1 n'est jamais dans CarsPositionnes, donc non concerne.
     * Reserve superadmin OU admin avec peutGererLesNotificationsPush.
     * POST JSON : { id }  (l'id de la ligne CarsPositionnes)
     */
    public function retirerCar(Request $request): JsonResponse
    {
        $admin = $this->resolveur->resoudreAdmin($request);
        if (! $admin || ($admin->role !== 'superadmin' && ! $admin->peutPositionnerCar)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé'], 200);
        }

        try {
            $data = json_decode($request->getContent(), true) ?? [];
            $id = $data['id'] ?? null;

            if ($id === null || ! is_numeric($id)) {
                return response()->json(['success' => false, 'message' => 'id du car requis'], 200);
            }

            // Recupere le car cible.
            $car = DB::selectOne(
                'SELECT id, "documentId", type, depart, "idAdmin" FROM "CarsPositionnes" WHERE id = :id',
                ['id' => (int) $id]
            );

            if (! $car) {
                return response()->json(['success' => false, 'message' => 'Car introuvable'], 200);
            }

            if ($admin->role !== 'superadmin' && $car->depart !== $admin->gare) {
                return response()->json(['success' => false, 'message' => 'Car hors de votre gare'], 200);
            }

            if ($admin->role !== 'superadmin' && (int) $car->idAdmin !== $admin->id) {
                return response()->json(['success' => false, 'message' => 'Vous ne pouvez retirer qu\'un car que vous avez vous-même positionné'], 200);
            }

            // PROTECTION : refuse si des billets valides existent sur ce car.
            $vendus = $this->compterVendus($car->documentId, $car->type);
            if ($vendus > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Retrait impossible : '.$vendus.' billet(s) déjà vendu(s) sur ce car.',
                ], 200);
            }

            $supprimees = DB::delete(
                'DELETE FROM "CarsPositionnes" WHERE id = :id',
                ['id' => (int) $id]
            );

            if ($supprimees < 1) {
                return response()->json(['success' => false, 'message' => 'Aucun car supprimé.'], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'Car retiré.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    public function positionnerCar(Request $request): JsonResponse
    {
        $admin = $this->resolveur->resoudreAdmin($request);
        if (! $admin || ($admin->role !== 'superadmin' && ! $admin->peutPositionnerCar)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé'], 200);
        }

        try {
            $data = json_decode($request->getContent(), true);

            $depart = $data['depart'] ?? null;
            if ($admin->role !== 'superadmin') {
                $depart = $admin->gare;
            }
            $destination = $data['destination'] ?? null;
            $date = $data['date'] ?? null;
            $heure = $data['heure'] ?? null;
            $type = $data['type'] ?? null;
            $dateIso = $data['date_iso'] ?? null;
            $ligne = $data['ligne'] ?? null;
            if ($ligne === null || $ligne === '') {
                $ligne = $depart.' '.$destination;
            }

            if (! $depart || ! $destination || ! $date || ! $heure || ! $type) {
                return response()->json(['success' => false, 'message' => 'depart, destination, date, heure et type requis'], 200);
            }

            if ($type !== 'standard' && $type !== 'vip') {
                return response()->json(['success' => false, 'message' => 'type invalide (standard|vip)'], 200);
            }

            $suffixeType = $type === 'vip' ? 'vip' : 'std';
            $documentIdBase = $depart.'-'.$destination.'_'.$date.'_'.$heure.'_h';

            $idAdmin = $admin->id ?? ($admin->idAdmin ?? null);
            $idAdmin = $idAdmin !== null ? (string) $idAdmin : null;
            $nomAdmin = $admin->nom ?? ($admin->name ?? null);

            $tentatives = 0;
            do {
                $tentatives++;
                $collision = false;
                try {
                    $ligneInseree = DB::selectOne(
                        'INSERT INTO "CarsPositionnes"
                            (depart, destination, date, heure, type, "numeroCar", "documentId", "idAdmin", "nomAdmin", date_iso, ligne)
                         SELECT :depart, :destination, :date, :heure, :type,
                                COALESCE(MAX("numeroCar"), 1) + 1,
                                :docbase || \'_\' || :suffixe || \'_car\' || (COALESCE(MAX("numeroCar"), 1) + 1),
                                :idAdmin, :nomAdmin, :dateIso, :ligne
                         FROM "CarsPositionnes"
                         WHERE depart = :depart2 AND destination = :destination2
                           AND date = :date2 AND heure = :heure2 AND type = :type2
                         RETURNING "numeroCar", "documentId"',
                        [
                            'depart' => $depart,
                            'destination' => $destination,
                            'date' => $date,
                            'heure' => $heure,
                            'type' => $type,
                            'docbase' => $documentIdBase,
                            'suffixe' => $suffixeType,
                            'idAdmin' => $idAdmin,
                            'nomAdmin' => $nomAdmin,
                            'dateIso' => $dateIso,
                            'ligne' => $ligne,
                            'depart2' => $depart,
                            'destination2' => $destination,
                            'date2' => $date,
                            'heure2' => $heure,
                            'type2' => $type,
                        ]
                    );
                } catch (\Illuminate\Database\QueryException $e) {
                    if ($e->getCode() === '23505' && $tentatives < 3) {
                        $collision = true;
                    } else {
                        throw $e;
                    }
                }
            } while ($collision);

            return response()->json([
                'success' => true,
                'message' => 'Car n°'.$ligneInseree->numeroCar.' de '.$heure.' positionné',
                'numeroCar' => (int) $ligneInseree->numeroCar,
                'documentId' => $ligneInseree->documentId,
                'type' => $type,
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }

    /**
     * Grille de suivi d'affluence (superadmin/admin gare) : tous les cars actifs
     * a partir d'aujourd'hui. Car 1 deduits des Tickets valides (groupes par
     * depart/destination/date/heure/type), + cars positionnes (CarsPositionnes).
     * Une ligne par car, avec vendus/seuil/taux. Filtre datePourCalcule >= CURRENT_DATE.
     * POST JSON : {} (aucun parametre requis). Reserve superadmin OU admin gare.
     */
    public function suiviAffluence(Request $request): JsonResponse
    {
        $admin = $this->resolveur->resoudreAdmin($request);
        if (! $admin || ($admin->role !== 'superadmin' && ! $admin->peutGererLesNotificationsPush)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé'], 200);
        }

        try {
            $estAdminGare = $admin->role !== 'superadmin';
            $gareAdmin = $admin->gare ?? null;

            // 1) CAR 1 actifs : agreges depuis Tickets, a partir d'aujourd'hui.
            $sqlCar1 = 'SELECT "documentId", depart, destination, date, heure, "typeVoyage" AS type,
                               MIN("datePourCalcule") AS date_iso, COUNT(*) AS vendus
                        FROM "Tickets"
                        WHERE statut = \'valide\' AND "datePourCalcule" >= CURRENT_DATE
                          AND "documentId" !~ \'_car[0-9]+$\'';
            $paramsCar1 = [];
            if ($estAdminGare) {
                $sqlCar1 .= ' AND depart = :gare';
                $paramsCar1['gare'] = $gareAdmin;
            }
            $sqlCar1 .= ' GROUP BY "documentId", depart, destination, date, heure, "typeVoyage"';
            $car1Rows = DB::select($sqlCar1, $paramsCar1);

            // 2) CARS POSITIONNES (>=2) a partir d'aujourd'hui.
            $sqlPos = 'SELECT "documentId", depart, destination, date, heure, type, "numeroCar",
                              ligne, date_iso
                       FROM "CarsPositionnes"
                       WHERE date_iso >= CURRENT_DATE';
            $paramsPos = [];
            if ($estAdminGare) {
                $sqlPos .= ' AND depart = :gare';
                $paramsPos['gare'] = $gareAdmin;
            }
            $posRows = DB::select($sqlPos, $paramsPos);

            $lignes = [];

            foreach ($car1Rows as $r) {
                $seuil = $this->seuilPlein($r->type);
                $cap = $r->type === 'vip' ? $this->paramInt('capacite_vip', 50) : $this->paramInt('capacite_standard', 70);
                $vendus = (int) $r->vendus;
                $lignes[] = [
                    'gare' => $r->depart,
                    'depart' => $r->depart,
                    'destination' => $r->destination,
                    'ligne' => trim($r->depart.' '.$r->destination),
                    'date' => $r->date,
                    'date_iso' => $r->date_iso,
                    'heure' => $r->heure,
                    'type' => $r->type,
                    'numeroCar' => 1,
                    'vendus' => $vendus,
                    'capacite' => $cap,
                    'seuil' => $seuil,
                    'tauxPct' => $seuil > 0 ? (int) round($vendus * 100 / $seuil) : 0,
                    'plein' => $vendus >= $seuil,
                ];
            }

            foreach ($posRows as $r) {
                $seuil = $this->seuilPlein($r->type);
                $cap = $r->type === 'vip' ? $this->paramInt('capacite_vip', 50) : $this->paramInt('capacite_standard', 70);
                $vendus = $this->compterVendus($r->documentId, $r->type);
                $lignes[] = [
                    'gare' => $r->depart,
                    'depart' => $r->depart,
                    'destination' => $r->destination,
                    'ligne' => ($r->ligne && trim($r->ligne) !== '') ? $r->ligne : trim($r->depart.' '.$r->destination),
                    'date' => $r->date,
                    'date_iso' => $r->date_iso,
                    'heure' => $r->heure,
                    'type' => $r->type,
                    'numeroCar' => (int) $r->numeroCar,
                    'vendus' => $vendus,
                    'capacite' => $cap,
                    'seuil' => $seuil,
                    'tauxPct' => $seuil > 0 ? (int) round($vendus * 100 / $seuil) : 0,
                    'plein' => $vendus >= $seuil,
                ];
            }

            // Tri : gare, puis date, puis heure, puis numeroCar.
            usort($lignes, function ($a, $b) {
                return [$a['gare'], $a['date_iso'], $a['heure'], $a['numeroCar']]
                   <=> [$b['gare'], $b['date_iso'], $b['heure'], $b['numeroCar']];
            });

            return response()->json(['success' => true, 'total' => count($lignes), 'lignes' => $lignes], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erreur : '.$e->getMessage()], 200);
        }
    }
}
