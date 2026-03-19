# Intégration de l'Agent Gemini dans Cursor IDE

Ce guide explique comment utiliser l'agent Gemini directement dans Cursor IDE.

## 🚀 Méthodes d'utilisation

### Méthode 1 : Via les Tâches (Tasks) - RECOMMANDÉ

Cursor IDE supporte les tâches personnalisées. Utilisez le raccourci clavier :

1. **Ouvrir la palette de commandes** : `Ctrl+Shift+P` (Windows/Linux) ou `Cmd+Shift+P` (Mac)
2. **Taper** : `Tasks: Run Task`
3. **Sélectionner** une des tâches suivantes :
   - 🤖 **Gemini Agent - Chat** : Mode interactif pour discuter avec l'agent
   - 🤖 **Gemini Agent - Query** : Poser une question unique (vous serez invité à saisir votre question)
   - 🤖 **Gemini Agent - Review Code** : Réviser le fichier actuellement ouvert
   - 🤖 **Gemini Agent - Explain Code** : Expliquer le fichier actuellement ouvert

### Méthode 2 : Via le Terminal Intégré

1. **Ouvrir le terminal** : `Ctrl+`` (backtick) ou `Terminal > New Terminal`
2. **Utiliser les commandes** :

```bash
# Mode interactif
python cursor_agent.py chat

# Poser une question unique
python cursor_agent.py query "Comment optimiser cette fonction?"

# Réviser un fichier
python cursor_agent.py review mon_fichier.py

# Expliquer un fichier
python cursor_agent.py explain mon_fichier.py
```

### Méthode 3 : Via des Raccourcis Claviers Personnalisés

1. **Ouvrir les raccourcis clavier** : `Ctrl+K Ctrl+S` ou `File > Preferences > Keyboard Shortcuts`
2. **Chercher** : `workbench.action.tasks.runTask`
3. **Ajouter un raccourci** pour la tâche "Gemini Agent - Chat"

Exemple de configuration dans `keybindings.json` :

```json
{
    "key": "ctrl+alt+g",
    "command": "workbench.action.tasks.runTask",
    "args": "🤖 Gemini Agent - Chat"
}
```

### Méthode 4 : Via le Menu Contextuel (Clic Droit)

Vous pouvez ajouter des commandes personnalisées dans le menu contextuel en modifiant `settings.json` :

```json
{
    "menus": {
        "explorer/context": [
            {
                "command": "workbench.action.tasks.runTask",
                "args": "🤖 Gemini Agent - Review Code",
                "when": "resourceExtname == .py",
                "group": "navigation"
            }
        ]
    }
}
```

## 📝 Exemples d'utilisation

### Réviser du code

1. Ouvrez un fichier Python
2. `Ctrl+Shift+P` → `Tasks: Run Task` → `🤖 Gemini Agent - Review Code`
3. L'agent analysera votre code et donnera des suggestions

### Expliquer du code

1. Ouvrez un fichier Python
2. `Ctrl+Shift+P` → `Tasks: Run Task` → `🤖 Gemini Agent - Explain Code`
3. L'agent expliquera le fonctionnement du code

### Poser une question rapide

1. `Ctrl+Shift+P` → `Tasks: Run Task` → `🤖 Gemini Agent - Query`
2. Saisissez votre question quand vous y êtes invité
3. L'agent répondra directement

## 🔧 Configuration

### Variables d'environnement

Assurez-vous d'avoir un fichier `.env` à la racine du projet :

```env
GEMINI_API_KEY=votre_clé_api_ici
GEMINI_MODEL=gemini-1.5-pro
```

### Personnaliser les tâches

Modifiez `.vscode/tasks.json` pour ajouter vos propres tâches personnalisées.

## 💡 Astuces

1. **Utilisez le terminal intégré** pour des interactions plus longues
2. **Sélectionnez du code** avant d'utiliser "Review Code" pour analyser seulement une partie
3. **Utilisez le mode chat** pour des conversations approfondies sur votre code
4. **Combinez avec les fonctionnalités Cursor** : L'agent Gemini peut compléter les suggestions de Cursor

## 🐛 Dépannage

### L'agent ne se lance pas

- Vérifiez que Python est installé et accessible : `python --version`
- Vérifiez que les dépendances sont installées : `pip install -r requirements.txt`
- Vérifiez que le fichier `.env` existe et contient `GEMINI_API_KEY`

### Erreur "Module not found"

- Installez les dépendances : `pip install -r requirements.txt`
- Vérifiez que vous êtes dans le bon répertoire

### L'agent ne répond pas

- Vérifiez votre connexion Internet
- Vérifiez que votre clé API est valide
- Consultez les messages d'erreur dans le terminal

## 📚 Commandes disponibles

| Commande | Description |
|----------|-------------|
| `python cursor_agent.py chat` | Mode interactif |
| `python cursor_agent.py query "question"` | Question unique |
| `python cursor_agent.py review [fichier]` | Réviser du code |
| `python cursor_agent.py explain [fichier]` | Expliquer du code |
| `python cursor_agent.py help` | Afficher l'aide |

## 🎯 Cas d'usage

- **Révision de code** : Obtenez des suggestions d'amélioration
- **Explication de code** : Comprenez du code complexe
- **Debugging** : Demandez de l'aide pour résoudre des bugs
- **Optimisation** : Améliorez les performances de votre code
- **Documentation** : Générez de la documentation pour votre code
- **Refactoring** : Obtenez des suggestions de refactorisation
