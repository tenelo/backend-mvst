<?php
// graphiques.php
// POST { "type": "jour",      "date": "EEEE_d_MMMM_y", "gare": "Ferké" }
// POST { "type": "moisAnnee", "moisAnnee": "MMMM_y",   "gare": "Ferké" }
// POST { "type": "annee",     "annee": "2026",          "gare": "Ferké" }

header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $type = $data['type'] ?? '';
    $gare = $data['gare'] ?? '';

    if ($type === 'jour') {
        $sql = "SELECT t.* FROM \"Tickets\" t
                JOIN \"Departs\" d ON t.\"documentId\" = d.\"documentId\"
                WHERE d.\"dateDeDepart\" = :valeur
                AND t.depart = :gare";
        $valeur = $data['date'];

    } elseif ($type === 'moisAnnee') {
        $sql = "SELECT t.* FROM \"Tickets\" t
                JOIN \"Departs\" d ON t.\"documentId\" = d.\"documentId\"
                WHERE d.\"moisAnnee\" = :valeur
                AND t.depart = :gare";
        $valeur = $data['moisAnnee'];

    } elseif ($type === 'annee') {
        $sql = "SELECT t.* FROM \"Tickets\" t
                JOIN \"Departs\" d ON t.\"documentId\" = d.\"documentId\"
                WHERE d.annee = :valeur
                AND t.depart = :gare";
        $valeur = $data['annee'];

    } else {
        echo json_encode(["success" => false, "message" => "Type non reconnu"]);
        exit();
    }

    $stmt = $conn->prepare($sql);
    $stmt->execute([':valeur' => $valeur, ':gare' => $gare]);
    $tickets = $stmt->fetchAll();

    echo json_encode(["success" => true, "tickets" => $tickets]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
