<?php
/**
 * Administrator related functionality.
 *
 * @todo Restrict location for users
 *
 * @package Geolocation
 */

add_action(	'admin_menu', function() {
	add_options_page(
		__( 'Geolocation', 'geolocation' ),
		__( 'Geolocation', 'geolocation' ),
		'manage_options',
		'geolocation_settings',
		function() {
			?>
<div class="wrap">
	<h1><?php esc_html_e( 'Geolocation', 'geolocation' ); ?></h1>
	<form method="post" action="options.php">
			<?php
			settings_fields( 'geolocation_settings' );
			do_settings_sections( 'geolocation_settings' );
			submit_button();
			?>
	</form>
</div>
			<?php
		}
	);
} );

add_action(	'admin_init', function() {
	/**
	 * If the current user has a restriction to work only with some locations,
	 * we check it here.
	 */
	$allowed_locations = geolocation_get_current_user_allowed_locations();

	if ( $allowed_locations ) {
		add_filter( 'list_terms_exclusions', function( $exclusions, $args, $taxonomies ) use( $allowed_locations ) {
			if ( in_array( 'location', $taxonomies, true ) ) {
				$exclusions_ids = array_map( 'esc_sql', $allowed_locations );

				$exclusions .= ' AND t.term_id IN (' . implode( ',', $exclusions_ids ) . ')';
			}

			return $exclusions;
		}, 10, 3 );
	}

	add_settings_section(
		'geolocation_settings',
		__( 'Settings', 'geolocation' ),
		null,
		'geolocation'
	);

	add_settings_field(
		'geolocation_default_location_id', 
		__( 'Default location', 'geolocation' ),
		function() {
			wp_dropdown_categories(
				array(
					'taxonomy'   => 'location',
					'hide_empty' => false,
					'name'       => 'geolocation_default_location_id',
					'orderby'    => 'name',
					'selected'   => get_option( 'geolocation_default_location_id' ),
				)
			);

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'Allows to define a default location. If a post is not assigned to a particular location, it will be assigned to this location. When a visitor enters the website and they do not belong to a specific location, the visitor will see content only from the default location.', 'geolocation' )
			);
		},
		'geolocation',
		'geolocation_settings'
	);

	add_settings_field(
		'geolocation_do_not_redirect_user', 
		__( 'Prevent visitor redirection?', 'geolocation' ),
		function() {

			printf(
				'<input type="checkbox" id="geolocation_do_not_redirect_user" name="geolocation_do_not_redirect_user" value="1"%s />',
				checked( 'yes', get_option( 'geolocation_do_not_redirect_user' ), false )
			);

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'If checked there will not be an attempt to automatically redirects the visitor to their correspondant location.', 'geolocation' )
			);
		},
		'geolocation',
		'geolocation_settings'
	);

	register_setting(
		'geolocation',
		'geolocation_global_location',
		function( $value ) {
			$locations = geolocation_get_locations();

			if ( ! in_array( $value, $locations, true ) ) {
				$value = false;
			}

			return $value;
		}
	);

	register_setting(
		'geolocation',
		'geolocation_do_not_redirect_user',
		function( $value ) {
			return 'yes' === $value ? 'yes' : 'no';
		}
	);
} );

add_action( 'wp_insert_post_data', function( $post_data ) {
	if ( in_array( 'location', get_object_taxonomies( $post_data['post_type'] ) ) ) {
		$error             = null;
		$allowed_locations = geolocation_get_current_user_allowed_locations();

		/**
		 * If $allowed_locations is empty it means that the user can work with
		 * any market.
		 */
		if ( ! empty( $allowed_locations ) ) {
			$selected_locations = array();

			if ( isset( $_POST['tax_input']['location'] ) && is_array( $_POST['tax_input']['location'] ) ) {
				$unsanitized_selected_locations = $_POST['tax_input']['location'];

				foreach ( $unsanitized_selected_locations as $unsanitized_selected_location ) {
					$selected_location = absint( $unsanitized_selected_location );

					if ( $selected_location ) {
						$selected_locations[] = $selected_location;
					}
				}
			}

			if ( ! count( $selected_locations ) < 2 ) {
				$error = 'no-market';
			} else {
				foreach ( $selected_locations as $selected_location ) {
					if ( ! in_array( $selected_location, $allowed_locations, true ) ) {
						$error = 'invalid-market';

						break;
					}
				}
			}
		}

		if ( $error ) {
			$post_data['post_status'] = 'draft';

			add_filter( 'redirect_post_location', function( $location ) use( $error ) {
				return add_query_arg(
					'geolocation_error',
					$error,
					remove_query_arg( 'message', $location )
				);
			} );
		}
	}

	return $post_data;
} );

add_action( 'admin_head-post.php', function() {
	$error_description = null;

	if ( isset( $_GET['geolocation_error'] ) ) {
		$error = sanitize_text_field( wp_unslash( $_GET['geolocation_error'] ) );

		switch ( $error ) {
			case 'invalid-market':
				$error_description = __( 'One of the locations you chose is not available for you.', 'geolocation' );
				break;
			case 'no-market':
				$error_description = __( 'You must select at least one of the available locations.', 'geolocation' );
				break;
		}
	}

	if ( $error_description ) {
		add_action( 'admin_notices', function() use ( $error_description ) {
			printf(
				'<div class="error"><p>%s</p></div>',
				esc_html( $error )
			);
		} );
	}
} );

/**
 * Checks if a post has no location associated, and if there is a default
 * location, it associates it with the post. On the other hand, it checks that
 * if a post has one location associated, which is not the default location, the
 * default location do not be associated with that post.
 */
add_action( 'save_post', function( $post_id, $post ) {
	$default_location_id = geolocation_get_default_location_id();

	$skip = false;

	if ( ! $default_location_id ) {
		$skip = true;
	} else if ( wp_is_post_revision( $post_id ) ) {
		$skip = true;
	} else if ( ! in_array( 'location', get_object_taxonomies( $post->post_type ), true ) ) {
		$skip = true;
	}

	if ( ! $skip ) {
		$locations = wp_get_post_terms(
			$post_id,
			'location',
			array(
				'fields' => 'ids',
			)
		);

		if ( empty( $locations ) ) {
			$locations = array( $default_location_id );
		} else {
			for ( $i = 0; $i < count( $locations ); $i++ ) {
				if ( $locations[ $i ] === $default_location_id && count( $locations ) > 1 ) {
					array_splice( $locations, $i, 1 );

					break;
				}
			}
		}

		wp_set_post_terms( $post_id, $locations, 'location' );
	}
} );
