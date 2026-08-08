<?php
header("Content-Type: application/json");
require_once 'config.php';
try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $conn->prepare('SELECT * FROM "HeuresDeDeparts" ORDER BY heure ASC');
        $stmt->execute();
        $heures = $stmt->fetchAll();
        echo json_encode(["success" => true, "heures" => $heures]);
        exit();
    }

    $data   = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    if ($action === 'ajouter') {
        $type = $data['type'] ?? 'standard';
        $stmt = $conn->prepare('INSERT INTO "HeuresDeDeparts" (heure, type) VALUES (:heure, :type)');
        $stmt->execute([':heure' => $data['heure'], ':type' => $type]);
        echo json_encode(["success" => true, "message" => "Heure ajoutée"]);
        exit();
    }

    if ($action === 'modifier') {
        $type = $data['type'] ?? 'standard';
        $stmt = $conn->prepare('UPDATE "HeuresDeDeparts" SET heure = :heure, type = :type WHERE id = :id');
        $stmt->execute([':heure' => $data['heure'], ':type' => $type, ':id' => (int)$data['id']]);
        echo json_encode(["success" => true, "message" => "Heure modifiée"]);
        exit();
    }

    if ($action === 'supprimer') {
        $stmt = $conn->prepare('DELETE FROM "HeuresDeDeparts" WHERE id = :id');
        $stmt->execute([':id' => (int)$data['id']]);
        echo json_encode(["success" => true, "message" => "Heure supprimée"]);
        exit();
    }

    echo json_encode(["success" => false, "message" => "Action non reconnue"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
