<?php

class WP_Json_Importer {

	/**
	 * @var \wpdb Instance of WordPress \wpdb.
	 */
	protected $db;

	public function __construct( $db ) {
		$this->db = $db;
	}

	public function import_options( $option_json ) {
		if ( empty( $option_json->options ) ) {
			die( 'Options empty.' );
		}

		foreach ( $option_json->options as $option_key => $option_value ) {
			delete_option( $option_key );

			// Assume the theme mods are for this theme.
			if ( 0 === strpos( $option_key, 'theme_mods_' ) ) {
				$theme_slug = get_option( 'stylesheet' );

				$this->db->insert( $this->db->options, [
					'option_name' => 'theme_mods_' . $theme_slug,
					'option_value' => $option_value,
				] );
			}

			$this->db->insert( $this->db->options, [
				'option_name' => $option_key,
				'option_value' => $option_value,
			] );

			$this->log( sprintf( 'Inserting option %s', $option_key ) );
		}

		flush_rewrite_rules();
	}

	public function get_user_login_id_map() {
		$login_user_map = [];
		$users = get_users();

		foreach ( $users as $user ) {
			$login_user_map[ $user->user_login ] = $user->ID;
		}

		return $login_user_map;
	}

	public function get_image_placeholder_file() {
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

	public function import_post( $import ) {
		$posts_done = [];
		$term_ids_by_tax = [];
		$time_import_start = time();
		$term_query = new WP_Term_Query();
		$login_user_map = $this->get_user_login_id_map();
		$placeholder_file = $this->get_image_placeholder_file();

		$this->db->query( 'START TRANSACTION' ); // 100x performance boost.

		foreach ( $import->posts as $post ) {

			// @todo Add a flag to disable this.
			// $post_exists = (int) $this->db->get_var( $this->db->prepare(
			// 	"SELECT COUNT(*) FROM $this->db->posts WHERE ID = %d",
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
			$this->db->insert( $this->db->posts, [
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

						$this->db->insert( $this->db->term_relationships, [
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

			$this->db->insert( $this->db->postmeta, [
				'post_id' => $postmeta->post_id,
				'meta_key' => $postmeta->meta_key,
				'meta_value' => $meta_value,
			] );

			unset( $postmeta );
		}

		$this->db->query( 'COMMIT' );

		$this->log( sprintf(
			'Import took %d seconds.',
			time() - $time_import_start
		) );

		foreach ( $term_ids_by_tax as $taxonomy => $term_ids ) {
			$term_ids = array_unique( $term_ids );

			if ( empty( $term_ids ) ) {
				continue;
			}

			$this->log( sprintf(
				'Updating term counts for %s',
				$taxonomy
			) );

			// @todo This requires the taxonomy to be registered and post types associated with the taxonomy.
			// wp_update_term_count_now( $term_ids, $taxonomy );
		}

		unset( $import, $posts_done, $meta_value, $meta_unserialized, $term_ids_by_tax ); // Save memory.

		// $this->db->flush(); // Try mysqli_free_result() and unset things.

	}

	public function import_terms( $terms ) {
		$term_defaults = [
			'term_id' => null,
			'name' => null,
			'slug' => null,
			'taxonomy' => null,
			'description' => '',
			'parent' => 0,
		];

		$this->db->query( 'START TRANSACTION' );

		foreach ( $terms as $term ) {
			$term = wp_parse_args( $term, $term_defaults );

			$this->db->insert( $this->db->terms, [
				'term_id' => $term['term_id'],
				'name' => $term['name'],
				'slug' => $term['slug'],
			] );

			$this->db->insert( $this->db->term_taxonomy, [
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

		$this->db->query( 'COMMIT' );
	}

	public function import_users( $users ) {
		//$this->db->query( "TRUNCATE TABLE $this->db->users" );
		//$this->db->query( "TRUNCATE TABLE $this->db->usermeta" );

		$password = wp_hash_password( 'wordpress' );

		foreach ( $users as $user ) {
			$user = (array) $user;

			// Remove an existing user.
			$current_user = get_user_by( 'login', $user['author_login'] );
			if ( ! empty( $current_user ) ) {
				wp_delete_user( $current_user->ID );
			}

			$this->db->insert( $this->db->users, [
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

	public function log( $message ) {
		echo $message . "\n";
	}

}
