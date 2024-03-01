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
docker-compose up -d
```

## Initialisation schéma base de données backoffice

```bash
# A ne faire qu'une fois
docker-compose exec backoffice php bin/console d:m:mi -n

# Si besoin pour réinitialiser sa base de données
docker-compose exec backoffice php bin/console doctrine:database:drop --force
docker-compose exec backoffice php bin/console doctrine:database:create
```

## Initialisation données de test

Des scripts SQL permettent de peupler la base de données avec des données de tests [lunt-mariadb\src\test\local](lunt-mariadb\src\test\local).

## Documentations

L'ensemble des documents du projet est entrepôsé dans le répertoire <K:\SSL\Clients\Université Numérique\INDEXATION NOTICES>

## Intégration continue et déploiement

Des opérations d'intégration continue et de déploiement automatique sont configurées dans le dossier Jenkins :  
https://sslv-factory.coexya.lan/jenkins/job/Universit%C3%A9%20Num%C3%A9rique/
