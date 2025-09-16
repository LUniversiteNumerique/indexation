#!/usr/bin/make -f
path=`pwd`

SRC_DIR := src
APP=docker compose -f compose.yml -f compose.dev.yml --env-file lunt-backoffice/.env.dev.local

dev:
	$(APP) up -d

ddev:
	$(APP) down

dstop:
	docker stop $(docker ps -aq)

bsh:
	$(APP) exec -it backoffice bash

build:
	$(APP) build --no-cache
