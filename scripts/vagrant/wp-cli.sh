#!/usr/bin/env bash

# wp is an alias we defined during provision.
vagrant ssh -c 'wp "${@:2}"'
