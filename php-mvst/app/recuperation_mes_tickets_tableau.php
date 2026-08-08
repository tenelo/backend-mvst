<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['idUtilisateur'])) {
        echo json_encode(["success" => false, "message" => "Paramètre manquant : idUtilisateur"]);
        exit();
    }

    $idUtilisateur = $data['idUtilisateur'];
    $offset = isset($data['offset']) ? intval($data['offset']) : 0;
    $limit  = isset($data['limit'])  ? intval($data['limit'])  : 100;

    $sqlCount = "SELECT COUNT(*) as total FROM \"Tickets\" WHERE \"idUtilisateur\" = :idUtilisateur";
    $stmtCount = $conn->prepare($sqlCount);
    $stmtCount->execute([':idUtilisateur' => $idUtilisateur]);
    $total = intval($stmtCount->fetch()['total']);

    $sql = "SELECT 
                t.\"documentId\",
                t.\"idUtilisateur\",
                t.nom,
                t.telephone,
                t.date,
                t.heure,
                t.depart,
                t.destination,
                t.place,
                t.\"etatScanne\",
                t.\"prixDuTicket\",
                t.statut,
                t.\"datePourCalcule\"::text,
                t.\"scanneDate\",
                t.\"dateDeCreation\"::text,
                t.\"typeVoyage\"
            FROM \"Tickets\" t
            WHERE t.\"idUtilisateur\" = :idUtilisateur 
            ORDER BY t.\"dateDeCreation\" DESC NULLS LAST
            LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':idUtilisateur', $idUtilisateur, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $tickets = $stmt->fetchAll();

    echo json_encode(["success" => true, "tickets" => $tickets, "total" => $total]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
