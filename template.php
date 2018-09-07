<?php
function geolocation_template_term_link( $args ) {
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
		$location_id = geolocation_get_visitor_location_id( true );
	}

	if ( $location_id ) {
		if ( $args['home'] ) {
			add_filter( 'geolocation_filter_term_link', '__return_false' );
		}

		$link = get_term_link( $location_id, 'location' );
		$text = '';

		if ( $args['home'] ) {
			remove_filter( 'geolocation_filter_term_link', '__return_false' );
		}

		if ( null !== $args['text'] ) {
			$text = $args['text'];
		} else {
			$location = geolocation_get_location( $location_id );

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
					'src' => true,
				),
			)
		);
	}

	return $output;
}

function geolocation_template_location_list( $args ) {
	$output = '';
	$args   = wp_parse_args(
		$args,
		array(
			'home' => false,
			'echo' => true,
		)
	);

	$locations = geolocation_get_locations();

	if ( ! empty( $locations ) ) {
		$output = '<ul>';

		foreach ( $locations as $location ) {
			$output .= '<li>';

			$output .= geolocation_template_location_link(
				array(
					'location_id' => $location->term_id,
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
				'a' => array(
					'src' => true,
				),
			)
		);
	}

	return $output;
}

function geolocation_template_redirection( $args ) {
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
		$location_id     = geolocation_get_visitor_location_id();

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
