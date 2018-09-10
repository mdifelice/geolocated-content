<?php
/**
 * Redirection extension. Allows visitors to be redirected to their
 * correspondant location using a geolocation webservice.
 *
 * @package Geolocated_Content
 */

/**
 * Prints location meta fields.
 *
 * @param WP_Term $term Optional. The term which is going to be edited or NULL
 *                      in case of a nwe term.
 */
function geolocated_content_redirection_location_form_fields( $term = null ) {
	$fields = array(
		'latitude'  => __( 'Latitude', 'geolocated-content' ),
		'longitude' => __( 'Longitude', 'geolocated-content' ),
	);
	$values = array();

	if ( is_a( $term, 'WP_Term' ) ) {
		$field_wrapper = '<tr class="form-field"><th scope="row">%s</th><td>%s</td></tr>';

		foreach ( $fields as $key => $caption ) {
			$values[ $key ] = get_term_meta( $term->term_id, 'geolocated_content_redirection_' . $key, true );
		}
	} else {
		$field_wrapper = '<div class="form-field">%s%s</div>';
	}

	wp_nonce_field( 'geolocated_content_redirection_location_update', 'geolocated_content_redirection_nonce' );

	foreach ( $fields as $key => $caption ) {
		printf(
			$field_wrapper,
			sprintf(
				'<label for="geolocated_content_redirection_%s">%s</label>',
				esc_attr( $key ),
				esc_html( $caption )
			),
			sprintf(
				'<input id="geolocated_content_redirection_%s" name="geolocated_content_redirection_%s" value="%s" type="number" class="widefat" step="0.000001" />',
				esc_attr( $key ),
				esc_attr( $key ),
				esc_attr( isset( $values[ $key ] ) ? $values[ $key ] : '' )
			)
		);
	}
}

/**
 * Updates location metadata.
 *
 * @param int $term_id The location ID.
 */
function geolocated_content_redirection_location_update( $term_id ) {
	check_admin_referer( 'geolocated_content_redirection_location_update', 'geolocated_content_redirection_nonce' );

	$fields = array(
		'latitude',
		'longitude',
	);

	foreach ( $fields as $field ) {
		$key = 'geolocated_content_redirection_' . $field;

		if ( isset( $_POST[ $key ] ) ) {
			$value = floatval( $_POST[ $key ] );
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

add_action( 'location_add_form_fields', 'geolocated_content_redirection_location_form_fields' );
add_action( 'location_edit_form_fields', 'geolocated_content_redirection_location_form_fields' );

add_action( 'edit_location', 'geolocated_content_redirection_location_update' );
add_action( 'create_location', 'geolocated_content_redirection_location_update' );

add_action( 'admin_init', function() {
	add_settings_field(
		'geolocated_content_redirection_enabled',
		__( 'Enable visitor redirection?', 'geolocated-content' ),
		function() {
			printf(
				'<input type="checkbox" name="geolocated_content_redirection_enabled" value="yes"%s />',
				checked( 'yes', get_option( 'geolocated_content_redirection_enabled' ), false )
			);

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'If checked there will be an attempt to automatically redirect the visitor to their correspondant location.', 'geolocated-content' )
			);
		},
		'geolocated-content',
		'geolocated-content'
	);

	add_settings_field(
		'geolocated_content_redirection_tolerance_radius',
		__( 'Tolerance radius', 'geolocated-content' ),
		function() {
			printf(
				'<input type="number" name="geolocated_content_redirection_tolerance_radius" value="%s" class="widefat" />',
				esc_attr( get_option( 'geolocated_content_redirection_tolerance_radius' ) )
			);

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'If this value is provided, the visitor will be considered that belongs to a location if they inside the specified radius (in kilometers). If it is not provided, the visitor will be matched with the closest location, no matter how far it is.', 'geolocated-content' )
			);
		},
		'geolocated-content',
		'geolocated-content'
	);

	register_setting(
		'geolocated-content',
		'geolocated_content_redirection_enabled',
		function( $value ) {
			return 'yes' === $value ? 'yes' : 'no';
		}
	);

	register_setting(
		'geolocated-content',
		'geolocated_content_redirection_tolerance_radius',
		function( $value ) {
			if ( '' !== $value ) {
				$value = geolocated_content_absfloat( $value );
			}

			return $value;
		}
	);
}, 11 );

add_action( 'geolocated_content_init', function( $location_slug ) {
	$redirection_enabled = get_option( 'geolocated_content_redirection_enabled' );

	if ( 'yes' === $redirection_enabled ) {
		$tolerance_radius         = get_option( 'geolocated_content_redirection_tolerance_radius' );
		$javascript_relative_path = 'assets/public/js/geolocated-content.min.js';
		$locations                = geolocated_content_get_locations();
		$locations_cleaned        = array();

		foreach ( $locations as $location ) {
			$locations_cleaned[ $location->slug ] = array(
				$location->latitude,
				$location->longitude,
			);
		}

		$javascript_settings = apply_filters(
			'geolocated_content_javascript_settings',
			array(
				'service'               => 'https://public-api.wordpress.com/geo/',
				'current_location_slug' => $location_slug,
				'locations'             => $locations_cleaned,
				'tolerance_radius'      => $tolerance_radius,
				'cookie'                => array(
					'default_value' => 'default',
					'name'          => 'geolocated_content_' . md5( serialize( $locations_cleaned ) ),
					'expires'       => date( 'D, d M Y H:i:s T', time() + DAY_IN_SECONDS ),
				),
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
			add_action( 'wp_enqueue_scripts', function() use ( $javascript_relative_path, $javascript_settings ) {
				wp_enqueue_script(
					'geolocated-content',
					plugins_url( $javascript_relative_path, __FILE__ ),
					array(),
					'1.0.0',
					true
				);

				wp_localize_script(
					'geolocated-content',
					'geolocated_content',
					$javascript_settings
				);
			} );
		} else {
			add_action( 'wp_head', function() use ( $javascript_settings, $javascript_relative_path ) {
				printf(
					'<script>var geolocated_content=%s;%s</script>',
					wp_json_encode( $javascript_settings ),
					file_get_contents( __DIR__ . '/' . $javascript_relative_path )
				);
			} );
		}
	}
} );

add_filter( 'geolocated_content_new_locations', function( $new_locations ) {
	foreach ( $new_locations as $id => &$new_location ) {
		$new_location->latitude  = get_term_meta( $id, 'geolocated_content_redirection_latitude', true );
		$new_location->longitude = get_term_meta( $id, 'geolocated_content_redirection_longitude', true );
	}

	return $new_locations;
} );
