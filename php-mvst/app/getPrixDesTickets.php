<?php
header("Content-Type: application/json");
require_once 'config.php';
try {
    $data = json_decode(file_get_contents("php://input"), true);
    $type = $data['type'] ?? 'standard';
    $sql  = "SELECT ligne AS axe, prix FROM \"Lignes\" WHERE type = :type AND prix > 0 ORDER BY ligne ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':type' => $type]);
    $prix = $stmt->fetchAll();
    echo json_encode([
        "success" => true,
        "heures"  => $prix
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Erreur : " . $e->getMessage()
    ]);
}
?>
