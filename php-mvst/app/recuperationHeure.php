<?php
header("Content-Type: application/json");
require_once 'config.php';
try {
    $data = json_decode(file_get_contents("php://input"), true);
    $type = $data['type'] ?? 'standard';
    $sql  = "SELECT heure FROM \"HeuresDeDeparts\" WHERE type = :type ORDER BY heure ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':type' => $type]);
    $heures = $stmt->fetchAll();
    echo json_encode([
        "success" => true,
        "heures"  => $heures
    ]);
} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "PDO: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur: " . $e->getMessage()]);
} catch (Error $e) {
    echo json_encode(["success" => false, "message" => "Fatal: " . $e->getMessage()]);
}
?>
