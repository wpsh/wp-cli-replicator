#!/usr/bin/env bash

if [ -z "$1" ]; then
	echo "Please specify the site URL."
	exit
fi

if [ -z "$WP_JSON_DIR" ]; then
	echo "Please specify the WP_JSON_DIR environment variable."
fi

URL="$1"
POST_FILES="$WP_JSON_DIR/posts-*.json"

wp site empty --url="$URL" --yes

# Import options, terms and users first.
wp eval-file "json-import.php" --url="$URL"

# Import the actual posts, post meta and comments.
for file in $POST_FILES; do
	echo "Importing from $file"
	export WP_JSON_FILE="$file"
	wp eval-file "json-import-posts.php" --url="$URL"
done
