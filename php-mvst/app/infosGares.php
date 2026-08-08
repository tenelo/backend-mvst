<?php
// infosGares.php
// GET    → liste toutes les infos gares
// POST   action=ajouter   { "ville": "...", "description": "...", "telephone": "..." }
// POST   action=modifier  { "id": 1, "ville": "...", "description": "...", "telephone": "..." }
// POST   action=supprimer { "id": 1 }

header("Content-Type: application/json");
require_once 'config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET ────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        $stmt = $conn->prepare('SELECT * FROM "InfosGares" ORDER BY id ASC');
        $stmt->execute();
        $infos = $stmt->fetchAll();
        echo json_encode(["success" => true, "infos" => $infos]);
        exit();
    }

    // ── POST ───────────────────────────────────────────────────────────────
    $data   = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    if ($action === 'ajouter') {
        $stmt = $conn->prepare(
            'INSERT INTO "InfosGares" (ville, description, telephone) 
             VALUES (:ville, :description, :telephone)'
        );
        $stmt->execute([
            ':ville'       => $data['ville'],
            ':description' => $data['description'],
            ':telephone'   => $data['telephone'],
        ]);
        echo json_encode(["success" => true, "message" => "Informations ajoutées"]);
        exit();
    }

    if ($action === 'modifier') {
        $stmt = $conn->prepare(
            'UPDATE "InfosGares" 
             SET ville = :ville, description = :description, telephone = :telephone 
             WHERE id = :id'
        );
        $stmt->execute([
            ':ville'       => $data['ville'],
            ':description' => $data['description'],
            ':telephone'   => $data['telephone'],
            ':id'          => (int)$data['id'],
        ]);
        echo json_encode(["success" => true, "message" => "Informations modifiées"]);
        exit();
    }

    if ($action === 'supprimer') {
        $stmt = $conn->prepare('DELETE FROM "InfosGares" WHERE id = :id');
        $stmt->execute([':id' => (int)$data['id']]);
        echo json_encode(["success" => true, "message" => "Information supprimée"]);
        exit();
    }

    echo json_encode(["success" => false, "message" => "Action non reconnue"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
