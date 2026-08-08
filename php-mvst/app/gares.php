<?php
// gares.php
// GET    → liste toutes les gares
// POST   action=ajouter   { "gare": "Ferké" }
// POST   action=modifier  { "id": 1, "gare": "Ferké" }
// POST   action=supprimer { "id": 1 }

header("Content-Type: application/json");
require_once 'config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET : lister toutes les gares ──────────────────────────────────────
    if ($method === 'GET') {
        $stmt = $conn->prepare('SELECT * FROM "Gares" ORDER BY id ASC');
        $stmt->execute();
        $gares = $stmt->fetchAll();
        echo json_encode(["success" => true, "gares" => $gares]);
        exit();
    }

    // ── POST ───────────────────────────────────────────────────────────────
    $data   = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    if ($action === 'ajouter') {
        $stmt = $conn->prepare('INSERT INTO "Gares" (gare) VALUES (:gare)');
        $stmt->execute([':gare' => $data['gare']]);
        echo json_encode(["success" => true, "message" => "Gare ajoutée"]);
        exit();
    }

    if ($action === 'modifier') {
        $stmt = $conn->prepare('UPDATE "Gares" SET gare = :gare WHERE id = :id');
        $stmt->execute([':gare' => $data['gare'], ':id' => (int)$data['id']]);
        echo json_encode(["success" => true, "message" => "Gare modifiée"]);
        exit();
    }

    if ($action === 'supprimer') {
        $stmt = $conn->prepare('DELETE FROM "Gares" WHERE id = :id');
        $stmt->execute([':id' => (int)$data['id']]);
        echo json_encode(["success" => true, "message" => "Gare supprimée"]);
        exit();
    }

    echo json_encode(["success" => false, "message" => "Action non reconnue"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
