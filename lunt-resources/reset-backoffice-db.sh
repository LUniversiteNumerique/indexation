#!/bin/sh
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice php bin/console d:d:drop -f
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice php bin/console d:d:create
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/univerique.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice php bin/console m:mi -n
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice php bin/console d:m:mi -n
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/groupe.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/user.sql)"'

docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/etablissement.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/auteur.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/keyword.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/licence.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/niveau.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/tdocument.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/tpedagogie.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/discipline.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/dewey.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/dossier.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/univerique_discipline.sql)"'
