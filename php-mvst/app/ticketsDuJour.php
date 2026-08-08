<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['documentId']) || !isset($data['gare'])) {
        echo json_encode(["success" => false, "message" => "Paramètres manquants"]);
        exit();
    }

    $documentId = $data['documentId'];
    $gare       = $data['gare'];

    $sql = "SELECT * FROM \"Tickets\"
            WHERE \"documentId\" = :documentId
            AND depart = :gare
            ORDER BY \"dateDeCreation\" DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':documentId' => $documentId, ':gare' => $gare]);
    $tickets = $stmt->fetchAll();

    echo json_encode(["success" => true, "tickets" => $tickets]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
