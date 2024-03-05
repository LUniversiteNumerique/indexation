#!/bin/sh
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice php bin/console doctrine:database:drop --force
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice php bin/console doctrine:database:create
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice php bin/console m:mi -n
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice php bin/console d:m:mi -n
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/groupe.sql)"'
docker-compose -f /home/user/lunt-indexation-notice/docker-compose.yml exec backoffice sh -c 'php bin/console d:q:sql -n "$(cat /var/www/site/sql/samples-data/user.sql)"'
