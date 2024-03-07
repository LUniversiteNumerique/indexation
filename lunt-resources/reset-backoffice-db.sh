#!/bin/sh
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice php bin/console doctrine:database:drop --force
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice php bin/console doctrine:database:create
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice php bin/console m:mi -n
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice php bin/console d:m:mi -n
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/groupe.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/user.sql)"'

docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/etablissement.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/auteur.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/keyword.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/licence.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/niveau.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/tdocument.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/tpedagogie.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/univerique.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/discipline.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/dewey.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/univerique_discipline.sql)"'

docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/notice.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/notice_auteur.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/notice_etablissement.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/notice_keyword.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/notice_niveau.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/notice_tdocument.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/notice_tpedagogie.sql)"'
