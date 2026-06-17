# AGENT.md — lmdbreferral

Module externe Dolibarr `lmdbreferral` installé sous `htdocs/custom/lmdbreferral`.

Règles de maintenance :

- ne pas modifier le core Dolibarr ;
- conserver la compatibilité Dolibarr v20+ et PHP 8.0+ ;
- respecter les droits, tokens CSRF, hooks, triggers et filtres `entity` natifs ;
- conserver `config_page_url` limité à `setup.php@lmdbreferral` ;
- garder `ChangeLog.md` et `modulebuilder.txt` à la racine.
