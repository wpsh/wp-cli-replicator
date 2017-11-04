<?php

class WP_Json_Importer {

	function __construct() {
		// @todo Check if WP is present.
	}

	function import_options( $option_json ) {
		global $wpdb;

		if ( empty( $option_json ) ) {
			die( 'Failed to read the options.json file.' );
		}

		foreach ( $option_json->options as $option_key => $option_value ) {
			delete_option( $option_key );

			// Assume the theme mods are for this theme.
			if ( 0 === strpos( $option_key, 'theme_mods_' ) ) {
				$theme_slug = get_option( 'stylesheet' );

				$wpdb->insert( $wpdb->options, [
					'option_name' => 'theme_mods_' . $theme_slug,
					'option_value' => $option_value,
				] );
			}

			$wpdb->insert( $wpdb->options, [
				'option_name' => $option_key,
				'option_value' => $option_value,
			] );

			$this->log( sprintf( 'Inserting option %s', $option_key ) );
		}

		flush_rewrite_rules();
	}

	function get_user_login_id_map() {
		$login_user_map = [];
		$users = get_users();

		foreach ( $users as $user ) {
			$login_user_map[ $user->user_login ] = $user->ID;
		}

		return $login_user_map;
	}

	function get_image_placeholder_file() {
		$placeholder_url = get_avatar_url(
			'team-atlas@xwp.co',
			array(
				'size' => 1000
			)
		);

		$file = wp_upload_bits(
			'placeholder.jpg',
			null,
			file_get_contents( $placeholder_url ) // @todo replace with wp_remote_get().
		);

		if ( empty( $file['file'] ) ) {
			die( 'Failed to create a placeholder image file.' );
		}

		return $file;
	}

	function import_posts( $files ) {
		global $wpdb;

		if ( empty( $files ) ) {
			die( 'Failed to find post JSON files.' );
		}

		$term_query = new WP_Term_Query();
		$login_user_map = $this->get_user_login_id_map();
		$placeholder_file = $this->get_image_placeholder_file();
		$term_ids_by_tax = [];

		foreach ( $files as $file ) {
			$posts_done = [];
			$time_import_start = time();

			$this->log( sprintf(
				'Inserting posts from %s (memory %d)',
				basename( $file ),
				memory_get_usage() / 1024 / 1024
			) );

			$json = file_get_contents( $file );
			$import = json_decode( $json );

			$wpdb->query( 'START TRANSACTION' ); // 100x performance boost.

			foreach ( $import->posts as $post ) {

				// @todo Add a flag to disable this.
				// $post_exists = (int) $wpdb->get_var( $wpdb->prepare(
				// 	"SELECT COUNT(*) FROM $wpdb->posts WHERE ID = %d",
				// 	$post->post_id
				// ) );
				//
				// if ( ! empty( $post_exists ) ) {
				// 	$this->log( sprintf(
				// 		'Skipping post ID %d, already exists.',
				// 		$post->post_id
				// 	) );
				//
				// 	continue;
				// }

				$post_author = '';
				if ( isset( $login_user_map[ $post->author ] ) ) {
					$post_author = $login_user_map[ $post->author ];
				}

				// @todo Bail if post ID already exists.
				$wpdb->insert( $wpdb->posts, [
					'ID' => $post->post_id,
					'guid' => $post->guid,
					'post_title' => $post->title,
					'post_name' => $post->post_name,
					'post_author' => $post_author,
					'post_date' => $post->post_date,
					'post_date_gmt' => $post->post_date_gmt,
					'post_excerpt' => $post->description,
					'post_content' => $post->post_content,
					'post_status' => $post->status,
					'post_parent' => $post->post_parent,
					'post_type' => $post->post_type,
					'post_mime_type' => $post->post_mime_type,
				] );

				$posts_done[] = $post->post_id;

				/*
				$this->log( sprintf(
					'Inserted post [%s]: %s (%d)',
					$post->post_type,
					substr( $post->title, 0, 10 ),
					$post->post_id
				) );
				*/

				unset( $post );
			}

			// Insert post term relationships.
			foreach ( $import->term_objects as $taxonomy => $term_slugs ) {

				if ( ! isset( $term_ids_by_tax[ $taxonomy ] ) ) {
					$term_ids_by_tax[ $taxonomy ] = [];
				}

				foreach ( $term_slugs as $slug => $object_ids ) {
					$term = $term_query->query( [
						'slug' => $slug,
						'taxonomy' => $taxonomy,
						'get' => 'all',
						'number' => 1,
					] );

					if ( ! empty( $term[0] ) ) {
						$term_ids_by_tax[ $taxonomy ][] = $term[0]->term_id;

						foreach ( $object_ids as $post_id ) {
							if ( ! in_array( $post_id, $posts_done ) ) {
								continue;
							}

							$wpdb->insert( $wpdb->term_relationships, [
								'object_id' => $post_id,
								'term_taxonomy_id' => $term[0]->term_taxonomy_id,
							] );

							/*
							$this->log( sprintf(
								'Adding post %d to %s (%s)',
								$post_id,
								$term[0]->name,
								$taxonomy
							) );
							*/
						}
					}

					unset( $term );
				}
			}

			// Insert post meta
			foreach ( $import->postmeta as $postmeta ) {
				$meta_value = $postmeta->meta_value;

				if ( ! in_array( $postmeta->post_id, $posts_done ) ) {
					continue;
				}

				if ( '_wp_attached_file' === $postmeta->meta_key ) {
					$meta_value = $placeholder_file['file'];
				} elseif ( '_wp_attachment_metadata' === $postmeta->meta_key ) {
					$meta_unserialized = unserialize( $meta_value );

					if ( is_array( $meta_unserialized ) ) {
						$meta_unserialized['file'] = $placeholder_file['file'];
						$meta_value = serialize( $meta_unserialized );
					} else {
						$this->log( sprintf(
							'Failed to unserialize _wp_attachment_metadata for post %d, value %s',
							$postmeta->post_id,
							$meta_value
						) );
					}
				}

				$wpdb->insert( $wpdb->postmeta, [
					'post_id' => $postmeta->post_id,
					'meta_key' => $postmeta->meta_key,
					'meta_value' => $meta_value,
				] );

				unset( $postmeta );
			}

			$wpdb->query( 'COMMIT' );

			$this->log( sprintf(
				'Import from %s took %d seconds.',
				basename( $file ),
				time() - $time_import_start
			) );

			unset( $xml, $import, $posts_done, $meta_value, $meta_unserialized ); // Save memory.

		}

		foreach ( $term_ids_by_tax as $taxonomy => $term_ids ) {
			$term_ids = array_unique( $term_ids );

			$this->log( sprintf(
				'Updating term counts for %s',
				$taxonomy
			) );

			wp_update_term_count_now( $term_ids, $taxonomy );
		}
	}

	function import_terms( $terms ) {
		global $wpdb;

		$term_defaults = [
			'term_id' => null,
			'name' => null,
			'slug' => null,
			'taxonomy' => null,
			'description' => '',
			'parent' => 0,
		];

		$wpdb->query( 'START TRANSACTION' );

		foreach ( $terms as $term ) {
			$term = wp_parse_args( $term, $term_defaults );

			$wpdb->insert( $wpdb->terms, [
				'term_id' => $term['term_id'],
				'name' => $term['name'],
				'slug' => $term['slug'],
			] );

			$wpdb->insert( $wpdb->term_taxonomy, [
				'term_id' => $term['term_id'],
				'taxonomy' => $term['taxonomy'],
				'description' => $term['description'],
				'parent' => $term['parent'],
			] );

			$this->log( sprintf(
				'Inserted term [%s]: %s (%d)',
				$term['taxonomy'],
				$term['name'],
				$term['term_id']
			) );
		}

		$wpdb->query( 'COMMIT' );
	}

	function import_users( $users ) {
		global $wpdb;

		//$wpdb->query( "TRUNCATE TABLE $wpdb->users" );
		//$wpdb->query( "TRUNCATE TABLE $wpdb->usermeta" );

		$password = wp_hash_password( 'wordpress' );

		foreach ( $users as $user ) {
			$user = (array) $user;

			// Remove an existing user.
			$current_user = get_user_by( 'login', $user['author_login'] );
			if ( ! empty( $current_user ) ) {
				wp_delete_user( $current_user->ID );
			}

			$wpdb->insert( $wpdb->users, [
				'ID' => $user['author_id'],
				'user_login' => $user['author_login'],
				'user_pass' => $password,
				'user_nicename' => $user['author_login'],
				'user_email' => $user['author_email'],
				'display_name' => $user['author_display_name'],
			] );

			// Add the user to the current site.
			add_user_to_blog( get_current_blog_id(), $user['author_id'], 'editor' );

			$this->log( sprintf(
				'Inserted user %s: %d',
				$user['author_login'],
				$user['author_id']
			) );
		}
	}

	protected function log( $message ) {
		echo $message . "\n";
	}

}
