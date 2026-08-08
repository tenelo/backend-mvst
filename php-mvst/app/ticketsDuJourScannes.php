<?php
// ticketsDuJourScannes.php
// Méthode : POST JSON
// Body : { "date": "yyyy-MM-dd", "gare": "Ferké" }
// Retourne les tickets scannés pour une date et une gare données

header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['date']) || !isset($data['gare'])) {
        echo json_encode(["success" => false, "message" => "Paramètres manquants"]);
        exit();
    }

    $date = $data['date'];
    $gare = $data['gare'];

    $sql = "SELECT * FROM \"Tickets\"
            WHERE \"scanneDate\"::date = :date
            AND \"etatScanne\" = 'scanné'
            AND depart = :gare
            ORDER BY \"scanneDate\" DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':date' => $date, ':gare' => $gare]);
    $tickets = $stmt->fetchAll();

    echo json_encode(["success" => true, "tickets" => $tickets]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
