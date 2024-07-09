#!/bin/sh
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice php bin/console d:d:drop -f
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice php bin/console d:d:create
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice php bin/console m:mi -n
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice php bin/console d:m:mi -n
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/sql/groupe.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/sql/user.sql)"'

docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/sql/etablissement.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/sql/auteur.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/sql/keyword.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/sql/licence.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/sql/niveau.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/sql/tdocument.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/sql/tpedagogie.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/sql/univerique.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/sql/discipline.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/sql/dewey.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /opt/lunt-resources/sql/univerique_discipline.sql)"'
