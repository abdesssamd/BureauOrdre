#!/usr/bin/env python3
"""
ocr.py — Pipeline OCR v3.0 pour gestion_fichiers
Supporte : texte imprimé (EasyOCR + Tesseract) ET écriture manuscrite (TrOCR).

NOUVEAUTÉ v3.0 — TrOCR (Microsoft) pour manuscrit :
  - Détection automatique manuscrit vs imprimé
  - TrOCR base/large-handwritten : 85-92% précision sur manuscrit latin
  - Segmentation par lignes avant envoi à TrOCR (modèle ligne par ligne)
  - Cache du modèle dans ~/.cache/huggingface/ (téléchargé une seule fois)
  - Fallback gracieux si transformers/torch non installés

Pipeline de décision :
  1. Détecter si manuscrit → TrOCR
  2. Sinon → EasyOCR (double passe AR/FR)
  3. Si confiance < 30% → Tesseract multi-PSM
  4. quality = 'handwritten_manual_review' si < 25%

Usage :
  python ocr.py <image> [--json] [--lang ar+fr] [--force-handwritten] [--force-printed]
  python ocr.py doc.jpg --json --force-handwritten --trocr-model large

Installation TrOCR (une seule fois, ~400MB) :
  pip install transformers torch torchvision pillow
"""

import sys
import os
import json
import argparse
import logging
import re

logging.basicConfig(level=logging.WARNING)
logger = logging.getLogger(__name__)

TROCR_MODELS = {
    "large":  "microsoft/trocr-large-handwritten",
    "base":   "microsoft/trocr-base-handwritten",
    "stage1": "microsoft/trocr-large-stage1",
}
_trocr_cache = {}


# ═══════════════════════════════════════════════════════════════════════════════
# 1. PRÉTRAITEMENT
# ═══════════════════════════════════════════════════════════════════════════════

def upscale_if_needed(img, min_height=1200):
    import cv2
    h, w = img.shape[:2]
    if h < min_height:
        scale = min_height / h
        img = cv2.resize(img, (int(w * scale), min_height), interpolation=cv2.INTER_CUBIC)
    return img


def deskew(img):
    import cv2, numpy as np
    try:
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY) if len(img.shape) == 3 else img
        _, thresh = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
        kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (50, 3))
        dilated = cv2.dilate(thresh, kernel, iterations=1)
        contours, _ = cv2.findContours(dilated, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        if not contours:
            return img
        h, w = img.shape[:2]
        min_area = w * h * 0.001
        angles = []
        for c in contours:
            if cv2.contourArea(c) > min_area:
                angle = cv2.minAreaRect(c)[-1]
                if angle < -45: angle += 90
                if abs(angle) < 10:
                    angles.append(angle)
        if not angles:
            return img
        angle = float(sum(angles) / len(angles))
        if abs(angle) < 0.5:
            return img
        M = cv2.getRotationMatrix2D((w // 2, h // 2), angle, 1.0)
        return cv2.warpAffine(img, M, (w, h), flags=cv2.INTER_CUBIC,
                               borderMode=cv2.BORDER_REPLICATE)
    except Exception:
        return img


def preprocess_for_printed(img):
    """Binarisation forte pour texte imprimé."""
    import cv2, numpy as np
    img = upscale_if_needed(img, 1200)
    img = deskew(img)
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY) if len(img.shape) == 3 else img
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    gray = clahe.apply(gray)
    gray = cv2.fastNlMeansDenoising(gray, h=10, templateWindowSize=7, searchWindowSize=21)
    _, otsu = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)
    adaptive = cv2.adaptiveThreshold(gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
                                      cv2.THRESH_BINARY, 31, 10)
    combined = cv2.bitwise_and(otsu, adaptive)
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (2, 2))
    return cv2.morphologyEx(combined, cv2.MORPH_OPEN, kernel)


def preprocess_for_handwritten(img):
    """
    Prétraitement doux pour manuscrit.
    Évite la binarisation dure qui détruit les traits fins au stylo.
    """
    import cv2
    img = upscale_if_needed(img, 1600)
    img = deskew(img)
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY) if len(img.shape) == 3 else img
    clahe = cv2.createCLAHE(clipLimit=1.5, tileGridSize=(8, 8))
    gray = clahe.apply(gray)
    gray = cv2.fastNlMeansDenoising(gray, h=7, templateWindowSize=7, searchWindowSize=21)
    # Seuillage adaptatif seul (Otsu écrase les variations de pression du stylo)
    result = cv2.adaptiveThreshold(gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
                                    cv2.THRESH_BINARY, 25, 8)
    return result


# ═══════════════════════════════════════════════════════════════════════════════
# 2. DÉTECTION MANUSCRIT vs IMPRIMÉ
# ═══════════════════════════════════════════════════════════════════════════════

def detect_handwriting(img) -> tuple:
    """
    Détecte écriture manuscrite vs imprimée.
    Combine 3 indicateurs : variance des angles, densité traits, régularité lignes.
    Retourne (is_handwritten: bool, score: float 0-1).
    """
    import cv2, numpy as np

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY) if len(img.shape) == 3 else img
    h, w = gray.shape
    score = 0.0

    # Indicateur 1 : variance des angles de segments (manuscrit = irrégulier)
    edges = cv2.Canny(gray, 50, 150)
    lines = cv2.HoughLinesP(edges, 1, np.pi / 180, threshold=20,
                             minLineLength=10, maxLineGap=5)
    if lines is not None and len(lines) > 10:
        angles = []
        for line in lines:
            x1, y1, x2, y2 = line[0]
            if x2 - x1 != 0:
                angles.append(abs(np.degrees(np.arctan2(y2 - y1, x2 - x1))) % 90)
        if angles:
            score += min(1.0, np.var(angles) / 400) * 0.4

    # Indicateur 2 : densité pixels sombres (manuscrit = plus clairsemé)
    _, binary = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
    dark_ratio = np.sum(binary > 0) / (h * w)
    if 0.03 < dark_ratio < 0.15:
        score += 0.3
    elif dark_ratio < 0.03:
        score += 0.5

    # Indicateur 3 : irrégularité de l'espacement entre lignes
    row_means = np.mean(binary, axis=1)
    peaks = np.where((row_means[1:-1] > row_means[:-2]) &
                     (row_means[1:-1] > row_means[2:]) &
                     (row_means[1:-1] > 10))[0]
    if len(peaks) > 1:
        gaps = np.diff(peaks)
        gap_variance = np.var(gaps) / (np.mean(gaps) ** 2 + 1)
        if gap_variance > 0.1:
            score += 0.3

    is_hw = score > 0.45
    logger.info(f"Détection: score={score:.2f} → {'manuscrit' if is_hw else 'imprimé'}")
    return is_hw, score


# ═══════════════════════════════════════════════════════════════════════════════
# 3. SEGMENTATION EN LIGNES (pour TrOCR)
# ═══════════════════════════════════════════════════════════════════════════════

def segment_lines(img) -> list:
    """
    Découpe l'image en lignes de texte individuelles.
    TrOCR traite une ligne à la fois — cette étape est essentielle
    pour obtenir de bons résultats.
    Retourne une liste d'images PIL.
    """
    import cv2, numpy as np
    from PIL import Image

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY) if len(img.shape) == 3 else img
    _, binary = cv2.threshold(gray, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)

    # Dilatation horizontale pour regrouper mots → lignes
    kernel = cv2.getStructuringElement(cv2.MORPH_RECT, (max(5, int(img.shape[1] * 0.04)), 3))
    dilated = cv2.dilate(binary, kernel, iterations=2)

    contours, _ = cv2.findContours(dilated, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    if not contours:
        return [Image.fromarray(gray).convert("RGB")]

    bboxes = sorted([cv2.boundingRect(c) for c in contours], key=lambda b: b[1])
    h_img, w_img = img.shape[:2]
    lines = []

    for (x, y, w, h) in bboxes:
        # Filtrer le bruit (trop petit) et les boîtes trop grandes
        if h < 10 or h > h_img * 0.18 or w < 30:
            continue
        pad = 6
        y1, y2 = max(0, y - pad), min(h_img, y + h + pad)
        x1, x2 = max(0, x - pad), min(w_img, x + w + pad)
        line_crop = gray[y1:y2, x1:x2]
        lines.append(Image.fromarray(line_crop).convert("RGB"))

    logger.info(f"Segmentation: {len(lines)} lignes")
    return lines if lines else [Image.fromarray(gray).convert("RGB")]


# ═══════════════════════════════════════════════════════════════════════════════
# 4. MOTEUR TrOCR
# ═══════════════════════════════════════════════════════════════════════════════

def _load_trocr(model_size="base"):
    """Charge TrOCR avec cache mémoire. Télécharge au premier appel."""
    global _trocr_cache
    if model_size in _trocr_cache:
        return _trocr_cache[model_size]
    from transformers import TrOCRProcessor, VisionEncoderDecoderModel
    name = TROCR_MODELS.get(model_size, TROCR_MODELS["base"])
    logger.info(f"Chargement TrOCR {model_size} ({name})...")
    processor = TrOCRProcessor.from_pretrained(name)
    model = VisionEncoderDecoderModel.from_pretrained(name)
    model.eval()
    _trocr_cache[model_size] = (processor, model)
    return processor, model


def run_trocr(img, model_size="base") -> tuple:
    """
    OCR manuscrit avec TrOCR Microsoft.
    Segmente en lignes, traite chaque ligne, assemble le résultat.
    Retourne (texte, confiance_0_100).
    """
    try:
        import torch
    except ImportError:
        logger.warning("TrOCR indisponible — pip install transformers torch")
        return None, 0.0

    try:
        processor, model = _load_trocr(model_size)
    except Exception as e:
        logger.warning(f"Chargement TrOCR échoué: {e}")
        return None, 0.0

    try:
        import math
        img_preprocessed = preprocess_for_handwritten(img)
        lines = segment_lines(img_preprocessed)

        all_text, all_scores = [], []

        for i, line_pil in enumerate(lines):
            try:
                pixel_values = processor(images=line_pil,
                                          return_tensors="pt").pixel_values
                with torch.no_grad():
                    outputs = model.generate(
                        pixel_values,
                        num_beams=4,
                        max_length=128,
                        output_scores=True,
                        return_dict_in_generate=True,
                    )
                line_text = processor.batch_decode(
                    outputs.sequences, skip_special_tokens=True)[0].strip()

                # Score de confiance depuis log-probabilité de la séquence
                if (hasattr(outputs, 'sequences_scores') and
                        outputs.sequences_scores is not None):
                    conf = max(0.0, min(100.0, math.exp(
                        outputs.sequences_scores[0].item()) * 100))
                else:
                    conf = 70.0

                if line_text:
                    all_text.append(line_text)
                    all_scores.append(conf)

            except Exception as e:
                logger.debug(f"TrOCR ligne {i}: {e}")
                continue

        if not all_text:
            return None, 0.0

        full_text = "\n".join(all_text)
        avg_conf = sum(all_scores) / len(all_scores)
        logger.info(f"TrOCR: {len(all_text)} lignes, conf={avg_conf:.1f}%")
        return full_text, round(avg_conf, 2)

    except Exception as e:
        logger.warning(f"TrOCR erreur: {e}")
        return None, 0.0


# ═══════════════════════════════════════════════════════════════════════════════
# 5. MOTEUR EasyOCR (IMPRIMÉ)
# ═══════════════════════════════════════════════════════════════════════════════

def run_easyocr(img_preprocessed, langs) -> tuple:
    """Double passe EasyOCR : AR seul + FR seul pour éviter mélange RTL/LTR."""
    try:
        import easyocr
    except ImportError:
        return None, 0.0

    lang_groups = []
    non_ar = [l for l in langs if l != 'ar']
    if 'ar' in langs: lang_groups.append(['ar'])
    if non_ar: lang_groups.append(non_ar)
    if not lang_groups: lang_groups = [langs]

    best_text, best_conf = "", 0.0

    for lang_group in lang_groups:
        try:
            reader = easyocr.Reader(lang_group, gpu=False, verbose=False)
            results = reader.readtext(img_preprocessed, detail=1, paragraph=False)
            if not results:
                continue
            filtered = sorted(
                [(b, t, c) for b, t, c in results if c > 0.15],
                key=lambda r: r[0][0][1]
            )
            if not filtered:
                continue
            confs = [c for _, _, c in filtered]
            avg_conf = sum(confs) / len(confs)
            text = "\n".join(t for _, t, _ in filtered)
            if avg_conf > best_conf:
                best_conf, best_text = avg_conf, text
        except Exception as e:
            logger.warning(f"EasyOCR ({lang_group}): {e}")

    return best_text, round(best_conf * 100, 2)


# ═══════════════════════════════════════════════════════════════════════════════
# 6. FALLBACK TESSERACT
# ═══════════════════════════════════════════════════════════════════════════════

def run_tesseract_fallback(image_path, langs) -> tuple:
    """Tesseract multi-PSM avec double passe FR/AR."""
    try:
        import pytesseract, cv2
    except ImportError:
        return None, 0.0

    if not os.environ.get('TESSDATA_PREFIX'):
        for c in ['/usr/share/tesseract-ocr/5/tessdata',
                  '/usr/share/tesseract-ocr/4.00/tessdata',
                  'C:/Tesseract-OCR/tessdata']:
            if os.path.isdir(c):
                os.environ['TESSDATA_PREFIX'] = c
                break

    try:
        img = cv2.imread(image_path)
        if img is None:
            return None, 0.0
        processed = preprocess_for_printed(img)

        lang_map = {'ar': 'ara', 'fr': 'fra', 'en': 'eng'}
        tess_langs = [lang_map.get(l, l) for l in langs]
        try:
            available = pytesseract.get_languages()
        except Exception:
            available = ['eng']
        tess_langs = [l for l in tess_langs if l in available] or ['eng']

        non_ar = [l for l in tess_langs if l != 'ara']
        passes = []
        if non_ar: passes.append('+'.join(non_ar))
        if 'ara' in tess_langs: passes.append('ara')

        best_text, best_conf = "", 0.0
        for lang_str in passes:
            for psm in [6, 3, 4]:
                try:
                    config = f"--oem 3 --psm {psm}"
                    data = pytesseract.image_to_data(
                        processed, lang=lang_str, config=config,
                        output_type=pytesseract.Output.DICT)
                    confs = [int(c) for c in data['conf'] if int(c) > 10]
                    avg_conf = sum(confs) / len(confs) if confs else 0.0
                    if avg_conf > best_conf:
                        best_conf = avg_conf
                        best_text = pytesseract.image_to_string(
                            processed, lang=lang_str, config=config)
                    if best_conf >= 70:
                        break
                except Exception:
                    continue
        return best_text.strip(), round(best_conf, 2)
    except Exception as e:
        logger.warning(f"Tesseract: {e}")
        return None, 0.0


# ═══════════════════════════════════════════════════════════════════════════════
# 7. NETTOYAGE & MAIN
# ═══════════════════════════════════════════════════════════════════════════════

def clean_text(text):
    if not text:
        return ""
    text = re.sub(r'[\x00-\x08\x0b\x0c\x0e-\x1f\x7f-\x9f]', '', text)
    text = re.sub(r'[ \t]{3,}', '  ', text)
    text = re.sub(r'\n{3,}', '\n\n', text)
    return text.strip()


def main():
    parser = argparse.ArgumentParser(description="OCR v3.0 avec TrOCR manuscrit")
    parser.add_argument('file_path')
    parser.add_argument('--json', action='store_true')
    parser.add_argument('--lang', default='ar+fr')
    parser.add_argument('--force-handwritten', action='store_true')
    parser.add_argument('--force-printed', action='store_true')
    parser.add_argument('--trocr-model', default='base',
                        choices=['base', 'large', 'stage1'])
    args = parser.parse_args()

    langs = [l.strip() for l in args.lang.split('+') if l.strip()]

    if not os.path.exists(args.file_path):
        out = {"error": f"Introuvable: {args.file_path}", "text": "", "confidence": 0}
        print(json.dumps(out, ensure_ascii=False) if args.json else "")
        sys.exit(1)

    try:
        import cv2
        img = cv2.imread(args.file_path)
        if img is None:
            raise ValueError("Impossible de lire l'image")

        text, confidence, engine = "", 0.0, "none"

        # Détection manuscrit / imprimé
        if args.force_handwritten:
            is_hw, hw_score = True, 1.0
        elif args.force_printed:
            is_hw, hw_score = False, 0.0
        else:
            is_hw, hw_score = detect_handwriting(img)

        # Branche manuscrit → TrOCR
        if is_hw:
            text, confidence = run_trocr(img, model_size=args.trocr_model)
            engine = f"trocr-{args.trocr_model}"
            # Fallback EasyOCR si TrOCR échoue
            if not text or confidence < 20:
                processed = preprocess_for_printed(img)
                text, confidence = run_easyocr(processed, langs)
                engine = "easyocr-fallback"
        # Branche imprimé → EasyOCR
        else:
            processed = preprocess_for_printed(img)
            text, confidence = run_easyocr(processed, langs)
            engine = "easyocr"

        # Fallback Tesseract si confiance insuffisante
        if not text or confidence < 30:
            tess_text, tess_conf = run_tesseract_fallback(args.file_path, langs)
            if tess_text and tess_conf > confidence:
                text, confidence, engine = tess_text, tess_conf, "tesseract-fallback"

        text = clean_text(text or "")

        # Qualité
        if not text or len(text) < 10:
            quality = "failed"
        elif confidence < 25:
            quality = "handwritten_manual_review"
        elif confidence < 45:
            quality = "low_confidence"
        else:
            quality = "ok"

        if args.json:
            print(json.dumps({
                "text": text,
                "confidence": confidence,
                "engine": engine,
                "quality": quality,
                "is_handwritten": is_hw,
                "handwriting_score": round(hw_score, 2),
                "word_count": len(text.split()) if text else 0,
            }, ensure_ascii=False))
        else:
            print(text)

    except Exception as e:
        if args.json:
            print(json.dumps({"error": str(e), "text": "", "confidence": 0},
                             ensure_ascii=False))
        else:
            print(f"Erreur: {e}", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
