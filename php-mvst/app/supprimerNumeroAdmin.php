<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['telephone'])) {
        echo json_encode(["success" => false, "message" => "Paramètre manquant"]);
        exit();
    }

    $stmt = $conn->prepare('DELETE FROM "Admins" WHERE telephone = :telephone');
    $stmt->execute([':telephone' => $data['telephone']]);

    echo json_encode(["success" => true, "message" => "Numéro supprimé"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
