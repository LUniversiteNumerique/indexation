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