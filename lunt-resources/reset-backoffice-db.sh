#!/bin/sh
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice php bin/console doctrine:database:drop --force
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice php bin/console doctrine:database:create
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice php bin/console m:mi -n
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice php bin/console d:m:mi -n
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/groupe.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/user.sql)"'

docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/etablissement.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/auteur.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/keyword.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/licence.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/niveau.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/tdocument.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/tpedagogie.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/univerique.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/discipline.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/dewey.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/univerique_discipline.sql)"'

docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/notice.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/notice_auteur.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/notice_etablissement.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/notice_keyword.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/notice_niveau.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/notice_tdocument.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/data/sql/notice_tpedagogie.sql)"'
