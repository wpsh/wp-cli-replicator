# WP Site Replicator

Quickly create replicas of large production websites from WordPress eXtended RSS (WXR) export files.


## Requirements

- WP CLI


## Instructions

Please note that this implementation relies on writing directly to the WP database so both post and term tables should be empty. All existing users with the same login name will be deleted and new users created with password `wordpress`.

1. Parse the exported WXR files into JSON files for site users, terms and posts:

		$ php xml-to-json.php path/to/wxr/files

	All WXR files `path/to/wxr/files/*.xml` are parsed and stored in `path/to/wxr/files/json` -- `users.json`, `terms.json` and `posts-*.json`.

2. Run the actual import:

		$ export WP_JSON_DIR="path/to/wxr/files/json" 
		$ ./import.sh http://example.local

Where `http://example.local` is the URL of the site where the data should be imported.
