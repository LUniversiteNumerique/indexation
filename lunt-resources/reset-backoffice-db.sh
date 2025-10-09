#!/bin/sh
docker compose -f compose.yml exec backoffice php bin/console d:d:drop -f
docker compose -f compose.yml exec backoffice php bin/console d:d:create
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/referentiels/univerique.sql)"'
docker compose -f compose.yml exec backoffice php bin/console d:s:u -f
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/referentiels/groupe.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/referentiels/etablissement.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/referentiels/user.sql)"'

docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/referentiels/auteur.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/referentiels/keyword.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/referentiels/licence.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/referentiels/niveau.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/referentiels/tdocument.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/referentiels/tpedagogie.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/referentiels/dossier.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/referentiels/univerique_discipline.sql)"'
