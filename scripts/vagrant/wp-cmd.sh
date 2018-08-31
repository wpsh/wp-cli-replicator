#!/usr/bin/env bash

docker-compose --file /vagrant/docker-compose.yml exec -T --user www-data phpfpm wp "$*"
