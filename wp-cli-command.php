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
	 * Get failed pingbacks for a post.
	 *
	 * <ID>
	 * : The ID of a post which you want to inspect.
	 *
	 * ## Examples
	 *
	 *	wp pingback-debug post 1
	 */
	public function post( $args, $assoc_args ) {
		global $wpdb;

		$post_id = intval( $args[0] );

		$metas = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE '_pingback_debug_%'", $post_id ) );

		if ( true === empty( $metas ) ) {
			WP_CLI::error( 'No failed pingbacks.' );
		}

		WP_CLI\Utils\format_items( 'table', $metas, array( 'meta_key', 'meta_value' ) );
	}
}

WP_CLI::add_command( 'pingback-debug', 'Pingback_Debug_Command' );
