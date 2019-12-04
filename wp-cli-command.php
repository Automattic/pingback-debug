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

		$post_id = absint( $args[0] );

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
		$post_id = absint( $args[0] );

		$post = get_post( $post_id );
		if ( ! $post ) {
			WP_CLI::error( 'Post not found.' );
		}

		$content = $post->post_content;

		$post_links_temp = wp_extract_urls( $content );

		$post_links = $this->debug_urls( $post_links_temp, $post );

		if ( true === empty( $post_links ) ) {
			WP_CLI::error( 'No links found in the post.' );
		}

		WP_CLI\Utils\format_items( 'table', $post_links, array( 'URL', 'Pung', 'Pingable', 'Reason' ) );
	}

	/**
 	 * Collect data on a specific URL, eventually in terms of a post.
 	 *
	 * <URL>
	 * : URL to inspect. Ideally should be exactly the same as used in the post.
	 *
	 * [--post_id=<ID>]
	 * : When provided, gets the data on the failure in terms of a specific post.
	 *
	 * ## Examples
	 *
	 *  wp pingback-debug example.org
	 * 	wp pingback-debug example.org --post_id=10
	 */
	public function url( $args, $assoc_args ) {
		$post = null;
		$url  = $args[0];

		if ( true === isset( $assoc_args['post_id'] ) ) {
			$post_id = absint( $assoc_args['post_id'] );

			if ( $post_id ) {
				$post = get_post( $post_id );

				if ( ! $post ) {
					WP_CLI::error( 'Post not found.' );
				}
			}
		}

		$post_links = $this->debug_urls( array( $url ), $post );

		WP_CLI\Utils\format_items( 'table', $post_links, array( 'URL', 'Pung', 'Pingable', 'Reason' ) );
	}

	private function debug_urls( $post_links_temp, $post = null )	{

		$post_links = array();
		$pung 		= array();
		$post_id    = false;

		if ( true === is_a( $post, 'WP_Post' ) ) {
			$pung    = get_pung( $post );
			$post_id = $post->ID;
		}

		foreach( (array) $post_links_temp as $link_test ) {

			$post_links[ $link_test ] = array(
				'URL'      => $link_test,
				'Pung'     => 'false',
				'Pingable' => 'true',
				'Reason'   => 'Not enough data on what went wrong.',
			);

			if ( in_array( $link_test, $pung ) ) {
				$post_links[ $link_test ]['Pung']   = 'true';
				$post_links[ $link_test ]['Reason'] = 'Was pung already.';
				continue;
			}
			if ( $post_id &&  ! empty( get_post_meta( $post_id, '_pingback_debug_' . md5( $link_test ), true ) ) ) {
				$post_links[ $link_test ]['Pung']   = 'true';
				$post_links[ $link_test ]['Reason'] = get_post_meta( $post_id, '_pingback_debug_' . md5( $link_test ), true );
				continue;
			}
			if ( url_to_postid( $link_test ) == $post_id ) {
				$post_links[ $link_test ]['Reason']   = 'A link to itself.';
				$post_links[ $link_test ]['Pingable'] = 'false';
				continue;
			}
			if ( is_local_attachment( $link_test ) ) {
				$post_links[ $link_test ]['Reason']   = 'Local attachments are never pinged.';
				$post_links[ $link_test ]['Pingable'] = 'false';
				continue;
			}
			$test = @parse_url( $link_test );
			if ( ! $test ) {
				$post_links[ $link_test ]['Reason']   = 'Unable to parse the URL.'; 
				$post_links[ $link_test ]['Pingable'] = 'false';
				continue;
			}
			if ( ! isset( $test['query'] ) && ! isset( $test['path'] ) || ( $test['path'] == '/' ) || ( $test['path'] == '' ) ) {
				$post_links[ $link_test ]['Reason']   = 'Missing query part and/or path in URL.';
				$post_links[ $link_test ]['Pingable'] = 'false';
				continue;
			}
			$pingback_server_url = discover_pingback_server_uri( $link_test );
			if ( false === $pingback_server_url ) {
				$post_links[ $link_test ]['Reason'] = 'Unable to discover the pingback server URL.';
				continue;
			}
		}

		return $post_links;
	}
}

WP_CLI::add_command( 'pingback-debug', 'Pingback_Debug_Command' );
