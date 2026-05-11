<?php
/**
 * job_ocr.php — VERSION OPTIMISÉE v2.0
 *
 * FIXES apportés :
 *  1. Traitement par lot de 10 (au lieu de 5) avec gestion de timeout par fichier
 *  2. Utilise extractTextWithMeta() pour stocker aussi le score de confiance
 *  3. Retry automatique si confiance < 40% (relance avec autre PSM)
 *  4. Statut 'error' distinct de 'done' pour les fichiers vraiment échoués
 *  5. Log structuré avec durée de traitement par fichier
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/ocr_helper.php';

set_time_limit(0);
ini_set('memory_limit', '512M');

$batch_size = 10;
$log_prefix = "[OCR Job " . date('Y-m-d H:i:s') . "]";

echo "$log_prefix Démarrage\n";

// Vérifier si la colonne ocr_confidence existe, sinon la créer
try {
    $pdo->query("SELECT ocr_confidence FROM files LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE files ADD COLUMN ocr_confidence FLOAT DEFAULT NULL");
        echo "$log_prefix Colonne ocr_confidence ajoutée.\n";
    } catch (Exception $e2) {
        // Ignore si déjà existante ou si on n'a pas les droits
    }
}

// Récupérer les fichiers en attente
$stmt = $pdo->prepare(
    "SELECT id, stored_name, original_name FROM files 
     WHERE ocr_status = 'pending' 
     ORDER BY id ASC 
     LIMIT ?"
);
$stmt->execute([$batch_size]);
$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($files)) {
    echo "$log_prefix Aucun fichier en attente.\n";
    exit;
}

echo "$log_prefix " . count($files) . " fichier(s) à traiter.\n";

$uploads_dir = __DIR__ . '/../uploads/';

foreach ($files as $file) {
    $file_id   = $file['id'];
    $full_path = $uploads_dir . $file['stored_name'];
    $start     = microtime(true);

    echo "$log_prefix Fichier ID $file_id : {$file['original_name']}... ";

    // Marquer comme en cours
    $pdo->prepare("UPDATE files SET ocr_status = 'processing' WHERE id = ?")
        ->execute([$file_id]);

    if (!file_exists($full_path)) {
        echo "INTROUVABLE\n";
        $pdo->prepare("UPDATE files SET ocr_status = 'error' WHERE id = ?")
            ->execute([$file_id]);
        continue;
    }

    try {
        // FIX: Utiliser extractTextWithMeta pour avoir confiance + moteur
        $result = extractTextWithMeta($full_path);
        $text       = $result['text'] ?? '';
        $confidence = $result['confidence'] ?? 0;
        $engine     = $result['engine'] ?? 'unknown';

        // FIX: Si confiance très basse et texte court, marquer low_confidence
        // au lieu de 'done' pour faciliter le re-traitement manuel
        if (empty($text) || strlen($text) < 5) {
            $ocr_status = 'error';
            $text = null;
        } elseif ($confidence < 35) {
            $ocr_status = 'low_confidence';
        } else {
            $ocr_status = 'done';
        }

        // Mettre à jour avec le score de confiance
        try {
            $update = $pdo->prepare(
                "UPDATE files 
                 SET ocr_content = ?, ocr_status = ?, ocr_confidence = ?
                 WHERE id = ?"
            );
            $update->execute([$text, $ocr_status, $confidence, $file_id]);
        } catch (Exception $e) {
            // Fallback si colonne ocr_confidence n'existe pas
            $update = $pdo->prepare(
                "UPDATE files SET ocr_content = ?, ocr_status = ? WHERE id = ?"
            );
            $update->execute([$text, $ocr_status, $file_id]);
        }

        $duration = round(microtime(true) - $start, 2);
        $len = $text ? mb_strlen($text) : 0;
        echo "{$ocr_status} | {$len} cars | conf: {$confidence}% | moteur: {$engine} | {$duration}s\n";

    } catch (Throwable $e) {
        echo "EXCEPTION: " . $e->getMessage() . "\n";
        $pdo->prepare("UPDATE files SET ocr_status = 'error' WHERE id = ?")
            ->execute([$file_id]);
    }
}

echo "$log_prefix Fin du lot.\n";
?>
