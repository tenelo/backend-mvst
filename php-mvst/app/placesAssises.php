<?php
// placesAssises.php
// Méthode : POST JSON
// Body : { "documentId": "..." }
// Retourne les places occupées pour un départ donné

header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['documentId'])) {
        echo json_encode(["success" => false, "message" => "Paramètre manquant"]);
        exit();
    }

    $documentId = $data['documentId'];

    $sql = "SELECT nom, telephone, depart, destination, place 
            FROM \"Tickets\" 
            WHERE \"documentId\" = :documentId
            ORDER BY place ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':documentId' => $documentId]);
    $places = $stmt->fetchAll();

    echo json_encode(["success" => true, "places" => $places]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
