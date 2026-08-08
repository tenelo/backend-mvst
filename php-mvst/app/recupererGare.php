<?php
// recupererGare.php
// Méthode : POST JSON
// Body : { "idUtilisateur": "..." }
// Retourne : { "success": true, "gare": "Ferké" }

header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['idUtilisateur'])) {
        echo json_encode(["success" => false, "message" => "Paramètre manquant"]);
        exit();
    }

    $idUtilisateur = $data['idUtilisateur'];

    $sql = "SELECT gare FROM \"Admins\" WHERE \"idUtilisateur\" = :idUtilisateur LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':idUtilisateur' => $idUtilisateur]);
    $result = $stmt->fetch();

    if ($result) {
        echo json_encode(["success" => true, "gare" => $result['gare']]);
    } else {
        echo json_encode(["success" => false, "message" => "Admin non trouvé"]);
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
