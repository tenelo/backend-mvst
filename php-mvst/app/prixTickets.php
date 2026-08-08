<?php
header("Content-Type: application/json");
require_once 'config.php';
try {
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $stmt = $conn->prepare('SELECT * FROM "PrixDesTickets" ORDER BY id ASC');
        $stmt->execute();
        $prix = $stmt->fetchAll();
        echo json_encode(["success" => true, "prix" => $prix]);
        exit();
    }

    $data   = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    if ($action === 'ajouter') {
        $type = $data['type'] ?? 'standard';
        $stmt = $conn->prepare(
            'INSERT INTO "PrixDesTickets" (axe, prix, type) VALUES (:axe, :prix, :type)'
        );
        $stmt->execute([':axe' => $data['axe'], ':prix' => (int)$data['prix'], ':type' => $type]);
        echo json_encode(["success" => true, "message" => "Prix ajouté"]);
        exit();
    }

    if ($action === 'modifier') {
        $type = $data['type'] ?? 'standard';
        $stmt = $conn->prepare(
            'UPDATE "PrixDesTickets" SET axe = :axe, prix = :prix, type = :type WHERE id = :id'
        );
        $stmt->execute([
            ':axe'  => $data['axe'],
            ':prix' => (int)$data['prix'],
            ':type' => $type,
            ':id'   => (int)$data['id'],
        ]);
        echo json_encode(["success" => true, "message" => "Prix modifié"]);
        exit();
    }

    if ($action === 'supprimer') {
        $stmt = $conn->prepare('DELETE FROM "PrixDesTickets" WHERE id = :id');
        $stmt->execute([':id' => (int)$data['id']]);
        echo json_encode(["success" => true, "message" => "Prix supprimé"]);
        exit();
    }

    echo json_encode(["success" => false, "message" => "Action non reconnue"]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
