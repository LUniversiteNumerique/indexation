#!/bin/sh
docker compose -f compose.yml exec backoffice php bin/console d:d:drop -f
docker compose -f compose.yml exec backoffice php bin/console d:d:create
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/univerique.sql)"'
docker compose -f compose.yml exec backoffice php bin/console d:s:u -f
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/groupe.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/etablissement.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/user.sql)"'

docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/auteur.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/keyword.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/licence.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/niveau.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/tdocument.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/tpedagogie.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/discipline.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/dewey.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/dossier.sql)"'
docker compose -f compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat data/sql/univerique_discipline.sql)"'
