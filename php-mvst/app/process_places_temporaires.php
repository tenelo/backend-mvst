<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $sqlSelect = "SELECT \"documentId\", places FROM \"PlacesTemporaires\"";
    $stmtSelect = $conn->prepare($sqlSelect);
    $stmtSelect->execute();
    $placesTemp = $stmtSelect->fetchAll();

    if (empty($placesTemp)) {
        echo json_encode(["success" => true, "message" => "Aucune place temporaire"]);
        exit();
    }

    $conn->beginTransaction();

    foreach ($placesTemp as $temp) {
        $documentId = $temp['documentId'];
        $place      = (int)$temp['places'];

        $sqlCheck = "SELECT COUNT(*) as total FROM \"Tickets\" 
                     WHERE \"documentId\" = :documentId AND place = :place AND statut = 'valide'";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->execute([':documentId' => $documentId, ':place' => $place]);
        $result = $stmtCheck->fetch();

        if ((int)$result['total'] === 0) {
            $sqlGetPlaces = "SELECT \"placesChoisies\" FROM \"Departs\" WHERE \"documentId\" = :documentId";
            $stmtGetPlaces = $conn->prepare($sqlGetPlaces);
            $stmtGetPlaces->execute([':documentId' => $documentId]);
            $row = $stmtGetPlaces->fetch();

            if ($row && $row['placesChoisies'] !== null && $row['placesChoisies'] !== '') {
                $decoded = json_decode($row['placesChoisies'], true);
                $placesActuelles = is_array($decoded) ? $decoded : [];
                $placesRestantes = array_values(array_diff($placesActuelles, [$place]));
                $nouvellesPlaces = json_encode($placesRestantes);

                $sqlUpdate = "UPDATE \"Departs\" SET \"placesChoisies\" = :places WHERE \"documentId\" = :documentId";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->execute([':places' => $nouvellesPlaces, ':documentId' => $documentId]);
            }
        }

        $sqlDelTemp = "DELETE FROM \"PlacesTemporaires\" WHERE \"documentId\" = :documentId AND places = :place";
        $stmtDelTemp = $conn->prepare($sqlDelTemp);
        $stmtDelTemp->execute([':documentId' => $documentId, ':place' => $place]);
    }

    $conn->commit();
    echo json_encode(["success" => true]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
