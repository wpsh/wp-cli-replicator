#!/usr/bin/env bash

set -e

# Always land in the synced directory. Should we change the home directory instead?
echo "cd /vagrant" >> ~/.bashrc

# Map wp to wp-cli inside the container.
echo "alias wp='docker-compose --file /vagrant/docker-compose.yml exec -T --user www-data phpfpm wp \"\${@:2}\"'" >> ~/.bashrc

# Map binary name to source URL.
REPOS=(
	"docker-compose,https://github.com/docker/compose/releases/download/1.22.0/docker-compose-`uname -s`-`uname -m`"
	"composer,https://getcomposer.org/download/1.7.2/composer.phar"
)

# Add Docker repo.
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo apt-key add -
sudo add-apt-repository "deb [arch=amd64] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable"

sudo apt-get update
sudo apt-get install -y \
	docker-ce avahi-daemon php-cli php-curl php-dom

# Ensure we can run docker commands without extra permissions.
sudo usermod -a -G docker $USER

# Install various binary scripts.
for repo_set in "${REPOS[@]}"; do
	while IFS=',' read -ra repo; do
		dest_path="/usr/local/bin/${repo[0]}"

		if [ ! -f "$dest_path" ]; then
			echo "Installing ${repo[1]} to $dest_path"

			sudo curl -sL "${repo[1]}" -o "$dest_path"
			sudo chmod +x "$dest_path"
		fi
	done <<< "$repo_set"
done
