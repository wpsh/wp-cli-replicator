#!/usr/bin/env bash

# Run this inside Vagrant, specify -T to disable pseudo-tty allocation.
vagrant ssh -c "cd /vagrant && sudo docker-compose exec -T --user www-data phpfpm wp ${*:1}"
