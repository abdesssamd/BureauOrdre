# 🚀 Guide Rapide - Utiliser Gemini Agent dans Cursor

## ⚡ Démarrage Rapide

### 1️⃣ Configuration Initiale

1. **Installer les dépendances** :
   ```bash
   pip install -r requirements.txt
   ```

2. **Créer le fichier `.env`** :
   ```env
   GEMINI_API_KEY=votre_clé_api_ici
   GEMINI_MODEL=gemini-1.5-pro
   ```

### 2️⃣ Utilisation dans Cursor

#### Méthode la plus simple : Via les Tâches

1. **Appuyez sur** : `Ctrl+Shift+P` (Windows) ou `Cmd+Shift+P` (Mac)
2. **Tapez** : `Tasks: Run Task`
3. **Choisissez** une des options :
   - 🤖 **Gemini Agent - Chat** → Mode conversation
   - 🤖 **Gemini Agent - Query** → Question rapide
   - 🤖 **Gemini Agent - Review Code** → Réviser le fichier ouvert
   - 🤖 **Gemini Agent - Explain Code** → Expliquer le fichier ouvert

#### Via le Terminal

Ouvrez le terminal (`Ctrl+``) et utilisez :

```bash
# Chat interactif
python cursor_agent.py chat

# Question unique
python cursor_agent.py query "Votre question ici"

# Réviser un fichier
python cursor_agent.py review mon_fichier.py

# Expliquer un fichier
python cursor_agent.py explain mon_fichier.py
```

## 📋 Exemples Concrets

### Réviser votre code

1. Ouvrez `gemini_agent.py`
2. `Ctrl+Shift+P` → `Tasks: Run Task` → `🤖 Gemini Agent - Review Code`
3. L'agent analysera et donnera des suggestions

### Poser une question rapide

1. `Ctrl+Shift+P` → `Tasks: Run Task` → `🤖 Gemini Agent - Query`
2. Tapez : "Comment optimiser cette fonction pour les performances?"
3. L'agent répondra directement

### Expliquer du code complexe

1. Ouvrez un fichier avec du code complexe
2. `Ctrl+Shift+P` → `Tasks: Run Task` → `🤖 Gemini Agent - Explain Code`
3. L'agent expliquera chaque partie du code

## 🎯 Cas d'Usage Fréquents

| Besoin | Action |
|--------|--------|
| Comprendre du code | `Explain Code` |
| Améliorer du code | `Review Code` |
| Question rapide | `Query` |
| Conversation approfondie | `Chat` |
| Debugging | `Query` + "Pourquoi ce code ne fonctionne pas?" |

## ⚙️ Raccourci Clavier (Optionnel)

Pour créer un raccourci clavier :

1. `Ctrl+K Ctrl+S` (ouvrir les raccourcis)
2. Cherchez `workbench.action.tasks.runTask`
3. Ajoutez un raccourci (ex: `Ctrl+Alt+G`)
4. Dans les arguments, mettez : `"🤖 Gemini Agent - Chat"`

## ❓ Problèmes Courants

**L'agent ne se lance pas ?**
- Vérifiez que Python est installé : `python --version`
- Installez les dépendances : `pip install -r requirements.txt`
- Vérifiez le fichier `.env` avec votre clé API

**Erreur "Module not found" ?**
- `pip install google-generativeai python-dotenv`

**L'agent ne répond pas ?**
- Vérifiez votre connexion Internet
- Vérifiez que votre clé API est valide

## 📚 Documentation Complète

Voir `CURSOR_INTEGRATION.md` pour plus de détails.
