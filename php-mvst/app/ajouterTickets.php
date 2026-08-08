<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data['documentId']) || !isset($data['places'])) {
        echo json_encode(["success" => false, "message" => "Paramètres manquants"]);
        exit();
    }

    $documentId      = $data['documentId'];
    $idUtilisateur   = $data['idUtilisateur'];
    $nom             = $data['nom'];
    $telephone       = $data['telephone'];
    $date            = $data['date'];
    $heure           = $data['heure'];
    $depart          = $data['depart'];
    $destination     = $data['destination'];
    $prixDuTicket    = (int)$data['prixDuTicket'];
    $places          = $data['places'];
    $statut          = $data['statut']    ?? 'valide';
    $etatScanne      = $data['etatScanne'] ?? 'nonScanné';
    $datePourCalcule = substr($data['datePourCalcule'], 0, 10);
    $typeVoyage = $data['typeVoyage'] ?? 'standard';

    if (empty($places) || !is_array($places)) {
        echo json_encode(["success" => false, "message" => "Aucune place fournie"]);
        exit();
    }

    $conn->beginTransaction();

    foreach ($places as $place) {
        $sql = "INSERT INTO \"Tickets\" 
            (\"documentId\", \"idUtilisateur\", nom, telephone, date, heure, 
             depart, destination, \"prixDuTicket\", place, \"etatScanne\", 
             statut, \"datePourCalcule\", \"scanneDate\", \"dateDeCreation\", \"typeVoyage\")
        VALUES 
            (:documentId, :idUtilisateur, :nom, :telephone, :date, :heure,
             :depart, :destination, :prixDuTicket, :place, :etatScanne,
             :statut, :datePourCalcule, '', NOW(), :typeVoyage)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':documentId'      => $documentId,
            ':idUtilisateur'   => $idUtilisateur,
            ':nom'             => $nom,
            ':telephone'       => $telephone,
            ':date'            => $date,
            ':heure'           => $heure,
            ':depart'          => $depart,
            ':destination'     => $destination,
            ':prixDuTicket'    => $prixDuTicket,
            ':place'           => (int)$place,
            ':etatScanne'      => $etatScanne,
            ':statut'          => $statut,
            ':datePourCalcule' => $datePourCalcule,
            ':typeVoyage' => $typeVoyage,
        ]);
    }

    $conn->commit();
    echo json_encode(["success" => true, "message" => "Tickets ajoutés avec succès"]);

} catch (Exception $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
