#!/usr/bin/env bash

docker-compose -f /vagrant/docker-compose.yml up --detach --remove-orphans

echo "Environment is ready!"
