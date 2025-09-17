#!/bin/bash
set -euo pipefail

# Nom des conteneurs
BACKOFFICE_CONTAINER="backoffice"
MARIADB_CONTAINER="mariadb"

# Base de données et credentials
DATABASE="unt_db"
USER="root"

# Prompt pour le mot de passe
read -s -p "Mot de passe BDD pour l'utilisateur $USER : " PASSWORD
echo

# Fichier dump à importer (par défaut dump_bdd.sql dans le dossier courant)
DUMP_FILE="${1:-dump_bdd.sql}"

# Vérifie que le fichier existe
if [[ ! -f "$DUMP_FILE" ]]; then
  echo "Fichier SQL introuvable : $DUMP_FILE"
  exit 1
fi

# 1. RAZ des tables via le backoffice
echo "Remise à zéro des données (RAZ)..."
docker compose exec -T "$BACKOFFICE_CONTAINER" php bin/console raz:all-data

# 2. Copie du fichier dans le conteneur MariaDB
echo "Copie du fichier SQL dans $MARIADB_CONTAINER:/tmp/"
docker compose cp "$DUMP_FILE" "$MARIADB_CONTAINER:/tmp/"

# 3. Import dans la base
echo "Import du fichier $DUMP_FILE dans la base $DATABASE..."
docker compose exec -T "$MARIADB_CONTAINER" \
  sh -c "mariadb $DATABASE -u$USER -p$PASSWORD < /tmp/$(basename "$DUMP_FILE")"

# 4. Suppression du fichier SQL dans le conteneur
echo "Nettoyage du conteneur..."
docker compose exec -T "$MARIADB_CONTAINER" \
  rm -f "/tmp/$(basename "$DUMP_FILE")"

echo "Import terminé avec succès"
