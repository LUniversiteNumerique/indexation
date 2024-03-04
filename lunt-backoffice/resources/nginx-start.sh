#!/bin/sh
envsubst '$SERVER_NAME' < /etc/nginx/conf.d/templates/default.conf.template > /etc/nginx/conf.d/default.conf && /usr/sbin/nginx -g "daemon off;"
