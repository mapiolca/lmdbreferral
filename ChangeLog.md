# ChangeLog

## Unreleased

- Repositionnement du champ `Parrain / apporteur` dans les formulaires tiers, sous `Environnement` quand la ligne existe, sinon près du nom du tiers.
- Déplacement du bloc `Parrain / apporteur` de la fiche tiers sous la ligne native `Commerciaux`.
- Alignement de l’icône de modification du bloc `Parrain / apporteur` avec le rendu natif des libellés éditables.

## 1.0.0

- Création du module `lmdbreferral` avec ID Dolibarr `450023`.
- Ajout des tables de liens, événements et utilisateurs parrains autorisés.
- Ajout des hooks tiers, onglets tiers/utilisateur, liste, vue d’ensemble et fiche de lien.
- Ajout du trigger `PROPAL_CLOSE_SIGNED` pour alimenter les événements de devis signés et du trigger interne `LMDBREFERRAL_PROPAL_SIGNED`.
- Ajout d’une API REST minimale pour les liens, événements et statistiques.
- Ajout de la compatibilité Multicompany et des pages de réglages internes.
