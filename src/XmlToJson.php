<?php

namespace WPSH_Replicator;

/**
 * Import data from JSON schema into WordPress.
 */
class XmlToJson extends CliTool {

	protected function load_xml( $file ) {
		$xml = file_get_contents( $file );
		$xml = $this->escape_xml( $xml );
		$xml = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_PARSEHUGE );

		if ( ! $xml ) {
			return $this->error( sprintf(
				'Failed to parse XML %s',
				$file
			) );
		}

		return $xml;
	}

	public function parse_terms( $file ) {
		$xml = $this->load_xml( $file );
		$ns = $xml->getNamespaces( true );

		return $this->process_terms( $xml->channel->children( $ns['wp'] ) );
	}

	public function parse_users( $file ) {
		$xml = $this->load_xml( $file );
		$ns = $xml->getNamespaces( true );

		return $this->process_users( $xml->channel->children( $ns['wp'] ) );
	}

	public function parse( $xml_file ) {
		$mime_types = [
			'gif' => 'image/gif',
			'jpg' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png' => 'image/gif',
		];

		$data = [
			'posts' => [],
			'postmeta' => [],
			'term_objects' => [],
		];

		$xml = $this->load_xml( $xml_file );
		$ns = $xml->getNamespaces( true );

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

		return $data;
	}

	protected function escape_xml( $xml ) {
		// Remove all invalid characters per XML spec:
		// @see https://www.w3.org/TR/xml11/#charsets
		$xml = preg_replace( '/[^\x9\xA\xB\xD\x20-\xD7FF\xE000-\xFFFD\x10000-x10FFFF]/u', ' ', $xml );

		// Escape XML entities.
		// @todo Prevent from touching the CDATA content.
		return preg_replace_callback( '/>([^<>]+)<\//', function( $matches ) {
			return sprintf(
				'>%s</',
				htmlentities( html_entity_decode( $matches[1] ), ENT_QUOTES | ENT_XML1 )
			);
		}, $xml );
	}

	protected function process_users( $items ) {
		$users = [];

		$term_types = [
			'tag',
			'category',
			'term',
		];

		foreach ( $items as $item ) {
			if ( 'author' === $item->getName() ) {
				$author = $this->prep_user( $item );
				$users[ $author['author_id'] ] = $author;
			}
		}

		return $users;
	}

	protected function process_terms( $items ) {
		$terms = [];

		$term_types = [
			'tag',
			'category',
			'term',
		];

		foreach ( $items as $item ) {
			$item_type = $item->getName();

			if ( in_array( $item_type, $term_types, true ) ) {
				$term = $this->prep_term( $item, $item_type );
				$terms[ $term['term_id'] ] = $term;
			}
		}

		return $terms;
	}

	protected function prep_user( $item ) {
		return array_map( function( $value ) {
			return html_entity_decode( strval( $value ) );
		}, (array) $item );
	}

	protected function prep_term( $item, $type ) {
		$term = [
			'taxonomy' => $type,
		];

		// Looks the same as prep_user().
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

}
