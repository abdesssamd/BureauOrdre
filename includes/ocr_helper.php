<?php
// ocr_helper.php - VERSION UNIQUE ET ROBUSTE

function extractTextFromFile($file_path) {
    // CHEMINS
    $tesseract   = 'C:/Tesseract-OCR/tesseract.exe';
    $tessdata    = 'C:/Tesseract-OCR/tessdata';
    
    // Detection Ghostscript
    $ghostscript = 'C:/Program Files/gs/gs10.06.0/bin/gswin64c.exe'; 
    if (!file_exists($ghostscript)) {
         $ghostscript = 'C:/Program Files (x86)/gs/gs10.06.0/bin/gswin32c.exe';
    }

    if (!file_exists($tesseract) || !file_exists($file_path)) return null;

    putenv("TESSDATA_PREFIX=$tessdata");
    putenv("LC_ALL=C"); 

    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $image_to_scan = null;
    $temp_files = [];

    // --- CAS 1 : C'EST DEJA UNE IMAGE (RAPIDE) ---
    if (in_array($ext, ['jpg','jpeg','png','tif','tiff'])) {
        $image_to_scan = $file_path;
    }
    // --- CAS 2 : C'EST UN PDF (LENT - nécessite Ghostscript) ---
    elseif ($ext === 'pdf') {
        if (!file_exists($ghostscript)) return null;
        
        $temp_img = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('ocr_sharp_') . '.png';
        $temp_files[] = $temp_img;

        // Conversion PDF -> PNG Noir & Blanc Haute Qualité
        $cmd = "\"$ghostscript\" -dSAFER -dBATCH -dNOPAUSE -sDEVICE=pngmono -r600 -dTextAlphaBits=1 -dGraphicsAlphaBits=1 -dLastPage=1 -sOutputFile=\"$temp_img\" \"$file_path\"";
        shell_exec($cmd);
        
        if (file_exists($temp_img)) $image_to_scan = $temp_img;
    }

    if (!$image_to_scan) return null;

    // --- EXECUTION TESSERACT ---
    $cmd = "\"$tesseract\" \"$image_to_scan\" stdout -l ara+fra+osd --tessdata-dir \"$tessdata\" --psm 3 2>&1";
    $output = shell_exec($cmd);

    // Nettoyage temporaire
    foreach($temp_files as $f) { if(file_exists($f)) @unlink($f); }

    return cleanOutput($output);
}

function cleanOutput($text) {
    if (empty($text)) return null;
    if (strpos($text, 'Error') !== false || strpos($text, 'System Error') !== false) return null;
    $text = str_replace(['asprise', 'Scanner.js', 'ONLY', 'FOREVALUATION'], '', $text);
    $clean = preg_replace('/[^\p{Arabic}\p{L}0-9\s\.\,\-\_\/]/u', ' ', $text);
    return trim($clean);
}
?>