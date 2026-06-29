# ChangeLog

## Unreleased

- Repositionnement du champ `Parrain / apporteur` dans les formulaires tiers, sous `Environnement` quand la ligne existe, sinon près du nom du tiers.
- Déplacement du bloc `Parrain / apporteur` de la fiche tiers sous la ligne native `Commerciaux`.
- Alignement de l’icône de modification du bloc `Parrain / apporteur` avec le rendu natif des libellés éditables.
- Placement du champ `Parrain / apporteur` à l’emplacement de `Environnement` en création tiers lorsque Multicompany n’affiche pas cette ligne.
- Utilisation des boutons natifs de filtre Dolibarr sur la liste principale des parrainages.
- Refonte de `card.php` en fiche native avec onglets `Fiche`, `Fichiers joints` et `Événements/Agenda`.
- Alignement du bloc Agenda sur la limite native de liste et déclaration de la résolution `lmdbreferrallink@lmdbreferral`.
- Intégration des événements métier de parrainage dans l’Agenda natif via `ActionComm`, avec synchronisation des événements existants à l’activation.
- Utilisation du réglage natif `MAIN_SIZE_SHORTLIST_LIMIT` pour le nombre de derniers événements affichés.
- Ajout de la colonne `Référence` dans la liste des parrainages et suppression de la colonne d’action `Événements`.
- Rendu natif des badges Multicompany et suppression du compteur redondant dans la colonne `Devis signé`.
- Alignement du positionnement du champ `Parrain / apporteur` en édition tiers sur le formulaire de création.
- Correction du rendu HTML de l’infobulle `getNomUrl()` des liens de parrainage.
- Ajout du bloc natif `Objets liés` sur la fiche lien et liaison automatique des devis signés.
- Uniformisation des listes de parrainages avec filtres natifs, tri, alignements, cases à cocher et annulation en masse.
- Ajout de l’édition indépendante du champ `Parrain / apporteur` depuis la fiche tiers en consultation.

## 1.0.0

- Création du module `lmdbreferral` avec ID Dolibarr `450023`.
- Ajout des tables de liens, événements et utilisateurs parrains autorisés.
- Ajout des hooks tiers, onglets tiers/utilisateur, liste, vue d’ensemble et fiche de lien.
- Ajout du trigger `PROPAL_CLOSE_SIGNED` pour alimenter les événements de devis signés et du trigger interne `LMDBREFERRAL_PROPAL_SIGNED`.
- Ajout d’une API REST minimale pour les liens, événements et statistiques.
- Ajout de la compatibilité Multicompany et des pages de réglages internes.
