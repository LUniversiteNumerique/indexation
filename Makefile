#!/usr/bin/make -f
path=`pwd`

SRC_DIR := src
APP=docker compose --env-file lunt-backoffice/.env

dev:
	$(APP) up -d

ddev:
	$(APP) down

dstop:
	docker stop $(docker ps -aq)

bsh:
	$(APP) exec -it backoffice bash

######################################
#                                    #
#  chmod -R a+rw public/uploads/     #
#  rm -rf public/assets              #
#  php bin/console as:co             #
#  rm -rf var/cache/prod/*           #
#  chown -R www-data:www-data var/   #
#  chown -R www-data:www-data /opt/  #
#                                    #
######################################