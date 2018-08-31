#!/usr/bin/env bash

# TODO Remove sudo once we figure out how to reload user groups.
sudo docker-compose -f /vagrant/docker-compose.yml up --detach --remove-orphans

# Install WordPress.
. /vagrant/scripts/vagrant/wp-install.sh

echo "Environment is ready!"
