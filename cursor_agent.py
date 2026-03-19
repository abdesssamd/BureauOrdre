"""
Agent Gemini pour Cursor IDE.

Ce script permet d'utiliser l'agent Gemini directement depuis Cursor IDE
via des commandes personnalisées ou le terminal intégré.
"""

import os
import sys
import json
from dotenv import load_dotenv
from gemini_agent import GeminiAgent


def create_coding_agent() -> GeminiAgent:
    """
    Crée un agent Gemini spécialisé pour le développement de code.
    
    Returns:
        Instance de GeminiAgent configurée pour le développement
    """
    load_dotenv()
    
    model_name = os.getenv("GEMINI_MODEL", "gemini-1.5-pro")
    agent = GeminiAgent(model_name=model_name)
    
    # Ajout d'outils spécifiques au développement si nécessaire
    # Exemple: fonction pour analyser du code, etc.
    
    return agent


def chat_mode():
    """Mode interactif pour discuter avec l'agent."""
    agent = create_coding_agent()
    
    print("🤖 Agent Gemini pour Cursor IDE")
    print("Tapez votre question ou 'quit' pour quitter\n")
    
    while True:
        try:
            user_input = input("Vous: ").strip()
            
            if not user_input:
                continue
            
            if user_input.lower() in ['quit', 'exit', 'q']:
                break
            
            response = agent.chat(user_input)
            print(f"\n🤖 Assistant: {response}\n")
            
        except KeyboardInterrupt:
            print("\n\nAu revoir!")
            break
        except Exception as e:
            print(f"\n❌ Erreur: {str(e)}\n")


def single_query_mode(query: str):
    """
    Mode pour une seule requête (utile pour les commandes Cursor).
    
    Args:
        query: Question à poser à l'agent
    """
    try:
        agent = create_coding_agent()
        response = agent.chat(query)
        print(response)
        return response
    except Exception as e:
        error_msg = f"Erreur: {str(e)}"
        print(error_msg)
        return error_msg


def code_review_mode(file_path: str = None):
    """
    Mode pour réviser du code.
    
    Args:
        file_path: Chemin vers le fichier à réviser (optionnel)
    """
    agent = create_coding_agent()
    
    if file_path and os.path.exists(file_path):
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                code_content = f.read()
            
            query = f"Peux-tu réviser ce code et me donner des suggestions d'amélioration?\n\n```\n{code_content}\n```"
            response = agent.chat(query)
            print(response)
            return response
        except Exception as e:
            error_msg = f"Erreur lors de la lecture du fichier: {str(e)}"
            print(error_msg)
            return error_msg
    else:
        # Mode interactif pour coller du code
        print("🤖 Mode Révision de Code")
        print("Collez votre code (tapez 'END' sur une ligne vide pour terminer):\n")
        
        code_lines = []
        while True:
            line = input()
            if line.strip() == 'END':
                break
            code_lines.append(line)
        
        code_content = '\n'.join(code_lines)
        query = f"Peux-tu réviser ce code et me donner des suggestions d'amélioration?\n\n```\n{code_content}\n```"
        response = agent.chat(query)
        print(f"\n{response}")
        return response


def explain_code_mode(file_path: str = None):
    """
    Mode pour expliquer du code.
    
    Args:
        file_path: Chemin vers le fichier à expliquer (optionnel)
    """
    agent = create_coding_agent()
    
    if file_path and os.path.exists(file_path):
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                code_content = f.read()
            
            query = f"Peux-tu expliquer ce code en détail?\n\n```\n{code_content}\n```"
            response = agent.chat(query)
            print(response)
            return response
        except Exception as e:
            error_msg = f"Erreur lors de la lecture du fichier: {str(e)}"
            print(error_msg)
            return error_msg
    else:
        print("🤖 Mode Explication de Code")
        print("Collez votre code (tapez 'END' sur une ligne vide pour terminer):\n")
        
        code_lines = []
        while True:
            line = input()
            if line.strip() == 'END':
                break
            code_lines.append(line)
        
        code_content = '\n'.join(code_lines)
        query = f"Peux-tu expliquer ce code en détail?\n\n```\n{code_content}\n```"
        response = agent.chat(query)
        print(f"\n{response}")
        return response


def main():
    """Point d'entrée principal."""
    if len(sys.argv) > 1:
        command = sys.argv[1].lower()
        
        if command == 'query' and len(sys.argv) > 2:
            # Mode requête unique
            query = ' '.join(sys.argv[2:])
            single_query_mode(query)
        
        elif command == 'review':
            # Mode révision de code
            file_path = sys.argv[2] if len(sys.argv) > 2 else None
            code_review_mode(file_path)
        
        elif command == 'explain':
            # Mode explication de code
            file_path = sys.argv[2] if len(sys.argv) > 2 else None
            explain_code_mode(file_path)
        
        elif command == 'help':
            print("""
🤖 Agent Gemini pour Cursor IDE - Aide

Usage:
  python cursor_agent.py [command] [arguments]

Commandes disponibles:
  chat                    Mode interactif (par défaut)
  query <question>        Poser une question unique
  review [fichier]        Réviser du code
  explain [fichier]       Expliquer du code
  help                    Afficher cette aide

Exemples:
  python cursor_agent.py chat
  python cursor_agent.py query "Comment optimiser cette fonction?"
  python cursor_agent.py review mon_fichier.py
  python cursor_agent.py explain mon_fichier.py
            """)
        
        else:
            print(f"Commande inconnue: {command}")
            print("Utilisez 'python cursor_agent.py help' pour voir l'aide")
    else:
        # Mode interactif par défaut
        chat_mode()


if __name__ == "__main__":
    main()
