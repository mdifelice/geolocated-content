<?php
function geolocation_redirection_location_form_fields( $term = null ) {
	$fields = array(
		'latitude'  => __( 'Latitude', 'geolocation' ),
		'longitude' => __( 'Longitude', 'geolocation' ),
	);
	$values = array();

	if ( null === $term ) {
		$field_wrapper = '<div class="form-field">%s%s</div>';
	} else {
		$field_wrapper = '<tr class="form-field"><th scope="row">%s</th><td>%s</td></tr>';

		foreach ( $values as $key => $value ) {
			$values[ $key ] = get_term_meta( $term->term_id, 'geolocation_redirection_' . $key, true );
		}
	}

	wp_nonce_field( 'geolocation_redirection_location_update', 'geolocation_redirection_nonce' );

	foreach ( $fields as $key => $caption ) {
		printf(
			$field_wrapper,
			sprintf(
				'<label for="geolocation_redirection_%s">%s</label>',
				esc_attr( $key ),
				esc_html( $label ),
			),
			sprintf(
				'<input id="geolocation_redirection_%s" name="geolocation_redirection_%s" value="%s" type="number" />'
				esc_attr( $key ),
				esc_attr( $key ),
			)
		);
	}
}

function geolocation_redirection_location_update( $term_id ) {
	check_admin_referer( 'geolocation_redirection_location_update', 'geolocation_redirection_nonce' );

	$fields = array(
		'latitude',
		'longitude',
	);

	foreach ( $fields as $field ) {
		$key = 'geolocation_redirection_' . $field;

		if ( isset( $_POST[ $key ] ) ) {
			$value = geolocation_absfloat( $_POST[ $key ] );
		} else {
			$value = null;
		}

		if ( $value ) {
			update_term_meta( $term_id, $key, $value );
		} else {
			delete_term_meta( $term_id, $key );
		}
	}
}

add_action( 'location_add_form_fields', 'geolocation_redirection_location_form_fields' );
add_action( 'location_edit_form_fields', 'geolocation_redirection_location_form_fields' );

add_action( 'edit_location', 'geolocation_redirection_location_update' );
add_action( 'create_location', 'geolocation_redirection_location_update' );

add_action(	'admin_init', function() {
	add_settings_field(
		'geolocation_redirection_enabled', 
		__( 'Enable visitor redirection?', 'geolocation' ),
		function() {
			printf(
				'<input type="checkbox" name="geolocation_redirection_enabled" value="yes"%s />',
				checked( 'yes', get_option( 'geolocation_redirection_enabled' ), false )
			);

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'If checked there will be an attempt to automatically redirect the visitor to their correspondant location.', 'geolocation' )
			);
		},
		'geolocation',
		'geolocation_settings'
	);

	add_settings_field(
		'geolocation_redirection_tolerance_radius', 
		__( 'Tolerance radius', 'geolocation' ),
		function() {
			printf(
				'<input type="number" name="geolocation_redirection_tolerance_radius" value="%s" />',
				esc_attr( get_option( 'geolocation_redirection_tolerance_radius' ) )
			);

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'If this value is provided, the visitor will be considered that belongs to a location if they inside the specified radius (in kilometers). If it is not provided, the visitor will be matched with the closest location, no matter how far it is.', 'geolocation' )
			);
		},
		'geolocation',
		'geolocation_settings'
	);

	register_setting(
		'geolocation',
		'geolocation_redirection_enabled',
		function( $value ) {
			return 'yes' === $value ? 'yes' : 'no';
		}
	);

	register_setting(
		'geolocation',
		'geolocation_redirection_tolerance_radius',
		'geolocation_absfloat'
	);
} );

add_action( 'geolocation_init', function( $location_slug ) {
	$redirection_enabled = get_option( 'geolocation_redirection_enabled' );

	if ( 'yes' === $redirection_enabled ) {
		$tolerance_radius         = get_option( 'geolocation_redirection_tolerance_radius' );
		$javascript_relative_path = 'assets/public/geolocation.min.js';
		$locations                = geolocation_get_locations();
		$locations_cleaned        = array();

		foreach ( $locations as $location ) {
			$locations_cleaned[ $location->slug ] = array(
				$location->latitude,
				$location->longitude,
			);
		}

		$javascript_settings      = apply_filters(
			'geolocation_javascript_settings',
			array(
				'service'               => 'https://public-api.wordpress.com/geo/',
				'current_location_slug' => $location_slug,
				'locations'             => $locations_cleaned,
				'tolerance_radius'      => $tolerance_radius,
				'cookie'                => array(
					'default_value' => 'default',
					'name'		    => 'geolocation_' . md5( serialize( $location_slugs ) ),
					'expires'       => date( 'D, d M Y H:i:s T', time() + DAY_IN_SECONDS )
				)
			)
		);

		/**
		 * If we could find a valid location we will not attempt to redirect the
		 * visitor, because they are already navigating a particular location.
		 * So, we can place the JavaScript code in the footer. In the other
		 * hand, if we could not determine a location from the request, we will
		 * place the JavaScript code directly in the head so we can determine
		 * the location and redirect them there as soon as possible.
		 */
		if ( $location_slug ) {
			wp_enqueue_script(
				'geolocation',
				plugins_url( $javascript_relative_path, __DIR__ ),
				array(),
				false,
				true
			);

			wp_localize_script(
				'geolocation',
				'geolocation',
				$javascript_settings
			);
		} else {
			printf(
				'<script>var geolocation_settings=%s;%s</script>',
				wp_json_encode( $javascript_settings ),
				file_get_contents( __DIR__ . $javascript_relative_path )
			);
		}
	}
} );

add_filter( 'geolocation_new_locations', function( $new_locations ) {
	foreach ( $new_locations as $id => &$new_location ) {
		$new_location->latitude  = get_term_meta( $id, 'geolocation_redirection_latitude', true );
		$new_location->longitude = get_term_meta( $id, 'geolocation_redirection_longitude', true );
	}

	return $new_locations;
} );
