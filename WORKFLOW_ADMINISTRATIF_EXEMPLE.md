# Workflow Administratif - Exemple Directeur

## 1) Creation au bureau d'ordre
- Secretariat enregistre le courrier.
- Deux modes:
  - `Avec document`: scan PDF/image puis enregistrement.
  - `Sans document`: cocher `Ordre sans document (instruction uniquement)`.

## 2) Transmission initiale
- Directeur ouvre le courrier dans le registre.
- Clique `Transmettre`.
- Choisit une cible:
  - une personne;
  - un service.
- Renseigne l'instruction (`Traitement`, `Avis`, `Information`, etc.) + echeance.

## 3) Traitement et retours (plusieurs recours)
- Le destinataire traite dans `Mes taches`.
- Ajoute commentaires, pieces jointes de correction, et avancement.
- Si correction demandee:
  - nouvelle transmission (retour) sur le meme courrier;
  - l'historique conserve toutes les etapes.
- Boucle jusqu'a validation.

## 4) Cloture
- L'agent (ou le service) clique `Valider` dans la tache.
- La tache passe `traite`.
- Le courrier peut etre marque `traite` puis archive.

## Cas supportes
- Document scanne au bureau d'ordre.
- Document partage a un service/personne.
- Ordre sans document partage (instruction seule).

## Script fourni
- Installation complete en un seul fichier SQL (schema + migration + workflow exemple):
  - `sql/install_complete.sql`
