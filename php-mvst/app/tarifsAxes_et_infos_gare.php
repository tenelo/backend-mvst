<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $type = isset($_GET['type']) ? $_GET['type'] : '';

    if ($type === 'tarifs') {
        $sql  = "SELECT axe, prix FROM \"PrixDesTickets\" ORDER BY axe ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $tarifs = $stmt->fetchAll();
        echo json_encode(["success" => true, "tarifs" => $tarifs]);

    } elseif ($type === 'gares') {
        $sql  = "SELECT ville, description, telephone FROM \"InfosGares\" ORDER BY ville ASC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $gares = $stmt->fetchAll();
        echo json_encode(["success" => true, "tarifs" => $gares]);

    } else {
        echo json_encode(["success" => false, "message" => "Paramètre type manquant"]);
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
