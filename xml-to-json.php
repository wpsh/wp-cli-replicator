<?php

ini_set( 'memory_limit', -1 );

if ( empty( $argv[1] ) ) {
	die( 'Please specify the wildcard to the XML files. For example, export' );
}

$xml_dir = rtrim( $argv[1], '/' );
$files = glob( $xml_dir . '/*.xml' );

if ( empty( $files ) ) {
	die( 'No files found at ' . $argv[1] );
}

$json_dir = dirname( $xml_dir ) . '/json';

if ( ! file_exists( $json_dir ) ) {
	mkdir( $json_dir );
}

$mime_types = [
	'gif' => 'image/gif',
	'jpg' => 'image/jpeg',
	'jpeg' => 'image/jpeg',
	'png' => 'image/gif',
];

foreach ( $files as $no => $xml_file ) {
	$data = [
		'posts' => [],
		'postmeta' => [],
		'term_objects' => [],
	];

	printf( "Parsing (%d/%d) %s \n", $no + 1, count( $files ), basename( $xml_file ) );

	$xml = file_get_contents( $xml_file );
	$xml = escape_xml( $xml );
	$xml = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_PARSEHUGE );

	if ( ! $xml ) {
		die( sprintf( 'Error at %s.', $xml_file ) );
	}

	$ns = $xml->getNamespaces( true );

	// Terms and users are repeated in every export file.
	if ( ! isset( $terms_and_users ) ) {
		$terms_and_users = process_users_terms( $xml->channel->children( $ns['wp'] ) );

		file_put_contents( $json_dir . '/terms.json', json_encode( $terms_and_users['terms'] ) );
		file_put_contents( $json_dir . '/users.json', json_encode( $terms_and_users['users'] ) );
	}

	foreach ( $xml->channel->item as $item ) {
		$post = [];
		$wp = $item->children( $ns['wp'] );
		$post_id = (int) $wp->post_id;

		$post['author'] = (string) $item->children( $ns['dc'] )->creator;
		$post['post_content'] = (string) $item->children( $ns['content'] )->encoded;
		$post['post_mime_type'] = '';

		if ( ! empty( $wp->attachment_url ) ) {
			$extension = pathinfo( $wp->attachment_url, PATHINFO_EXTENSION );

			if ( isset( $mime_types[ $extension ] ) ) {
				$post['post_mime_type'] = $mime_types[ $extension ];
			}
		}

		foreach ( $item as $key => $element ) {
			$attributes = $element->attributes();

			if ( isset( $attributes->domain ) ) {
				$taxonomy = (string) $attributes->domain;
				$slug = (string) $attributes->nicename;

				if ( ! isset( $data['term_objects'][ $taxonomy ] ) ) {
					$data['term_objects'][ $taxonomy ] = [
						$slug => [],
					];
				}

				$data['term_objects'][ $taxonomy ][ $slug ][] = $post_id;
			} else {
				$post[ (string) $key ] = (string) $element;
			}
		}

		foreach ( $wp as $key => $wp_item ) {
			if ( ! $wp_item->count() ) {
				$post[ (string) $key ] = (string) $wp_item;
			} elseif ( 'postmeta' === $wp_item->getName() ) {
				$data['postmeta'][] = array(
					'post_id' => $post_id,
					'meta_key' => (string) $wp_item->meta_key,
					'meta_value' => (string) $wp_item->meta_value,
				);
			}
		}

		$data['posts'][ $post_id ] = $post;

		unset( $post, $wp );
	}

	$filename = sprintf( '%s/posts-%s.json', $json_dir, basename( $xml_file, '.xml' ) );

	file_put_contents( $filename, json_encode( $data ) );

	unset( $xml, $data );

}

function escape_xml( $xml ) {
	// Remove all invalid characters per XML spec:
	// #x9 | #xA | #xD | [#x20-#xD7FF] | [#xE000-#xFFFD] | [#x10000-#x10FFFF]
	$xml = preg_replace( '/[^\x9\xA\xD\x20-\xD7FF\xE000-\xFFFD\x10000-x10FFFF]/', ' ', $xml );

	// Escape ampersands in regular attribute values.
	$xml = preg_replace_callback( '/>(.+?)<\//', function( $matches ) {
		if ( false === strpos( $matches[1], '<![CDATA' ) ) {
			// Replace ampersands that don't have the ";" character after atleast 6 chars. Thank you VIP.
			$matches[0] = preg_replace( '/(&(?![^\s]{0,5};))/', '&amp;', $matches[0] );
		}

		return $matches[0];
	}, $xml );

	return $xml;
}

function process_users_terms( $items ) {
	$data = [
		'users' => [],
		'terms' => [],
	];

	$term_types = [
		'tag',
		'category',
		'term',
	];

	foreach ( $items as $item ) {
		$item_type = $item->getName();

		if ( 'author' === $item_type ) {
			$author = prep_user( $item );

			$data['users'][ $author['author_id'] ] = $author;
		} elseif ( in_array( $item_type, $term_types, true ) ) {
			$term = prep_term( $item, $item_type );

			$data['terms'][ $term['term_id'] ] = $term;
		}
	}

	return $data;
}

function prep_user( $item ) {
	return array_map( function( $value ) {
		return html_entity_decode( strval( $value ) );
	}, (array) $item );
}

function prep_term( $item, $type ) {
	$term = [
		'taxonomy' => $type,
	];

	$item = array_map( function( $value ) {
		return html_entity_decode( strval( $value ) );
	}, (array) $item );

	foreach ( $item as $key => $value ) {
		$key = str_replace( $type . '_', '', $key );

		if ( 'id' === $key ) {
			$key = 'term_id';
		} elseif ( 'cat_name' === $key ) {
			$key = 'name';
		} elseif ( 'nicename' === $key ) {
			$key = 'slug';
		}

		$term[ $key ] = $value;
	}

	if ( 'tag' === $type ) {
		$term['taxonomy'] = 'post_tag';
	}

	return $term;
}
