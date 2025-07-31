#!/bin/sh
docker compose -f compose.yml exec backoffice php bin/console d:d:drop -f
docker compose -f compose.yml exec backoffice php bin/console d:d:create
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/univerique.sql)"'
docker compose -f compose.yml exec backoffice php bin/console d:s:u -f
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/groupe.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/etablissement.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/user.sql)"'

docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/auteur.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/keyword.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/licence.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/niveau.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/tdocument.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/tpedagogie.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/dossier.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/univerique_discipline.sql)"'
