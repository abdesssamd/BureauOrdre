"""
Agent AI modulaire utilisant l'API Google Gemini.

Ce module fournit une classe GeminiAgent qui permet d'interagir avec les modèles
Google Gemini avec support pour la gestion de mémoire et le function calling.
"""

import os
import json
from typing import List, Dict, Any, Optional, Callable
import google.generativeai as genai
from google.generativeai.types import FunctionDeclaration, Tool


class GeminiAgent:
    """
    Agent AI modulaire utilisant l'API Google Gemini.
    
    Cette classe gère les interactions avec les modèles Gemini, maintient
    un historique de conversation et supporte le function calling.
    
    Attributes:
        api_key (str): Clé API Google Gemini
        model_name (str): Nom du modèle à utiliser
        chat_session: Session de chat Gemini
        history (List[Dict]): Historique de la conversation
        tools (Dict[str, Callable]): Dictionnaire des outils disponibles
    """
    
    def __init__(
        self,
        api_key: Optional[str] = None,
        model_name: str = "gemini-1.5-pro"
    ):
        """
        Initialise l'agent Gemini.
        
        Args:
            api_key: Clé API Google Gemini. Si None, cherche dans les variables d'environnement.
            model_name: Nom du modèle à utiliser (défaut: 'gemini-1.5-pro').
                       Préparé pour 'gemini-3.0' quand disponible.
        """
        # Récupération de la clé API
        self.api_key = api_key or os.getenv("GEMINI_API_KEY")
        if not self.api_key:
            raise ValueError(
                "Clé API non fournie. "
                "Fournissez-la via le paramètre api_key ou la variable d'environnement GEMINI_API_KEY."
            )
        
        # Configuration de l'API
        genai.configure(api_key=self.api_key)
        
        # Nom du modèle (support pour gemini-3.0 quand disponible)
        self.model_name = model_name or os.getenv("GEMINI_MODEL", "gemini-1.5-pro")
        
        # Initialisation du modèle
        self.model = genai.GenerativeModel(
            model_name=self.model_name,
            tools=self._get_tools() if hasattr(self, '_tools') else None
        )
        
        # Session de chat et historique
        self.chat_session = None
        self.history: List[Dict[str, Any]] = []
        
        # Dictionnaire des outils disponibles
        self.tools: Dict[str, Callable] = {}
        
        # Initialisation de la session de chat
        self._start_chat_session()
    
    def _get_tools(self) -> Optional[List[Tool]]:
        """
        Construit la liste des outils (fonctions) disponibles pour le modèle.
        
        Returns:
            Liste des outils au format Gemini, ou None si aucun outil n'est défini.
        """
        if not self.tools:
            return None
        
        function_declarations = []
        
        for func_name, func in self.tools.items():
            # Récupération des annotations de type et docstring
            import inspect
            sig = inspect.signature(func)
            doc = inspect.getdoc(func) or ""
            
            # Extraction des paramètres depuis la signature
            properties = {}
            required = []
            
            for param_name, param in sig.parameters.items():
                if param_name == 'self':
                    continue
                
                param_type = param.annotation
                param_default = param.default
                
                # Mapping des types Python vers les types JSON Schema
                type_mapping = {
                    str: "string",
                    int: "integer",
                    float: "number",
                    bool: "boolean",
                    list: "array",
                    dict: "object"
                }
                
                # Gestion des types optionnels (Optional[Type] ou Union[Type, None])
                origin = get_origin(param_type)
                if origin is Union:
                    args = get_args(param_type)
                    # Si c'est Union[Type, None] ou Optional[Type]
                    if len(args) == 2 and type(None) in args:
                        # Prendre le type non-None
                        param_type = next(arg for arg in args if arg is not type(None))
                
                json_type = type_mapping.get(param_type, "string")
                
                properties[param_name] = {
                    "type": json_type,
                    "description": f"Paramètre {param_name}"
                }
                
                if param_default == inspect.Parameter.empty:
                    required.append(param_name)
            
            # Création de la déclaration de fonction
            func_decl = FunctionDeclaration(
                name=func_name,
                description=doc.split('\n')[0] if doc else f"Fonction {func_name}",
                parameters={
                    "type": "object",
                    "properties": properties,
                    "required": required if required else None
                }
            )
            
            function_declarations.append(func_decl)
        
        if function_declarations:
            return [Tool(function_declarations=function_declarations)]
        
        return None
    
    def _start_chat_session(self):
        """Initialise ou réinitialise la session de chat."""
        # Reconstruction des outils si nécessaire
        tools = self._get_tools()
        
        # Recréation du modèle avec les outils à jour
        if tools:
            self.model = genai.GenerativeModel(
                model_name=self.model_name,
                tools=tools
            )
        else:
            self.model = genai.GenerativeModel(model_name=self.model_name)
        
        # Démarrage d'une nouvelle session
        self.chat_session = self.model.start_chat(history=[])
        self.history = []
    
    def add_tool(self, name: str, function: Callable, description: Optional[str] = None):
        """
        Ajoute un outil (fonction) que l'agent peut appeler.
        
        Args:
            name: Nom de la fonction (doit correspondre au nom de la fonction)
            function: Fonction Python callable
            description: Description optionnelle de la fonction
        """
        self.tools[name] = function
        
        # Ajout de la description si fournie
        if description:
            function.__doc__ = description
        
        # Redémarrage de la session pour prendre en compte le nouvel outil
        self._start_chat_session()
    
    def remove_tool(self, name: str):
        """
        Supprime un outil de la liste des outils disponibles.
        
        Args:
            name: Nom de l'outil à supprimer
        """
        if name in self.tools:
            del self.tools[name]
            self._start_chat_session()
    
    def _execute_tool(self, function_name: str, arguments: Dict[str, Any]) -> Any:
        """
        Exécute un outil avec les arguments fournis.
        
        Args:
            function_name: Nom de la fonction à exécuter
            arguments: Arguments à passer à la fonction
            
        Returns:
            Résultat de l'exécution de la fonction
        """
        if function_name not in self.tools:
            raise ValueError(f"Outil '{function_name}' non trouvé.")
        
        func = self.tools[function_name]
        return func(**arguments)
    
    def chat(self, message: str) -> str:
        """
        Envoie un message à l'agent et retourne la réponse.
        
        Cette méthode gère automatiquement le function calling si nécessaire.
        
        Args:
            message: Message de l'utilisateur
            
        Returns:
            Réponse de l'agent
        """
        if not self.chat_session:
            self._start_chat_session()
        
        try:
            # Envoi du message
            response = self.chat_session.send_message(message)
            
            # Gestion du function calling (boucle pour gérer plusieurs appels)
            max_iterations = 10  # Protection contre les boucles infinies
            iteration = 0
            
            while iteration < max_iterations:
                iteration += 1
                
                # Vérification si la réponse contient un function call
                function_call_found = False
                
                if (response.candidates and 
                    len(response.candidates) > 0 and 
                    response.candidates[0].content and
                    response.candidates[0].content.parts):
                    
                    # Parcourir toutes les parties pour trouver un function call
                    for part in response.candidates[0].content.parts:
                        # Vérification si c'est un function call
                        if hasattr(part, 'function_call') and part.function_call:
                            function_call = part.function_call
                            function_name = function_call.name
                            
                            # Extraction des arguments
                            if hasattr(function_call, 'args'):
                                if isinstance(function_call.args, dict):
                                    arguments = function_call.args
                                else:
                                    arguments = dict(function_call.args) if hasattr(function_call.args, '__dict__') else {}
                            else:
                                arguments = {}
                            
                            function_call_found = True
                            
                            # Exécution de la fonction
                            try:
                                result = self._execute_tool(function_name, arguments)
                                
                                # Formatage du résultat
                                if isinstance(result, (dict, list)):
                                    result_data = result
                                else:
                                    result_data = {"result": str(result)}
                                
                                # Création de la réponse de fonction
                                from google.generativeai.types import FunctionResponse
                                
                                function_response = FunctionResponse(
                                    name=function_name,
                                    response=result_data
                                )
                                
                                # Envoi du résultat au modèle
                                response = self.chat_session.send_message(function_response)
                                break  # Sortir de la boucle des parts
                                
                            except Exception as e:
                                # En cas d'erreur, on informe le modèle
                                error_msg = f"Erreur lors de l'exécution de '{function_name}': {str(e)}"
                                from google.generativeai.types import FunctionResponse
                                
                                function_response = FunctionResponse(
                                    name=function_name,
                                    response={"error": error_msg}
                                )
                                
                                response = self.chat_session.send_message(function_response)
                                break  # Sortir de la boucle des parts
                
                # Si aucun function call n'a été trouvé, on sort de la boucle
                if not function_call_found:
                    break
            
            # Extraction de la réponse textuelle
            if hasattr(response, 'text') and response.text:
                response_text = response.text
            else:
                # Essayer d'extraire le texte d'une autre manière
                try:
                    if (response.candidates and 
                        len(response.candidates) > 0 and 
                        response.candidates[0].content and
                        response.candidates[0].content.parts):
                        text_parts = []
                        for part in response.candidates[0].content.parts:
                            if hasattr(part, 'text') and part.text:
                                text_parts.append(part.text)
                        response_text = ' '.join(text_parts) if text_parts else "Désolé, je n'ai pas pu générer de réponse."
                    else:
                        response_text = "Désolé, je n'ai pas pu générer de réponse."
                except:
                    response_text = "Désolé, je n'ai pas pu générer de réponse."
            
            # Ajout à l'historique
            self.history.append({
                "role": "user",
                "content": message
            })
            self.history.append({
                "role": "assistant",
                "content": response_text
            })
            
            return response_text
            
        except Exception as e:
            error_msg = f"Erreur lors de la communication avec Gemini: {str(e)}"
            self.history.append({
                "role": "user",
                "content": message
            })
            self.history.append({
                "role": "assistant",
                "content": error_msg
            })
            return error_msg
    
    def clear_history(self):
        """Efface l'historique de la conversation et redémarre la session."""
        self._start_chat_session()
    
    def get_history(self) -> List[Dict[str, Any]]:
        """
        Retourne l'historique de la conversation.
        
        Returns:
            Liste des messages de la conversation
        """
        return self.history.copy()
    
    def reset(self):
        """Réinitialise complètement l'agent (historique et outils)."""
        self.tools = {}
        self.clear_history()
