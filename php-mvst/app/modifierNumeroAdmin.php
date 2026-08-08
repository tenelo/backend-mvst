<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['id']) || !isset($data['telephone']) || !isset($data['role']) || !isset($data['gare'])) {
        echo json_encode(["success" => false, "message" => "Paramètres manquants"]);
        exit();
    }

    // Vérifier que le nouveau numéro n'est pas déjà utilisé par un autre admin
    $stmt = $conn->prepare('SELECT id FROM "Admins" WHERE telephone = :telephone AND id != :id');
    $stmt->execute([':telephone' => $data['telephone'], ':id' => $data['id']]);
    if ($stmt->fetch()) {
        echo json_encode(["success" => false, "message" => "Ce numéro est déjà utilisé par un autre admin"]);
        exit();
    }

    $stmt = $conn->prepare('UPDATE "Admins" SET telephone = :telephone, role = :role, gare = :gare, profil = :role WHERE id = :id AND nom IS NULL');
    $stmt->execute([
        ':telephone' => $data['telephone'],
        ':role'      => $data['role'],
        ':gare'      => $data['gare'],
        ':id'        => $data['id'],
    ]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(["success" => false, "message" => "Modification impossible. Le compte a déjà été créé."]);
        exit();
    }

    echo json_encode(["success" => true, "message" => "Informations mises à jour"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
