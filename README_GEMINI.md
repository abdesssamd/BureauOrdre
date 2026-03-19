# Agent AI Gemini - Documentation

Agent AI modulaire utilisant l'API Google Gemini avec support pour la gestion de mémoire et le function calling.

## Installation

1. Installer les dépendances :
```bash
pip install -r requirements.txt
```

2. Créer un fichier `.env` à la racine du projet :
```env
GEMINI_API_KEY=votre_clé_api_ici
GEMINI_MODEL=gemini-1.5-pro
```

Pour obtenir une clé API :
- Visitez [Google AI Studio](https://makersuite.google.com/app/apikey)
- Créez une nouvelle clé API

## Utilisation

### Interface en ligne de commande

Lancer le script principal :
```bash
python main.py
```

Commandes spéciales disponibles :
- `quit` ou `exit` : Quitter l'application
- `clear` : Effacer l'historique de conversation
- `history` : Afficher l'historique
- `reset` : Réinitialiser complètement l'agent

### Utilisation programmatique

```python
from dotenv import load_dotenv
from gemini_agent import GeminiAgent

# Charger les variables d'environnement
load_dotenv()

# Initialiser l'agent
agent = GeminiAgent(model_name="gemini-1.5-pro")

# Ajouter un outil personnalisé
def ma_fonction(param1: str, param2: int) -> dict:
    """Description de la fonction"""
    return {"result": f"Traitement de {param1} avec {param2}"}

agent.add_tool("ma_fonction", ma_fonction)

# Interagir avec l'agent
response = agent.chat("Bonjour, peux-tu m'aider ?")
print(response)

# Accéder à l'historique
history = agent.get_history()
```

## Modèles supportés

- `gemini-1.5-pro` (par défaut)
- `gemini-1.5-flash`
- `gemini-3.0` (quand disponible)

## Fonctionnalités

### Gestion de la mémoire
L'agent maintient automatiquement un historique de la conversation via `ChatSession` de Gemini.

### Function Calling
L'agent peut appeler des fonctions Python définies par l'utilisateur. Exemple inclus : `get_current_weather`.

### Ajout d'outils personnalisés

```python
def mon_outil(parametre: str) -> dict:
    """
    Description de l'outil.
    
    Args:
        parametre: Description du paramètre
    
    Returns:
        Résultat de l'opération
    """
    # Votre logique ici
    return {"status": "success"}

agent.add_tool("mon_outil", mon_outil)
```

## Structure du projet

```
.
├── gemini_agent.py      # Classe principale GeminiAgent
├── main.py              # Script de test en ligne de commande
├── requirements.txt     # Dépendances Python
└── .env                 # Variables d'environnement (à créer)
```

## Notes

- L'agent gère automatiquement les appels de fonction en boucle si nécessaire
- Les erreurs lors de l'exécution des outils sont capturées et communiquées au modèle
- L'historique peut être effacé ou consulté à tout moment
