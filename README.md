# lmdbreferral — Parrainage Dolibarr

Module externe Dolibarr V1 pour suivre les liens de parrainage entre un tiers ou un utilisateur apporteur et un tiers filleul.

## Fonctionnalités V1

- sélection d’un parrain lors de la création ou modification d’un tiers ;
- parrainage par tiers et, si activé, par utilisateurs autorisés ;
- annulation fonctionnelle des liens sans suppression physique ;
- détection automatique des devis signés via `PROPAL_CLOSE_SIGNED` ;
- rattachement automatique des devis déjà signés lors de l’attribution d’un parrain ;
- bannière native sur les factures des tiers filleuls avec un lien de parrainage actif ;
- vue d’ensemble statistique avec filtres, KPI, entonnoir, graphe étoile, classements et relances ;
- liste native exportable des parrainages ;
- modèle PDF standard générable depuis le bloc natif **Fichiers joints** ;
- modèles de numérotation `standard` et `advanced` configurables depuis les réglages du module ;
- API REST minimale : liste, détail, création, remplacement, annulation, événements, statistiques enrichies et graphe étoile ;
- onglets `Parrainages / Filleuls` sur fiches tiers et utilisateurs ;
- compatibilité Multicompany avec déclaration des objets partageables.

## Hors périmètre V1

- récompenses, primes, paiements et avoirs ;
- notifications et événements Agenda configurables ;
- cartographie réseau avancée ;
- commissionnement multi-niveaux.

## Compatibilité

- Dolibarr v20 minimum ;
- PHP 8.0 minimum ;
- MySQL/MariaDB via l’abstraction Dolibarr.

## Réglages

Le seul point d’entrée déclaré dans la liste des modules est `admin/setup.php`. Les onglets internes `Compatibilité` et `À propos` sont accessibles depuis cette page.

Les utilisateurs autorisés à être parrains sont stockés dans `llx_lmdbreferral_user_eligibility`.

Les réglages de dashboard permettent de définir le délai de relance des filleuls sans devis signé, la profondeur et la limite de nœuds du graphe étoile.

Les réglages de documents et de numérotation permettent d’activer le modèle PDF standard, de choisir le modèle PDF par défaut et de sélectionner le modèle de référence des liens de parrainage.
