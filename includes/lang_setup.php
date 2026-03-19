<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// 1. Changement de langue via URL (?lang=ar)
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = ($_GET['lang'] === 'ar') ? 'ar' : 'fr';
}

// 2. Langue par défaut
$lang_code = $_SESSION['lang'] ?? 'fr';

// 3. Définir la direction (LTR ou RTL)
$dir = ($lang_code === 'ar') ? 'rtl' : 'ltr';

// 4. Charger le vocabulaire
$t = require_once __DIR__ . "/../lang/$lang_code.php"; // $t pour "Translate"
?>