<?php
// misAjourEtatScanne.php
// Méthode : POST JSON
// Body : { "documentId": "...", "idUtilisateur": "...", "place": 23 }
// Retourne : { "success": true }

header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['documentId']) || !isset($data['idUtilisateur']) || !isset($data['place'])) {
        echo json_encode(["success" => false, "message" => "Paramètres manquants"]);
        exit();
    }

    $documentId    = $data['documentId'];
    $idUtilisateur = $data['idUtilisateur'];
    $place         = (int)$data['place'];
    $scanneDate    = date('Y-m-d H:i:s');

    $sql = "UPDATE \"Tickets\" 
            SET \"etatScanne\" = 'scanné', \"scanneDate\" = :scanneDate
            WHERE \"documentId\" = :documentId 
            AND \"idUtilisateur\" = :idUtilisateur 
            AND place = :place";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':scanneDate'    => $scanneDate,
        ':documentId'    => $documentId,
        ':idUtilisateur' => $idUtilisateur,
        ':place'         => $place,
    ]);

    echo json_encode(["success" => true, "message" => "Ticket mis à jour"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
