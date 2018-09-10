<?php
/**
 * Template functions.
 *
 * @package Geolocated_Content
 */

/**
 * Prints a link to a location.
 *
 * @param mixed $args Options.
 *
 * @return string The HTML text.
 */
function geolocated_content_template_location_link( $args ) {
	$output = '';
	$args   = wp_parse_args(
		$args,
		array(
			'location_id' => null,
			'text'        => null,
			'home'        => false,
			'echo'        => true,
		)
	);

	if ( $args['location_id'] ) {
		$location_id = $args['location_id'];
	} else {
		$location_id = geolocated_content_get_visitor_location_id( true );
	}

	if ( $location_id ) {
		if ( ! $args['home'] ) {
			add_filter( 'geolocated_content_filter_term_link', '__return_false' );
		}

		$link = get_term_link( $location_id, 'location' );
		$text = '';

		if ( ! $args['home'] ) {
			remove_filter( 'geolocated_content_filter_term_link', '__return_false' );
		}

		if ( null !== $args['text'] ) {
			$text = $args['text'];
		} else {
			$location = geolocated_content_get_location( $location_id );

			if ( $location ) {
				$text = $location->name;
			}
		}

		if ( is_wp_error( $link ) ) {
			$link = null;
		}
	} else {
		$link = null;
	}

	if ( $link ) {
		$output = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $link ),
			$text
		);
	}

	if ( $args['echo'] ) {
		echo wp_kses(
			$output,
			array(
				'a' => array(
					'href' => true,
				),
			)
		);
	}

	return $output;
}

/**
 * Prints a list of location links.
 *
 * @param mixed $args Options.
 *
 * @return string The HTML text.
 */
function geolocated_content_template_location_list( $args ) {
	$output = '';
	$args   = wp_parse_args(
		$args,
		array(
			'home' => false,
			'echo' => true,
		)
	);

	$locations = geolocated_content_get_locations();

	if ( ! empty( $locations ) ) {
		$output = '<ul>';

		foreach ( $locations as $location ) {
			$output .= '<li>';

			$output .= geolocated_content_template_location_link(
				array(
					'location_id' => $location->term_id,
					'home'        => $args['home'],
					'echo'        => false,
				)
			);

			$output .= '</li>';
		}

		$output .= '</ul>';
	}

	if ( $args['echo'] ) {
		echo wp_kses(
			$output,
			array(
				'ul' => array(),
				'li' => array(),
				'a'  => array(
					'href' => true,
				),
			)
		);
	}

	return $output;
}

/**
 * Redirects to another URL if the location is not the specified.
 *
 * @param mixed $args Options.
 *
 * @return string The HTML text.
 */
function geolocated_content_template_redirection( $args ) {
	$output = '';
	$args   = wp_parse_args(
		$args,
		array(
			'url'         => null,
			'location_id' => null,
			'echo'        => true,
		)
	);

	if ( ! empty( $args['url'] ) ) {
		$redirection_url = esc_url( $args['url'] );
		$location_id     = geolocated_content_get_visitor_location_id();

		if ( $location_id === $args['location_id'] ) {
			$output = sprintf(
				'<script>window.location.href=%s;</script>',
				wp_json_encode( $redirection_url )
			);
		}
	}

	if ( $args['echo'] ) {
		echo wp_kses(
			$output,
			array(
				'script' => array(),
			)
		);
	}

	return $output;
}
