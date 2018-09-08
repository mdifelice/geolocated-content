<?php
/**
 * Shortcode definitions.
 *
 * @package Geolocation
 */

add_shortcode( 'geolocation_redirect', function( $atts ) {
	$output = '';
	$atts   = shortcode_atts(
		array(
			'url'      => null,
			'location' => null,
		), $atts
	);

	if ( isset( $atts['location'] ) ) {
		$output = geolocation_template_redirection( array(
			'url'         => $atts['url'],
			'location_id' => $location_id,
			'echo'        => false,
		) );
	}

	return $output;
} );

add_shortcode( 'geolocation_location_list', function( $atts ) {
	$atts = shortcode_atts(
		array(
			'home' => 'yes',
		), $atts
	);

	$output  = '<div class="geolocation-location-list">';
	$output .= geolocation_template_location_list( array(
		'home' => 'yes' === $atts['home'],
		'echo' => false,
	) );
	$output .= '</div>';

	return $output;
} );

add_shortcode( 'geolocation_location_link', function( $atts ) {
	$atts = shortcode_atts(
		array(
			'text' => null,
			'home' => 'yes',
		), $atts
	);

	$output  = '<div class="geolocation-location-link">';
	$output .= geolocation_template_location_link( array(
		'text' => $atts['text'],
		'home' => 'yes' === $atts['home'],
		'echo' => false,
	) );
	$output .= '</div>';

	return $output;
} );

/**
 * Allows to exclude each shortcode of any location by using the
 * 'geolocation_allowed_locations' attribute.
 */
add_filter( 'pre_do_shortcode_tag', function( $html, $tag, $attr ) {
	if ( isset( $attr['geolocation_allowed_locations'] ) ) {
		$is_shortcode_allowed = true;
		$location_slug        = geolocation_get_visitor_location_slug( true );

		if ( $location_id ) {
			$is_shortcode_allowed = false;
			$allowed_locations    = array_map( function( $value ) {
				return strtolower( trim( $value ) );
			}, explode( ',', $atts['geolocationa_allowed_locations'] ) );

			if ( in_array( $location_slug, $allowed_location_slugs, true ) ) {
				$is_shortcode_allowed = true;
			}
		} else {
			$is_shortcode_allowed = false;
		}

		if ( ! $is_shortcode_allowed ) {
			/**
			 * Returning an empty string it shortcircuits the shortcode
			 * processing.
			 */
			$html = '';
		}
	}

	return $html;
}, 10, 3 );
