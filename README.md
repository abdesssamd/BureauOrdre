# Gestion des Fichiers et Courrier Administratif

Application web PHP/MySQL pour la gestion documentaire interne et le suivi du courrier administratif (arrivee/depart), avec partage, attribution de taches, notifications et OCR.

## Fonctionnalites principales

- Authentification multi-profils: `admin`, `directeur`, `secretaire`, `employe`.
- Gestion des utilisateurs, services (departements) et droits d'acces.
- Gestion documentaire:
  - upload de fichiers,
  - classement par dossiers,
  - visibilite `public` / `private` / `department`,
  - partage utilisateur / service / groupe,
  - etiquettes (tags) sur fichiers.
- Bureau d'ordre:
  - enregistrement des courriers `arrivee` et `depart`,
  - numero de reference unique,
  - suivi du statut (`nouveau`, `en_cours`, `traite`, `archive`),
  - liaison optionnelle avec un document scanne.
- Workflow d'attribution:
  - affectation a un utilisateur ou a un service,
  - instruction, delai, retour de traitement,
  - historique via commentaires.
- Messagerie interne:
  - conversations,
  - pieces jointes,
  - statut de lecture/presence/saisie.
- Notifications en temps reel applicatif (partage, upload, systeme).
- OCR avance pour extraction de texte sur PDF/images (texte imprime ET manuscrit).
- Interface multilingue FR/AR.

## Stack technique

- PHP 8.x (XAMPP compatible)
- MySQL / MariaDB
- Composer (dependance OCR PHP)
- Tesseract OCR + Ghostscript (option OCR PDF/image)
- Python 3.x + EasyOCR + TrOCR (OCR avance, optionnel)
- HTML/CSS/JS (frontend)

## Structure du projet

- `public/` : point d'entree web et pages applicatives
- `config/` : configuration base de donnees
- `includes/` : helpers (auth, OCR, notifications, etc.)
- `sql/` : scripts SQL d'installation
- `uploads/` : stockage des documents envoyes
- `vendor/` : dependances Composer

## Installation locale (XAMPP)

### 1) Cloner le projet

```bash
git clone https://github.com/abdesssamd/BureauOrdre
cd BureauOrdre
```

### 2) Installer les dependances PHP

```bash
composer install
```

### 3) Configurer l'environnement

1. Copier `env_example.txt` vers `.env`.
2. Renseigner vos parametres:

```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=gestion_fichiers
DB_USER=root
DB_PASS=
```

Important: ne jamais versionner le fichier `.env`.

### 4) Creer la base (auto-install)

L'application tente de creer la base et les tables automatiquement au premier lancement via `config/db.php`.

Conditions minimales:
- l'utilisateur MySQL doit avoir les droits de creation de base/table,
- les scripts SQL doivent etre presents dans `sql/install_complete.sql` (ou `sql/schema.sql`).

### 5) Lancer l'application

- Demarrer Apache + MySQL dans XAMPP.
- Ouvrir:
  - `http://localhost/BureauOrdre/public/install.php` (si premiere installation),
  - puis `http://localhost/BureauOrdre/public/index.php`.

---

## OCR — Module d'extraction de texte (v3.0)

Le module OCR supporte deux types de documents :
- **Texte imprime** : factures, courriers administratifs, formulaires numerises
- **Ecriture manuscrite** : lettres manuscrites, formulaires remplis a la main

### Architecture du pipeline

```
Image / PDF recu
      │
      ▼
  [PHP] ocr_helper.php
      │
      ├── PDF → Ghostscript → PNG (pnggray 300 DPI)
      │
      ▼
  [Python] ocr.py
      │
      ▼
  detect_handwriting()   ← 3 indicateurs : variance angles,
      │                    densite traits, regularite lignes
      │
      ├── Manuscrit (score > 0.45)
      │       │
      │       ▼
      │   TrOCR (Microsoft)
      │   • Segmentation en lignes
      │   • Traitement ligne par ligne
      │   • 85-92% precision sur manuscrit latin/francais
      │       │
      │       └── confiance < 20% → EasyOCR fallback
      │
      └── Imprime
              │
              ▼
          EasyOCR
          • Double passe AR puis FR (evite melange RTL/LTR)
          • paragraph=False (ordre de lecture natif)
              │
              └── confiance < 30% → Tesseract multi-PSM fallback
```

### Statuts de qualite OCR

| Statut | Signification |
|--------|---------------|
| `ok` | Confiance >= 45%, texte exploitable |
| `low_confidence` | Confiance 25-44%, a verifier |
| `handwritten_manual_review` | Manuscrit avec confiance < 25%, relecture manuelle recommandee |
| `failed` | Extraction echouee, texte vide |

### Composants requis

**Tesseract OCR** (obligatoire pour le fallback PHP) :
- Windows : `C:/Tesseract-OCR/tesseract.exe`
- Telecharger : https://github.com/UB-Mannheim/tesseract/wiki

**Ghostscript** (pour conversion PDF) :
- Windows : `C:/Program Files/gs/gs10.06.0/bin/gswin64c.exe`
- Telecharger : https://www.ghostscript.com/releases/

**Python + bibliotheques OCR** (pour EasyOCR et TrOCR) :

```bash
pip install -r includes/requirements_ocr.txt
```

Ou installation manuelle :

```bash
# Minimum (EasyOCR seulement — texte imprime)
pip install easyocr opencv-python-headless pillow numpy

# Complet avec TrOCR (+ ecriture manuscrite, ~400 MB)
pip install transformers torch torchvision pillow
```

> Le modele TrOCR (`microsoft/trocr-base-handwritten`) est telecharge automatiquement
> au premier appel et mis en cache dans `~/.cache/huggingface/`.
> Pour le modele large (~1.4 GB, meilleure precision) :
> `python ocr.py image.jpg --trocr-model large`

### Traitement OCR par lot (job_ocr.php)

Traite les fichiers en statut `pending` par lots de 10 :

```bash
php includes/job_ocr.php
```

Planifier la commande :
- **Windows** : Gestionnaire de taches (Task Scheduler)
- **Linux** : `crontab -e` → `*/5 * * * * php /chemin/includes/job_ocr.php`

### Utilisation directe de ocr.py

```bash
# Mode automatique (detection manuscrit/imprime automatique)
python includes/ocr.py document.jpg --json

# Forcer mode manuscrit (TrOCR)
python includes/ocr.py lettre.jpg --json --force-handwritten

# Forcer mode imprime (EasyOCR)
python includes/ocr.py facture.jpg --json --force-printed

# Modele TrOCR large (plus precis)
python includes/ocr.py lettre.jpg --json --force-handwritten --trocr-model large

# Langues specifiques
python includes/ocr.py doc.jpg --json --lang ar+fr+en
```

Exemple de sortie JSON :

```json
{
  "text": "A Monsieur le Directeur\nCOMPLITIME...",
  "confidence": 87.3,
  "engine": "trocr-base",
  "quality": "ok",
  "is_handwritten": true,
  "handwriting_score": 0.72,
  "word_count": 142
}
```

### Corrections apportees (v2.0 → v3.0)

| Fichier | Probleme | Fix |
|---------|----------|-----|
| `ocr.py` | Pas de support manuscrit | TrOCR integre avec detection automatique |
| `ocr.py` | `paragraph=True` cassait l'ordre bilingue | `paragraph=False` + tri par position Y |
| `ocr.py` | Une seule passe `ar+fr` melangee | Double passe AR seul puis FR seul |
| `ocr_helper.php` | `pngmono` 1-bit detruisait les details | `pnggray` 8-bit a 300 DPI |
| `ocr_helper.php` | PSM 3 unique | Multi-PSM : 6 → 3 → 4 |
| `ocr_helper.php` | `cleanOutput()` trop agressive | Suppression ciblee caracteres de controle seulement |
| `ocr_helper.php` | Pas de score de confiance | `confidence` + `quality` retournes |
| `job_ocr.php` | Statut `done` meme si confiance tres basse | Statuts `low_confidence` et `error` distincts |
| `mail_add.php` | PDF non traite en OCR synchrone | PDF ajoute a la liste des extensions OCR |
| `mail_add_marche.php` | Appel `extractTextFromImage()` inexistante | Remplace par `extractTextWithMeta()` |

---

## Notes

- Le projet contient aussi des scripts Python d'agent IA (`main.py`, `gemini_agent.py`) avec leur propre documentation dans `README_GEMINI.md`.
- Cette partie est optionnelle pour le fonctionnement de l'application web PHP.
