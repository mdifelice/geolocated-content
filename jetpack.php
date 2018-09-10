<?php
/**
 * Compatibility with Jetpack.
 *
 * @package Geolocated_Content
 */

/**
 * Compatibility with the Jetpack Contact Form.
 */
add_filter( 'grunion_contact_form_redirect_url', 'geolocated_content_add_location_to_url' );

add_filter( 'grunion_contact_form_form_action', function( $url ) {
	return preg_replace( '/([^\/])#contact-form/', '$1/#contact-form', $url );
} );
