<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['telephone'])) {
        echo json_encode(["success" => false, "message" => "Paramètre manquant"]);
        exit();
    }

    $telephone = $data['telephone'];

    $stmt = $conn->prepare('SELECT id, points FROM "Utilisateurs" WHERE telephone = :telephone');
    $stmt->execute([':telephone' => $telephone]);
    $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

    $existe = $utilisateur ? true : false;
    $bloque = ($utilisateur && intval($utilisateur['points']) <= 0) ? true : false;

    echo json_encode([
        "success" => true,
        "existe" => $existe,
        "bloque" => $bloque,
        "points" => $utilisateur ? intval($utilisateur['points']) : 0
    ]);
    
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
