#!/bin/sh
# Script de démarrage du programme nginx
# Avant le lancement du serveur nginx, la configuration du site est actualisée à partir d'une template
envsubst '$SERVER_NAME' < /etc/nginx/conf.d/templates/default.conf.template > /etc/nginx/conf.d/default.conf
/usr/sbin/nginx -g "daemon off;"
