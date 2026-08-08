<?php
header("Content-Type: application/json");
require_once 'config.php';
try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['documentId']) || !isset($data['place'])) {
        echo json_encode(["success" => false, "message" => "Paramètres manquants"]);
        exit();
    }
    $documentId = $data['documentId'];
    $place = $data['place'];
    $sql = "SELECT \"etatScanne\" FROM \"Tickets\" 
            WHERE \"documentId\" = :documentId 
            AND place = :place 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':documentId' => $documentId, ':place' => $place]);
    $ticket = $stmt->fetch();
    if ($ticket) {
        echo json_encode(["success" => true, "etatScanne" => $ticket['etatScanne']]);
    } else {
        echo json_encode(["success" => false, "message" => "Ticket non trouvé"]);
    }
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
