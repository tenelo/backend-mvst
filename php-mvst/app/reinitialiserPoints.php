<?php
header("Content-Type: application/json");
require_once 'config.php';

try {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    switch ($action) {

        case 'lister_tous':
            $page = isset($data['page']) ? intval($data['page']) : 1;
            $limit = isset($data['limit']) ? intval($data['limit']) : 100;
            $offset = ($page - 1) * $limit;

            $stmtCount = $conn->query('SELECT COUNT(*) as total FROM "Utilisateurs"');
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $conn->prepare('
                SELECT "idUtilisateur", nom, prenoms, telephone, residence, points, "dateDeCreation"
                FROM "Utilisateurs"
                ORDER BY "dateDeCreation" DESC
                LIMIT :limit OFFSET :offset
            ');
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "utilisateurs" => $utilisateurs,
                "total" => intval($total),
                "page" => $page,
                "limit" => $limit,
                "totalPages" => ceil($total / $limit)
            ]);
            break;

        case 'lister_bloques':
            $page = isset($data['page']) ? intval($data['page']) : 1;
            $limit = isset($data['limit']) ? intval($data['limit']) : 100;
            $offset = ($page - 1) * $limit;

            $stmtCount = $conn->prepare('
                SELECT COUNT(DISTINCT h."idUtilisateur") as total
                FROM "historique_actions" h
                INNER JOIN (
                    SELECT "idUtilisateur", MAX(date) AS derniere_date
                    FROM "historique_actions"
                    WHERE nouveaux_points <= 0
                    GROUP BY "idUtilisateur"
                ) derniere ON h."idUtilisateur" = derniere."idUtilisateur" AND h.date = derniere.derniere_date
            ');
            $stmtCount->execute();
            $total = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $conn->prepare('
                SELECT h."idUtilisateur", h.nom, h.prenoms, h.telephone, h.nouveaux_points AS points, h.motif, h.date
                FROM "historique_actions" h
                INNER JOIN (
                    SELECT "idUtilisateur", MAX(date) AS derniere_date
                    FROM "historique_actions"
                    WHERE nouveaux_points <= 0
                    GROUP BY "idUtilisateur"
                ) derniere ON h."idUtilisateur" = derniere."idUtilisateur" AND h.date = derniere.derniere_date
                ORDER BY h.date DESC
                LIMIT :limit OFFSET :offset
            ');
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $bloques = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "bloques" => $bloques,
                "total" => intval($total),
                "page" => $page,
                "limit" => $limit,
                "totalPages" => ceil($total / $limit)
            ]);
            break;

        case 'reinitialiser':
            if (!isset($data['idUtilisateur']) || !isset($data['points'])) {
                echo json_encode(["success" => false, "message" => "Parametres manquants"]);
                exit();
            }

            $idUtilisateur = $data['idUtilisateur'];
            $points = intval($data['points']);
            $motif = $data['motif'] ?? 'Reinitialisation par administrateur';

            if ($points < 1) {
                echo json_encode(["success" => false, "message" => "Le nombre de points doit etre superieur a 0"]);
                exit();
            }

            $stmt = $conn->prepare('SELECT nom, prenoms, telephone, points FROM "Utilisateurs" WHERE "idUtilisateur" = :id');
            $stmt->execute([':id' => $idUtilisateur]);
            $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$utilisateur) {
                echo json_encode(["success" => false, "message" => "Utilisateur introuvable"]);
                exit();
            }

            $anciensPoints = intval($utilisateur['points']);
            $nom = $utilisateur['nom'];
            $prenoms = $utilisateur['prenoms'] ?? '';
            $telephone = $utilisateur['telephone'];

            $stmt = $conn->prepare('UPDATE "Utilisateurs" SET points = :points WHERE "idUtilisateur" = :id');
            $stmt->execute([':points' => $points, ':id' => $idUtilisateur]);

            $stmt = $conn->prepare('INSERT INTO "historique_actions" ("idUtilisateur", nom, prenoms, telephone, anciens_points, nouveaux_points, motif, date) VALUES (:id, :nom, :prenoms, :tel, :ancien, :nouveau, :motif, NOW())');
            $stmt->execute([
                ':id' => $idUtilisateur,
                ':nom' => $nom,
                ':prenoms' => $prenoms,
                ':tel' => $telephone,
                ':ancien' => $anciensPoints,
                ':nouveau' => $points,
                ':motif' => "Deblocage : $motif"
            ]);

            echo json_encode([
                "success" => true,
                "message" => "$nom debloque avec $points points",
                "points" => $points
            ]);
            break;

        case 'verifier':
            if (!isset($data['telephone'])) {
                echo json_encode(["success" => false, "message" => "Numero manquant"]);
                exit();
            }

            $telephone = $data['telephone'];

            $stmt = $conn->prepare('SELECT "idUtilisateur", nom, prenoms, telephone, residence, points FROM "Utilisateurs" WHERE telephone = :tel');
            $stmt->execute([':tel' => $telephone]);
            $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$utilisateur) {
                echo json_encode(["success" => false, "message" => "Aucun utilisateur trouve"]);
                exit();
            }

            $stmt = $conn->prepare('SELECT * FROM "historique_actions" WHERE "idUtilisateur" = :id ORDER BY date DESC');
            $stmt->execute([':id' => $utilisateur['idUtilisateur']]);
            $historique = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                "success" => true,
                "utilisateur" => $utilisateur,
                "bloque" => intval($utilisateur['points']) <= 0,
                "historique" => $historique
            ]);
            break;

        default:
            echo json_encode(["success" => false, "message" => "Action non reconnue"]);
    }

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
