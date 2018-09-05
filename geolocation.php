<?php
/**
 * Plugin Name: Geolocation
 * Description: Allows to deliver different content to users in different locations.
 * Plugin URI:  https://github.com/mdifelice/geolocation
 * Author:      Martín Di Felice
 * Author URI:  https://github.com/mdifelice
 * Text Domain: geolocated-content
 * Domain Path: /languages
 * Version:     1.0.0
 * License:     GPL2
 *
 * Geolocation is free software: you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation, either version 2 of the License, or any later version.
 *
 * Geolocation is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * Geolocation. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 *
 * @package Geolocation
 */

if ( ! geolocation_is_admin() ) {
	$geolocation_locations   = geolocation_get_locations();
	$geolocation_request_uri = null;

	if ( isset( $_SERVER['REQUEST_URI'] ) ) {
		$geolocation_request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	}

	/**
	 * Add slash to avoid duplicated URLs. If we don't do this, we will have two
	 * URLs for each request. We 301-redirects URLs with no trailing slashes.
	 */
	if( ! empty( $geolocation_request_uri ) && geolocation_must_add_slash( $_SERVER['REQUEST_URI'] ) &&
		$_SERVER['REQUEST_URI'][ strlen( $_SERVER['REQUEST_URI'] ) - 1] != '/' ) {
		$url = $_SERVER['REQUEST_URI'] . '/';
		wp_safe_redirect( $geolocation_request_uri . '/', 301 );

		exit;
	}

	/**
	  * Remove market from URI to let WP parse correctly the request
	  * We leave $_SERVER['REQUEST_URI'] so when WordPress parses it, it
	  * does it like a regular URL. But, right here, the Batcache plugin
	  * already executed, so each request will have a different cache.
	  * Also, we leave the original request in
	  * $_SERVER['ORIGINAL_REQUEST_URI'] if some function want to use
	  * later.
	  */
	if( $geolocation_market = geolocation_extract_market( $_SERVER['REQUEST_URI'] ) ) {
		$_SERVER['ORIGINAL_REQUEST_URI'] 	= $_SERVER['REQUEST_URI'];
		$_SERVER['REQUEST_URI'] 			= preg_replace( '/^\/' . preg_quote( $geolocation_market ) . '/', '', $_SERVER['REQUEST_URI'], 1 );
	}
}

