#!/bin/bash
set -euo pipefail

# Variables de connexion MySQL
MYSQL_USER="root"
MYSQL_DATABASE="unt_db"

# Prompt pour le mot de passe
read -s -p "Mot de passe BDD pour l'utilisateur $MYSQL_USER : " MYSQL_PASSWORD
echo

# Conteneurs
db_container="lunt-indexation-notice-mariadb-1"
app_container="lunt-indexation-notice-backoffice-1"

# Répertoire local où se trouvent les fichiers SQL
local_dir="lunt-backoffice/data"

make dev

echo "=== Mise à jour du schéma avec Symfony ==="
docker exec -it "$app_container" php bin/console d:s:u --force

# Ordre d'import
tables=(auteur groupe licence niveau tdocument univerique user dossier indexing_config)

echo "=== Import direct des fichiers SQL dans l'ordre défini ==="
for table in "${tables[@]}"; do
    sql_file="$local_dir/${table}.sql"
    if [[ -f "$sql_file" ]]; then
        echo "Import de $sql_file..."
        docker exec -i "$db_container" \
          mariadb -u${MYSQL_USER} -p${MYSQL_PASSWORD} ${MYSQL_DATABASE} < "$sql_file"
    else
        echo "Fichier introuvable : $sql_file"
        exit 1
    fi
done

docker exec -it "$app_container" php bin/console app:import-xml-data

docker exec -it "$app_container" php bin/console import:specialites-data uoh

docker exec -it "$app_container" php bin/console import:users-uoh-data

# Import notices à exposer sur le portail
docker exec -it "$app_container" php bin/console import:notice-data uoh suplom_exposed
# Import notices à ne pas exposer sur le portail
docker exec -it "$app_container" php bin/console import:notice-data uoh suplom_not_exposed false

echo "=== Import terminé ==="

echo "=== Installation des dépendances composer ==="
docker exec -it "$app_container" composer install
echo "=== Installation terminée ==="

echo "Ouvrir le backoffice : http://localhost"
