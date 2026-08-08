<?php
header("Content-Type: application/json");
require_once 'config.php';
try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['documentId'])) {
        echo json_encode(["success" => false, "message" => "Paramètre manquant"]);
        exit();
    }
    $documentId = $data['documentId'];
    $sql = "SELECT * FROM \"Tickets\"
            WHERE \"documentId\" = :documentId
            AND \"etatScanne\" = 'scanné'
            ORDER BY \"scanneDate\" DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':documentId' => $documentId]);
    $tickets = $stmt->fetchAll();
    echo json_encode(["success" => true, "tickets" => $tickets]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
