#!/usr/bin/make -f
path=`pwd`

SRC_DIR := src
APP=docker compose -f compose.yml -f compose.dev.yml --env-file lunt-backoffice/.env.dev.local

build:
	$(APP) build --no-cache

dev-start:
	$(APP) up -d

dev-stop:
	$(APP) down

bo-bash:
	$(APP) exec -it backoffice bash

db-connect:
	$(APP) exec -it mariadb mysql -u root -p -D unt_db

asset-compile:
	$(APP) exec -it backoffice php bin/console asset-map:compile

