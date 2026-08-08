<?php
// suppressionTickets.php
// Méthode : POST JSON
// Body : { "dates": ["date1", "date2", "date3", "date4"] }
// Retourne les tickets pour les dates données

header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $method = $_SERVER['REQUEST_METHOD'];

    // ── GET tickets par dates ──────────────────────────────────────────────
    if ($method === 'POST' && isset($data['dates'])) {
        $dates = $data['dates'];
        $placeholders = implode(',', array_fill(0, count($dates), '?'));

        $sql = "SELECT * FROM \"Tickets\"
                WHERE date IN ($placeholders)
                ORDER BY \"dateDeCreation\" DESC";

        $stmt = $conn->prepare($sql);
        $stmt->execute($dates);
        $tickets = $stmt->fetchAll();

        echo json_encode(["success" => true, "tickets" => $tickets]);
        exit();
    }

    // ── DELETE ticket par id ───────────────────────────────────────────────
    if ($method === 'POST' && isset($data['action']) && $data['action'] === 'supprimer') {
        $id = (int)$data['id'];

        $sql  = "DELETE FROM \"Tickets\" WHERE id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':id' => $id]);

        echo json_encode(["success" => true, "message" => "Ticket supprimé"]);
        exit();
    }

    echo json_encode(["success" => false, "message" => "Action non reconnue"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
