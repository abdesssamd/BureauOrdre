<?php
// job_ocr.php - VERSION POUR DOSSIER "INCLUDES"

// 1. Connexion DB
// __DIR__ est le dossier actuel (includes). 
// On ajoute /../ pour remonter d'un cran vers la racine pour trouver 'config'
require_once __DIR__ . '/../config/db.php'; 

// Le fichier ocr_helper est dans le même dossier (includes), donc pas de /../
require_once __DIR__ . '/ocr_helper.php';

// Pas de limite de temps
set_time_limit(0); 

echo "--- Demarrage du Job OCR : " . date('Y-m-d H:i:s') . " ---\n";

// 2. On cherche les fichiers en attente
$stmt = $pdo->prepare("SELECT id, stored_name, original_name FROM files WHERE ocr_status = 'pending' LIMIT 5");
$stmt->execute();
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($files) === 0) {
    echo "Aucun fichier en attente.\n";
    exit;
}

// 3. Chemin vers les uploads
// On remonte d'un cran (/../) pour sortir de 'includes' et aller dans 'uploads'
$uploads_dir = __DIR__ . '/../uploads/'; 

foreach ($files as $file) {
    $file_id = $file['id'];
    $full_path = $uploads_dir . $file['stored_name'];

    echo "Traitement du fichier ID: $file_id ({$file['original_name']})...\n";

    // Marquer comme 'processing'
    $pdo->prepare("UPDATE files SET ocr_status = 'processing' WHERE id = ?")->execute([$file_id]);

    if (file_exists($full_path)) {
        try {
            $text = extractTextFromFile($full_path);
            
            if ($text === null) $text = "";

            // Sauvegarde
            $update = $pdo->prepare("UPDATE files SET ocr_content = ?, ocr_status = 'done' WHERE id = ?");
            $update->execute([$text, $file_id]);
            
            echo " > Termine. Longueur texte : " . strlen($text) . " cars.\n";

        } catch (Exception $e) {
            echo " > Erreur : " . $e->getMessage() . "\n";
            $pdo->prepare("UPDATE files SET ocr_status = 'done' WHERE id = ?")->execute([$file_id]);
        }
    } else {
        echo " > Fichier introuvable : $full_path\n";
        $pdo->prepare("UPDATE files SET ocr_status = 'done' WHERE id = ?")->execute([$file_id]);
    }
}

echo "--- Fin du lot ---\n";
?>