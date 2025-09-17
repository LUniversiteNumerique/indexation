#!/bin/bash
set -euo pipefail

# Nom du conteneur
CONTAINER="lunt-indexation-notice-mariadb-1"

# Variables de connexion MySQL
USER="root"
DATABASE="unt_db"
HOST="127.0.0.1"
PORT="3306"

# Prompt pour le mot de passe
read -s -p "Mot de passe BDD pour l'utilisateur $USER : " PASSWORD
echo

# Fichier de sortie
OUTPUT="dump_bdd.sql"

# Ordre des tables
TABLES=(auteur dewey dewey_perso discipline etablissement groupe keyword licence niveau tdocument tpedagogie univerique indexing_config user dossier notice dewey_group dewey_group_dewey discipline_group discipline_group_specialites notice_auteur notice_dewey notice_dewey_perso notice_discipline notice_etablissement notice_keyword notice_niveau notice_notice notice_tdocument notice_tpedagogie univerique_discipline)

echo "Export SQL démarré..."

# Supprime l'ancien dump s'il existe
rm -f "$OUTPUT"

# En-tête du fichier SQL avec la date d'export
echo "-- Export réalisé le $(date '+%Y-%m-%d %H:%M:%S')" >> "$OUTPUT"
echo >> "$OUTPUT"

# Export table par table, dans l'ordre voulu
for table in "${TABLES[@]}"; do
  echo "Export de la table $table..."
  mysqldump \
    --host="$HOST" \
    --port="$PORT" \
    --user="$USER" \
    --password="$PASSWORD" \
    --no-create-info \
    --skip-add-locks \
    --skip-comments \
    --skip-disable-keys \
    --complete-insert \
    "$DATABASE" "$table" \
    >> "$OUTPUT"
done

echo "Export terminé : $OUTPUT"
