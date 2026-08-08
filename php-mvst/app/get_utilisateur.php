<?php
header("Content-Type: application/json");
require_once 'config.php';
$id = $_GET['id'] ?? '';
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'id manquant']);
    exit();
}
try {
    $stmt = $conn->prepare(
        'SELECT nom, prenoms, telephone, residence, points, mail, "dateDeCreation" FROM "Utilisateurs" WHERE "idUtilisateur" = :id LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo json_encode([
            'success'    => true,
            'utilisateur' => [
                'nom'      => $row['nom'],
                'prenoms'  => $row['prenoms'],
                'telephone'=> $row['telephone'],
                'residence'=> $row['residence'],
                'points'   => $row['points'],
                'mail'     => $row['mail'],
                'dateDeCreation' => $row['dateDeCreation']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
