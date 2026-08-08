<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["date"]) || !isset($data["gare"])) {
        echo json_encode(["success" => false, "message" => "Paramètres manquants"]);
        exit();
    }

    $date = $data["date"];
    $gare = $data["gare"];

    $sql = "SELECT 
                \"heureDeDepart\",
                \"documentId\",
                \"dateDeDepart\",
                depart,
                destination,
                \"typeVoyage\",
                (SELECT COUNT(*) FROM \"Tickets\" t 
                 WHERE t.\"documentId\" = d.\"documentId\") AS \"nombreDePlacesChoisies\"
            FROM \"Departs\" d
            WHERE \"dateDeDepart\" = :date
            AND depart = :gare
            GROUP BY \"documentId\", \"heureDeDepart\", \"dateDeDepart\", depart, destination, \"typeVoyage\"
            ORDER BY \"heureDeDepart\" ASC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([":date" => $date, ":gare" => $gare]);
    $departs = $stmt->fetchAll();

    echo json_encode(["success" => true, "departs" => $departs]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
