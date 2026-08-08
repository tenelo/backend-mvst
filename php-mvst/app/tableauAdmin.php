<?php
// tableauAdmin.php
// Méthode : POST JSON
// Body : { "annee": "2026", "gare": "Ferké" }
// Retourne tous les tickets pour une année et une gare données

header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['annee']) || !isset($data['gare'])) {
        echo json_encode(["success" => false, "message" => "Paramètres manquants"]);
        exit();
    }

    $annee = $data['annee'];
    $gare  = $data['gare'];

    $sql = "SELECT t.* FROM \"Tickets\" t
            JOIN \"Departs\" d ON t.\"documentId\" = d.\"documentId\"
            WHERE d.annee = :annee
            AND t.depart = :gare
            ORDER BY t.\"dateDeCreation\" DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':annee' => $annee, ':gare' => $gare]);
    $tickets = $stmt->fetchAll();

    echo json_encode(["success" => true, "tickets" => $tickets]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
