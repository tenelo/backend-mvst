<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['telephone'])) {
        echo json_encode(["success" => false, "message" => "Paramètre manquant"]);
        exit();
    }

    $stmt = $conn->prepare('SELECT id, nom FROM "Admins" WHERE telephone = :telephone');
    $stmt->execute([':telephone' => $data['telephone']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        echo json_encode(["success" => true, "autorise" => false, "existe" => false]);
        exit();
    }

    if (!empty($admin['nom'])) {
        echo json_encode(["success" => true, "autorise" => true, "existe" => true]);
        exit();
    }

    echo json_encode(["success" => true, "autorise" => true, "existe" => false]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "PDO: " . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur: " . $e->getMessage()]);
}
?>
