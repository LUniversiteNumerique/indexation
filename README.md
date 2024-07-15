# Les Universités Numériques et Thématiques
## Système d'indexation des notices

L'application d'indexation des notices des Universités Numériques et Thématiques est construite autour
d'une architecture découplée.  

Les composants de cette application sont les suivants :
- lunt-backoffice : Interface de contribution et d'administration Php / Symphony
- Entrepôt des notices MariaDB
- Stockage des ressources Filesystem
- lunt-indexation : Chaîne d'indexation Php
- Moteur de recherche Solr
- Entrepôt OAI jOAI
- Site internet WordPress

## Démarrer le projet

```bash
docker-compose up -d --build
```

## Initialisation schéma base de données backoffice

```bash
# A ne faire qu'une fois
docker-compose exec backoffice php bin/console m:mi -n
docker-compose exec backoffice php bin/console d:m:mi -n

# Si besoin pour réinitialiser sa base de données
docker-compose exec backoffice php bin/console d:d:d --force
docker-compose exec backoffice php bin/console d:d:c
docker-compose exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/univerique.sql)"'
docker-compose exec backoffice php bin/console m:mi -n
docker-compose exec backoffice php bin/console d:m:mi -n
docker-compose exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/groupe.sql)"'
docker-compose exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/user.sql)"'
```

## Initialisation données de test

Des scripts SQL permettent de peupler la base de données avec des données de tests [lunt-mariadb\src\test\local](lunt-mariadb\src\test\local).

## Documentations

L'ensemble des documents du projet est entrepôsé dans le répertoire <K:\SSL\Clients\Université Numérique\INDEXATION NOTICES>

## Intégration continue et déploiement

Des opérations d'intégration continue et de déploiement automatique sont configurées dans le dossier Jenkins :  
https://sslv-factory.coexya.lan/jenkins/job/Universit%C3%A9%20Num%C3%A9rique/

L'intégration de Jenkins a été réalisée pour le projet. La configuration est disponible ici :  
https://ssl-gitlab.coexya.eu/universite-numerique/lunt-indexation-notice/-/settings/integrations/jenkins/edit

Le job Jenkins effectue une analyse Sonar à chaque push sur la branche develop.  
Un déploiement sur la plateforme de développement est réalisé à chaque création de tag.

## Environnement

Accès Backoffice développement : http://sslv-lunt-develop.lyon-dev2.local/
