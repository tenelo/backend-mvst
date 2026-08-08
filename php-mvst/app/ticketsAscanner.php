<?php
header("Content-Type: application/json");
require_once 'config.php';
try {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['gare'])) {
        echo json_encode(["success" => false, "message" => "Paramètre manquant : gare"]);
        exit();
    }
    $gare = $data['gare'];
    $dateDuJour = date('Y-m-d');
    $sql = "SELECT * FROM \"Tickets\" 
            WHERE \"datePourCalcule\" >= :dateDuJour
            AND depart = :gare
            ORDER BY \"datePourCalcule\" ASC, heure ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':dateDuJour' => $dateDuJour, ':gare' => $gare]);
    $tickets = $stmt->fetchAll();
    echo json_encode(["success" => true, "tickets" => $tickets]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
