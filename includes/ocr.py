import easyocr
import sys
import json

# Arguments : chemin de l'image
file_path = sys.argv[1]

# Initialisation du lecteur (Arabe et Français)
# gpu=False si vous n'avez pas de carte graphique NVIDIA sur le serveur
reader = easyocr.Reader(['ar', 'fr'], gpu=False) 

try:
    # paragraph=True aide à regrouper le texte en blocs logiques
    result = reader.readtext(file_path, detail=0, paragraph=True)
    
    # On joint tout le texte trouvé
    full_text = "\n".join(result)
    
    # On renvoie le résultat pour PHP
    print(full_text)
    
except Exception as e:
    print("Erreur : " + str(e))