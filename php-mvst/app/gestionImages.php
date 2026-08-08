<?php
// gestionImages.php
header("Content-Type: application/json");
require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    // ── GET : récupérer toutes les images ─────────────────────────────────
    if ($method === 'GET') {
        $sql  = "SELECT id, titre, description, statut, lien_image FROM \"Images\" ORDER BY \"dateDeCreation\" DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $images = $stmt->fetchAll();
        echo json_encode(["success" => true, "images" => $images]);
        exit();
    }

    // ── POST ──────────────────────────────────────────────────────────────
    if ($method === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'modifier') {
            $id          = (int)$_POST['id'];
            $titre       = $_POST['titre'];
            $description = $_POST['description'];
            $statut      = strtolower($_POST['statut']);

            $sql  = "UPDATE \"Images\" SET titre = :titre, description = :description, statut = :statut WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':titre'       => $titre,
                ':description' => $description,
                ':statut'      => $statut,
                ':id'          => $id,
            ]);

            echo json_encode(["success" => true, "message" => "Image modifiée avec succès"]);
            exit();
        }

        if ($action === 'supprimer') {
            $id = (int)$_POST['id'];

            $sqlSelect = "SELECT lien_image FROM \"Images\" WHERE id = :id";
            $stmtSelect = $conn->prepare($sqlSelect);
            $stmtSelect->execute([':id' => $id]);
            $image = $stmtSelect->fetch();

            if ($image && !empty($image['lien_image'])) {
                $cheminFichier = __DIR__ . '/' . $image['lien_image'];
                if (file_exists($cheminFichier)) {
                    unlink($cheminFichier);
                }
            }

            $sql  = "DELETE FROM \"Images\" WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':id' => $id]);

            echo json_encode(["success" => true, "message" => "Supprimé avec succès"]);
            exit();
        }

        if ($action === 'ajouter') {
            $titre       = $_POST['titre'];
            $description = $_POST['description'];
            $statut      = strtolower($_POST['statut']);
            $sansImage   = isset($_POST['sans_image']) && $_POST['sans_image'] === '1';

            if ($sansImage) {
                $lienImage = '';
            } else {
                if (!isset($_FILES['lien_image']) || $_FILES['lien_image']['error'] !== UPLOAD_ERR_OK) {
                    echo json_encode(["success" => false, "message" => "Erreur upload image"]);
                    exit();
                }

                $uploadDir = __DIR__ . '/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $extension   = pathinfo($_FILES['lien_image']['name'], PATHINFO_EXTENSION);
                $nomFichier  = uniqid('img_') . '.' . $extension;
                $destination = $uploadDir . $nomFichier;

                if (!move_uploaded_file($_FILES['lien_image']['tmp_name'], $destination)) {
                    echo json_encode(["success" => false, "message" => "Impossible de sauvegarder l'image"]);
                    exit();
                }

                $lienImage = 'uploads/' . $nomFichier;
            }

            $sql  = "INSERT INTO \"Images\" (titre, description, statut, lien_image) VALUES (:titre, :description, :statut, :lien_image)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':titre'       => $titre,
                ':description' => $description,
                ':statut'      => $statut,
                ':lien_image'  => $lienImage,
            ]);

            echo json_encode(["success" => true, "message" => "Ajouté avec succès"]);
            exit();
        }
    }

    echo json_encode(["success" => false, "message" => "Action non reconnue"]);

} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => "Erreur : " . $e->getMessage()]);
}
?>
