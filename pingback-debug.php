<?php

/**
 * Plugin Name: Pingback debug
 * Plugin URI:  https://example.com/plugins/the-basics/
 * Description: Debug your outcoming pingbacks
 * Version:     0.1
 * Author:      WordPress.com
 * Author URI:  https://wordpress.com/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pingback-debug
 * Domain Path: /languages
 */

function ping_debug_http_api_debug( $response, $context, $class, $args, $url ) {
	include_once( ABSPATH . WPINC . '/class-IXR.php' );
	include_once( ABSPATH . WPINC . '/class-wp-http-ixr-client.php' );
	
	// Check whether we are hooked to a pingback action.
	$message = new IXR_Message( $args['body' ] );
	if ( ! $message->parse() || 'pingback.ping' !== $message->methodName ) {
		return;
	}

	
	// Check whether the pingback action failed.
	$response = new IXR_Message( $response[ 'body' ] );
	if ( ! $response->parse() || ! isset( $response->faultCode ) || ! isset( $response->faultString ) ) {
		return;
	}
	
	// Store the data.
	$pagelinkedfrom = str_replace( '&amp;', '&', $message->params[0] );
	$pagelinkedto   = str_replace( '&amp;', '&', $message->params[1] );
	$pagelinkedto   = str_replace( '&', '&amp;', $pagelinkedto );	
	$post_id = url_to_postid( $pagelinkedfrom );
	if ( ! $post_id ) {
		return;
	}
	$meta_key = '_pingback_debug_' . md5( $pagelinkedto );
	update_post_meta( $post_id, $meta_key, $response->faultCode . ' : ' . $response->faultString );
}

add_action( 'http_api_debug', 'ping_debug_http_api_debug', 10, 5 );