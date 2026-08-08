<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['idUtilisateur']) || !isset($data['telephone'])) {
        echo json_encode(["success" => false, "message" => "Paramètres manquants"]);
        exit();
    }

    $sql = 'UPDATE "Admins" SET
                "idUtilisateur" = :idUtilisateur,
                "idAuth"        = :idAuth,
                nom             = :nom,
                prenoms         = :prenoms,
                residence       = :residence,
                mail            = :mail,
                "dateDeCreation" = NOW()
            WHERE telephone = :telephone';

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':idUtilisateur' => $data['idUtilisateur'],
        ':idAuth'        => $data['idAuth']      ?? '',
        ':nom'           => $data['nom']         ?? '',
        ':prenoms'       => $data['prenoms']     ?? '',
        ':residence'     => $data['residence']   ?? '',
        ':mail'          => $data['mail']        ?? '',
        ':telephone'     => $data['telephone'],
    ]);

    echo json_encode(["success" => true, "message" => "Admin mis à jour"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
