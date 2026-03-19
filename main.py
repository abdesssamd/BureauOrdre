"""
Script principal pour tester l'agent Gemini.

Ce script permet d'interagir avec l'agent via une interface en ligne de commande.
"""

import os
import sys
from dotenv import load_dotenv
from gemini_agent import GeminiAgent


def get_current_weather(location: str, unit: str = "celsius") -> dict:
    """
    Obtient la météo actuelle pour un lieu donné.
    
    Cette fonction est un exemple d'outil (tool) que l'agent peut appeler.
    Dans un cas réel, cette fonction ferait un appel API vers un service météo.
    
    Args:
        location: Nom de la ville ou lieu
        unit: Unité de température ('celsius' ou 'fahrenheit')
    
    Returns:
        Dictionnaire contenant les informations météorologiques
    """
    # Simulation de données météo (dummy function)
    weather_data = {
        "location": location,
        "temperature": 22 if unit == "celsius" else 72,
        "unit": unit,
        "condition": "ensoleillé",
        "humidity": 65,
        "wind_speed": 10
    }
    
    return weather_data


def main():
    """Fonction principale pour l'interface en ligne de commande."""
    # Chargement des variables d'environnement
    load_dotenv()
    
    print("=" * 60)
    print("🤖 Agent Gemini - Interface de Test")
    print("=" * 60)
    print()
    
    try:
        # Récupération du modèle depuis les variables d'environnement ou utilisation du défaut
        model_name = os.getenv("GEMINI_MODEL", "gemini-1.5-pro")
        
        print(f"📦 Initialisation de l'agent avec le modèle: {model_name}")
        print("⏳ Veuillez patienter...")
        print()
        
        # Initialisation de l'agent
        agent = GeminiAgent(model_name=model_name)
        
        # Ajout de l'outil exemple
        agent.add_tool("get_current_weather", get_current_weather)
        
        print("✅ Agent initialisé avec succès!")
        print(f"🔧 Outils disponibles: {', '.join(agent.tools.keys())}")
        print()
        print("💡 Commandes spéciales:")
        print("   - 'quit' ou 'exit' : Quitter l'application")
        print("   - 'clear' : Effacer l'historique de conversation")
        print("   - 'history' : Afficher l'historique")
        print("   - 'reset' : Réinitialiser complètement l'agent")
        print()
        print("-" * 60)
        print()
        
        # Boucle d'interaction
        while True:
            try:
                # Saisie utilisateur
                user_input = input("Vous: ").strip()
                
                if not user_input:
                    continue
                
                # Commandes spéciales
                if user_input.lower() in ['quit', 'exit', 'q']:
                    print("\n👋 Au revoir!")
                    break
                
                elif user_input.lower() == 'clear':
                    agent.clear_history()
                    print("✅ Historique effacé.\n")
                    continue
                
                elif user_input.lower() == 'history':
                    history = agent.get_history()
                    if history:
                        print("\n📜 Historique de la conversation:")
                        print("-" * 60)
                        for i, msg in enumerate(history, 1):
                            role = "👤 Utilisateur" if msg["role"] == "user" else "🤖 Assistant"
                            content = msg["content"]
                            # Tronquer les messages trop longs
                            if len(content) > 200:
                                content = content[:200] + "..."
                            print(f"{i}. {role}: {content}")
                        print("-" * 60)
                    else:
                        print("📜 Aucun historique.\n")
                    continue
                
                elif user_input.lower() == 'reset':
                    agent.reset()
                    agent.add_tool("get_current_weather", get_current_weather)
                    print("✅ Agent réinitialisé.\n")
                    continue
                
                # Envoi du message à l'agent
                print("\n🤖 Assistant: ", end="", flush=True)
                response = agent.chat(user_input)
                print(response)
                print()
                
            except KeyboardInterrupt:
                print("\n\n⚠️  Interruption détectée. Utilisez 'quit' pour quitter proprement.")
                print()
            
            except Exception as e:
                print(f"\n❌ Erreur: {str(e)}\n")
    
    except ValueError as e:
        print(f"❌ Erreur de configuration: {str(e)}")
        print("\n💡 Assurez-vous d'avoir:")
        print("   1. Créé un fichier .env avec GEMINI_API_KEY=votre_clé")
        print("   2. Installé les dépendances: pip install -r requirements.txt")
        sys.exit(1)
    
    except Exception as e:
        print(f"❌ Erreur lors de l'initialisation: {str(e)}")
        print(f"   Détails: {str(e)}")
        sys.exit(1)


if __name__ == "__main__":
    main()
