<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['telephone']) || !isset($data['role']) || !isset($data['gare'])) {
        echo json_encode(["success" => false, "message" => "Paramètres manquants"]);
        exit();
    }

    $stmt = $conn->prepare('SELECT id FROM "Admins" WHERE telephone = :telephone');
    $stmt->execute([':telephone' => $data['telephone']]);
    if ($stmt->fetch()) {
        echo json_encode(["success" => false, "message" => "Ce numero est deja enregistre"]);
        exit();
    }

    $rolesValides = ['admin', 'superadmin'];
    if (!in_array($data['role'], $rolesValides)) {
        echo json_encode(["success" => false, "message" => "Role invalide"]);
        exit();
    }

    $sql = 'INSERT INTO "Admins" (telephone, role, gare)
            VALUES (:telephone, :role, :gare)';

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':telephone' => $data['telephone'],
        ':role'      => $data['role'],
        ':gare'      => $data['gare'],
    ]);

    echo json_encode(["success" => true, "message" => "Numero ajoute avec succes"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
