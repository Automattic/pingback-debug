<?php

class Pingback_Debug_Command {
	
	/**
	 * Gets info on all failed pingbacks.
	 */
	public function all( $args, $assoc_args ) {
		global $wpdb;
		
		$metas = $wpdb->get_results( "SELECT * FROM {$wpdb->postmeta} WHERE meta_key LIKE '_pingback_debug_%'" );

		WP_CLI\Utils\format_items( 'table', $metas, array( 'post_id', 'meta_key', 'meta_value' ) );
	}

	/**
	 * Get saved data of failed pingbacks for a post.
	 *
	 * <ID>
	 * : ID of a post which you want to inspect.
	 *
	 * ## Examples
	 *
	 *	wp pingback-debug failed 1
	 */
	public function failed( $args, $assoc_args ) {
		global $wpdb;

		$post_id = intval( $args[0] );

		$metas = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE '_pingback_debug_%'", $post_id ) );

		if ( true === empty( $metas ) ) {
			WP_CLI::error( 'No failed pingbacks.' );
		}

		WP_CLI\Utils\format_items( 'table', $metas, array( 'meta_key', 'meta_value' ) );
	}

	/**
	 * Inspect the status of URLs a of a post.
	 *
	 * <ID>
	 * : ID of a post which you want to inspect.
	 *
	 * ## Examples
	 *
	 *	wp pingback-debug post 44
	 */
	public function post( $args, $assoc_args ) {
		$post_id = intval( $args[0] );

		$post = get_post( $post_id );
		if ( ! $post ) {
			WP_CLI::error( 'Post not found.' );
		}

		$post_links = array();

		$pung = get_pung( $post );

		$content = $post->post_content;

		$post_links_temp = wp_extract_urls( $content );

		foreach( (array) $post_links_temp as $link_test ) {
			if ( in_array( $link_test, $pung ) ) {
				$post_links[] = array( 'URL' => $link_test, 'Pung' => 'true', 'Pingable' => 'false', 'Reason' => 'Was pung already.' );
				continue;
			}
			if ( ! empty( get_post_meta( $post->ID, '_pingback_debug_' . md5( $link_test ), true ) ) ) {
				$post_links[] = array( 'URL' => $link_test, 'Pung' => 'true', 'Pingable' => 'true', 'Reason' => get_post_meta( $post->ID, '_pingback_debug_' . md5( $link_test ), true ) );
				continue;
			}
			if ( url_to_postid( $link_test ) == $post->ID ) {
				$post_links[] = array( 'URL' => $link_test, 'Pung' => 'false', 'Pingable' => 'false', 'Reason' => 'A link to itself.' );
				continue;
			}
			if ( is_local_attachment( $link_test ) ) {
				$post_links[] = array( 'URL' => $link_test, 'Pung' => 'false', 'Pingable' => 'false', 'Reason' => 'Local attachments are never pinged.' );
				continue;
			}
			$test = @parse_url( $link_test );
			if ( ! $test ) {
				$post_links[] = array( 'URL' => $link_test, 'Pung' => 'false', 'Pingable' => 'false', 'Reason' => 'Unable to parse the URL.' );
				continue;
			}
			if ( ! isset( $test['query'] ) && ! isset( $test['path'] ) || ( $test['path'] == '/' ) || ( $test['path'] == '' ) ) {
				$post_links[] = array( 'URL' => $link_test, 'Pung' => 'false', 'Pingable' => 'false', 'Reason' => 'Missing query part and/or path in URL.' );
				continue;
			}
			$pingback_server_url = discover_pingback_server_uri( $link_test );
			if ( false === $pingback_server_url ) {
				$post_links[] = array( 'URL' => $link_test, 'Pung' => 'false', 'Pingable' => 'false', 'Reason' => 'Unable to discover the pingback server URL.' );
				continue;
			}
			$post_links[] = array( 'URL' => $link_test, 'Pung' => 'false', 'Pingable' => 'true', 'Reason' => 'Not enough data on what went wrong.' );
		}

		if ( true === empty( $post_links ) ) {
			WP_CLI::error( 'No links found in the post.' );
		}

		WP_CLI\Utils\format_items( 'table', $post_links, array( 'URL', 'Pung', 'Pingable', 'Reason' ) );
	}
}

WP_CLI::add_command( 'pingback-debug', 'Pingback_Debug_Command' );
