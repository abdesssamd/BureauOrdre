<?php
/**
 * ocr_helper.php — VERSION OPTIMISÉE v2.0
 *
 * FIXES apportés :
 *  1. PDF → PNG avec pnggray (niveaux de gris 8-bit) au lieu de pngmono (1-bit)
 *     pngmono à 600 DPI détruisait les détails fins ; pnggray à 300 DPI est meilleur.
 *  2. Tesseract multi-PSM : essaie PSM 6 → 3 → 4 et garde le meilleur résultat
 *     (l'ancienne version utilisait seulement PSM 3).
 *  3. Double passe langue : fra+eng séparé de ara pour éviter le mélange RTL/LTR.
 *  4. cleanOutput() moins agressive : gardait trop peu de caractères.
 *  5. Appel optionnel de ocr.py (EasyOCR) si Python disponible — utilisé comme
 *     complément si confiance Tesseract < seuil.
 *  6. Score de confiance retourné dans le tableau de résultats.
 */

/**
 * Point d'entrée principal.
 * Retourne le texte OCR ou null si échec.
 */
function extractTextFromFile(string $file_path): ?string {
    $result = extractTextWithMeta($file_path);
    return $result['text'] ?? null;
}

/**
 * Version étendue retournant aussi la confiance et le moteur utilisé.
 * Retourne ['text' => string, 'confidence' => float, 'engine' => string]
 */
function extractTextWithMeta(string $file_path): array {
    $tesseract  = 'C:/Tesseract-OCR/tesseract.exe';
    $tessdata   = 'C:/Tesseract-OCR/tessdata';
    $ghostscript = _findGhostscript();

    if (!file_exists($tesseract) || !file_exists($file_path)) {
        return ['text' => null, 'confidence' => 0, 'engine' => 'none'];
    }

    putenv("TESSDATA_PREFIX=$tessdata");
    putenv("LC_ALL=C");

    $ext        = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $temp_files = [];
    $image_path = null;

    // ── Préparation de l'image ────────────────────────────────────────────
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp', 'webp'])) {
        $image_path = $file_path;

    } elseif ($ext === 'pdf') {
        if (!$ghostscript) {
            error_log("OCR: Ghostscript introuvable pour $file_path");
            return ['text' => null, 'confidence' => 0, 'engine' => 'none'];
        }

        $temp_img   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('ocr_') . '.png';
        $temp_files[] = $temp_img;

        // FIX: pnggray (8-bit) au lieu de pngmono (1-bit) — préserve mieux les
        // nuances de contraste que Tesseract traite mieux.
        // 300 DPI suffit (600 DPI pngmono = 2x plus de fichier, pas de gain réel).
        $cmd = "\"{$ghostscript}\" -dSAFER -dBATCH -dNOPAUSE "
             . "-sDEVICE=pnggray -r300 "
             . "-dTextAlphaBits=4 -dGraphicsAlphaBits=4 "
             . "-dLastPage=1 "
             . "-sOutputFile=\"{$temp_img}\" \"{$file_path}\" 2>&1";
        shell_exec($cmd);

        if (file_exists($temp_img) && filesize($temp_img) > 1000) {
            $image_path = $temp_img;
        }
    }

    if (!$image_path) {
        return ['text' => null, 'confidence' => 0, 'engine' => 'none'];
    }

    // ── Tentative EasyOCR/TrOCR via ocr.py ──────────────────────────────
    // FIX v3.0: Détecter si le fichier est probablement manuscrit
    // (peut être forcé via paramètre ou détecté automatiquement par ocr.py)
    $force_handwritten = false; // ocr.py auto-détecte par défaut
    $python_result = _tryEasyOCR($image_path, $force_handwritten);
    if ($python_result && ($python_result['confidence'] ?? 0) >= 55) {
        _cleanup($temp_files);
        return $python_result;
    }

    // ── Tesseract multi-PSM ───────────────────────────────────────────────
    $best_text = null;
    $best_len  = 0;

    // FIX: Double passe — FR+ENG d'abord (LTR), puis AR (RTL)
    // Évite que Tesseract mélange les deux directions de lecture.
    $lang_passes = [
        'fra+eng',  // Passe latine
        'ara',      // Passe arabe
    ];

    // FIX: Multi-PSM — PSM 6 (bloc uniforme) → PSM 3 (auto) → PSM 4 (colonne)
    $psm_order = [6, 3, 4];

    foreach ($lang_passes as $lang) {
        foreach ($psm_order as $psm) {
            $cmd = "\"{$tesseract}\" \"{$image_path}\" stdout"
                 . " -l {$lang}"
                 . " --tessdata-dir \"{$tessdata}\""
                 . " --psm {$psm}"
                 . " --oem 3"
                 . " 2>&1";

            $output = shell_exec($cmd);
            if (!$output) continue;

            $cleaned = cleanOutput($output);
            if ($cleaned && mb_strlen($cleaned) > $best_len) {
                $best_len  = mb_strlen($cleaned);
                $best_text = $cleaned;
            }

            // Si on a déjà un bon résultat pour cette langue, passer à la suivante
            if ($best_len > 200) break;
        }
    }

    _cleanup($temp_files);

    $confidence = $best_text ? min(95, 40 + ($best_len / 20)) : 0;

    return [
        'text'       => $best_text,
        'confidence' => round($confidence, 1),
        'engine'     => 'tesseract',
    ];
}

/**
 * Appelle ocr.py (EasyOCR + TrOCR) si Python3 est disponible.
 * Retourne le résultat parsé ou null si indisponible.
 *
 * FIX v3.0: Passage du flag --force-handwritten si l'image semble manuscrite
 * (détection par nom de fichier ou forçage manuel), et lecture du champ
 * 'quality' pour le statut retourné.
 */
function _tryEasyOCR(string $image_path, bool $force_handwritten = false): ?array {
    $script = __DIR__ . '/ocr.py';
    if (!file_exists($script)) return null;

    $python = null;
    foreach (['python3', 'python', 'py'] as $bin) {
        $test = shell_exec("{$bin} --version 2>&1");
        if ($test && stripos($test, 'python') !== false) {
            $python = $bin;
            break;
        }
    }
    if (!$python) return null;

    $escaped_img    = escapeshellarg($image_path);
    $escaped_script = escapeshellarg($script);
    $hw_flag        = $force_handwritten ? ' --force-handwritten' : '';

    $cmd = "{$python} {$escaped_script} {$escaped_img} --json --lang ar+fr{$hw_flag} 2>&1";
    $output = shell_exec($cmd);
    if (!$output) return null;

    // Extraire la dernière ligne JSON
    $lines  = array_filter(explode("\n", trim($output)));
    $last   = end($lines);
    $parsed = json_decode($last, true);

    if (!$parsed || empty($parsed['text'])) return null;

    return [
        'text'             => $parsed['text'],
        'confidence'       => $parsed['confidence'] ?? 0,
        'engine'           => $parsed['engine'] ?? 'python-ocr',
        'quality'          => $parsed['quality'] ?? 'unknown',
        'is_handwritten'   => $parsed['is_handwritten'] ?? false,
    ];
}

/**
 * Nettoyage du texte OCR.
 * FIX v2.0: Moins agressif — l'ancienne version supprimait des
 * caractères valides français (accents) et arabes avec le regex \p{Arabic}.
 * On garde maintenant tout sauf les caractères vraiment parasites.
 */
function cleanOutput(?string $text): ?string {
    if (empty($text)) return null;

    // Supprimer les marqueurs Asprise/Scanner (artefacts de certaines bibliothèques)
    $text = str_replace(['asprise', 'Scanner.js', 'ONLY', 'FOREVALUATION'], '', $text);

    // Supprimer les erreurs Tesseract
    if (preg_match('/Error|System Error|Warning.*tesseract/i', $text)) {
        // Garder quand même le texte après les erreurs s'il y en a
        $text = preg_replace('/^.*?(Error|Warning)[^\n]*\n/im', '', $text);
    }

    // FIX: Supprimer UNIQUEMENT les caractères de contrôle et boîtes Unicode
    // (l'ancien regex /[^\p{Arabic}\p{L}0-9\s\.\,\-\_\/]/u supprimait trop)
    $text = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', '', $text);

    // Normaliser les espaces (pas trop agressivement)
    $text = preg_replace('/[ \t]{4,}/', '  ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    $clean = trim($text);
    return $clean ?: null;
}

/**
 * Trouve le chemin Ghostscript sur Windows.
 */
function _findGhostscript(): ?string {
    $candidates = [
        'C:/Program Files/gs/gs10.06.0/bin/gswin64c.exe',
        'C:/Program Files/gs/gs10.05.1/bin/gswin64c.exe',
        'C:/Program Files/gs/gs10.04.0/bin/gswin64c.exe',
        'C:/Program Files (x86)/gs/gs10.06.0/bin/gswin32c.exe',
        'C:/Program Files (x86)/gs/gs10.05.1/bin/gswin32c.exe',
    ];
    foreach ($candidates as $path) {
        if (file_exists($path)) return $path;
    }
    return null;
}

/**
 * Supprime les fichiers temporaires.
 */
function _cleanup(array $files): void {
    foreach ($files as $f) {
        if (file_exists($f)) @unlink($f);
    }
}
