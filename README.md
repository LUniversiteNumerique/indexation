# Les Universités Numériques et Thématiques
## Système d'indexation des notices
L'application d'indexation des notices des Universités Numériques et Thématiques est construite autour
d'une architecture découplée.  

Les composants de cette application sont les suivants :
- lunt-backoffice : Interface de contribution et d'administration et Chaîne d'indexation Php / Symphony
- Entrepôt des notices MariaDB
- Entrepôt OAI jOAI et Stockage des ressources Filesystem
- Moteur de recherche Solr

## Documentation
L'ensemble des documents du projet est entrepôsé dans le répertoire du projet : K:/SSL/Clients/Université Numérique/INDEXATION NOTICES/

## Intégration continue et déploiement
Des opérations d'intégration continue et de déploiement automatique sont configurées dans le [dossier Jenkins](https://sslv-factory.coexya.lan/jenkins/job/Universit%C3%A9%20Num%C3%A9rique/).

L'intégration de Jenkins a été réalisée pour le projet. La configuration est disponible [ici](https://ssl-gitlab.coexya.eu/universite-numerique/lunt-indexation-notice/-/settings/integrations/jenkins/edit).

Le job Jenkins effectue une analyse Sonar à chaque push sur la branche develop.
Il effectue aussi une analyse checkmarx.
Un déploiement sur la plateforme de développement est réalisé à chaque création de tag.

## Installation dev
Voir le manuel d'installation développeur : K:/SSL/Clients/Université Numérique/INDEXATION NOTICES/Utilisat/Manuels/MAN00410 - Manuel d'installation développeur.docx

## Commandes utiles
- Lancer le projet : `make dev-start`
- Arrêter le projet : `make dev-stop`
- Build le projet : `make build`
- Se connecter au conteneur backoffice : `make bo-bash`
- Se connecter à la base de données : `make db-connect`
- Générer les assets et compiler les js : `make asset-compile` 
