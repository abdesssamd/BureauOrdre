<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

// 1. MUR DE SÉCURITÉ : Pas connecté ? Dehors !
if (empty($_SESSION['user_id'])) {
    http_response_code(403); // Code HTTP "Interdit"
    die("⛔ ACCÈS INTERDIT : Réservé au personnel autorisé.");
}

// 2. VALIDATION : L'ID doit être un nombre entier
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die("❌ Demande invalide.");
}

$file_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];
$user_dept = $_SESSION['department_id'];
$user_role = $_SESSION['role'] ?? 'employe';

// 3. VÉRIFICATION DES DROITS (Qui a le droit de voir ?)
// Admin = voit tout
// Chef/Employé = voit Public + Département + Ses propres fichiers
$sql = "SELECT * FROM files WHERE id = ?";

if ($user_role !== 'admin') {
    $sql .= " AND (
        visibility = 'public' 
        OR (visibility = 'department' AND department_id = $user_dept)
        OR owner_id = $user_id
    )";
}

$stmt = $pdo->prepare($sql);
$stmt->execute([$file_id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    die("❌ Document introuvable ou vous n'avez pas la permission de le consulter.");
}

// 4. LIVRAISON SÉCURISÉE DU FICHIER
// On utilise 'basename' pour empêcher le pirate de remonter dans les dossiers (../)
$safe_filename = basename($file['stored_name']);
$filepath = '../uploads/' . $safe_filename;

if (file_exists($filepath)) {
    // On force le navigateur à ne pas exécuter le fichier, juste l'afficher
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: inline; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: private, max-age=0, must-revalidate');
    
    // Nettoyer le tampon de sortie pour éviter de corrompre le fichier
    if (ob_get_level()) ob_end_clean();
    
    readfile($filepath);
    exit;
} else {
    http_response_code(404);
    die("❌ Erreur critique : Le fichier physique a disparu du serveur.");
}
?>