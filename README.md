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
- OCR (optionnel) pour extraction de texte sur PDF/images via Tesseract.
- Interface multilingue FR/AR.

## Stack technique

- PHP 8.x (XAMPP compatible)
- MySQL / MariaDB
- Composer (dependance OCR PHP)
- Tesseract OCR + Ghostscript (option OCR PDF/image)
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

## OCR (optionnel)

Le module OCR utilise:
- `C:/Tesseract-OCR/tesseract.exe`
- `C:/Program Files/gs/gs10.06.0/bin/gswin64c.exe`

Traitement OCR par lot:

```bash
php includes/job_ocr.php
```

Vous pouvez planifier cette commande (Task Scheduler Windows / cron Linux).


## Notes

- Le projet contient aussi des scripts Python d'agent IA (`main.py`, `gemini_agent.py`) avec leur propre documentation dans `README_GEMINI.md`.
- Cette partie est optionnelle pour le fonctionnement de l'application web PHP.
