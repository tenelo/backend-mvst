<?php
header("Content-Type: application/json");
require_once 'config.php';
try {
    $dateDuJour = date('Y-m-d');
    $sql = "SELECT * FROM \"Tickets\" 
            WHERE \"datePourCalcule\" >= :dateDuJour
            ORDER BY \"datePourCalcule\" ASC, heure ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':dateDuJour' => $dateDuJour]);
    $tickets = $stmt->fetchAll();
    echo json_encode(["success" => true, "tickets" => $tickets]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
