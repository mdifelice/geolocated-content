<?php
/**
 * User restriction functionality. Basically it allows users which are not
 * administrators to work with specific locations.
 */

function geolocation_user_restriction_profile( $user ) {
	wp_nonce_field( 'geolocation_user_restriction_user_user_update', 'geolocation_user_restriction_nonce' );

	?>
<table class="form-table">
	<tr>
		<th><label for="geolocation_allowed_location_ids"><?php esc_html_e( 'Allowed Locations?', 'geolocation' ); ?></label></th>
		<td>
			<?php geolocation_dropdown( geolocation_get_user_allowed_location_ids( $user->ID ), true ); ?>
		</td>
	</tr>
</table>
	<?php
}

function geolocation_user_restriction_update( $user_id ) {
	check_admin_referer( 'geolocation_user_restriction_user_update', 'geolocation_user_restriction_nonce' );

	if ( current_user_can( 'edit_user', $user_id ) ) { 
		$allowed_location_ids = null;

		if ( isset( $_POST['allowed_location_ids'] ) ) {
			$allowed_location_ids = array_filter( array_map( 'absint', $_POST['allowed_location_ids'] ) );
		}

		if ( $allowed_location_ids ) {
			update_user_meta( $user_id, 'geolocation_allowed_location_ids', $allowed_location_ids );
		} else {
			delete_user_meta( $user_id, 'geolocation_allowed_location_ids' );
		}
	}
}

add_action( 'show_user_profile', 'geolocation_user_restriction_profile' );
add_action( 'edit_user_profile', 'geolocation_user_restriction_profile' );

add_action( 'personal_options_update', 'geolocation_user_restriction_update' );
add_action( 'edit_user_profile_update', 'geolocation_user_restriction_update' );

add_action(	'admin_init', function() {
	/**
	 * If the current user has a restriction to work only with some locations,
	 * we check it here.
	 */
	$allowed_location_ids = geolocation_get_current_user_allowed_location_ids();

	if ( $allowed_location_ids ) {
		add_filter( 'list_terms_exclusions', function( $exclusions, $args, $taxonomies ) use( $allowed_location_ids ) {
			if ( in_array( 'location', $taxonomies, true ) ) {
				$exclusions_ids = array_map( 'esc_sql', $allowed_location_ids );

				$exclusions .= ' AND t.term_id IN (' . implode( ',', $exclusions_ids ) . ')';
			}

			return $exclusions;
		}, 10, 3 );
	}
} );

add_action( 'wp_insert_post_data', function( $post_data ) {
	if ( in_array( 'location', get_object_taxonomies( $post_data['post_type'] ) ) ) {
		$error                = null;
		$allowed_location_ids = geolocation_get_current_user_allowed_location_ids();

		/**
		 * If $allowed_location_ids is empty it means that the user can work
		 * with any market.
		 */
		if ( ! empty( $allowed_location_ids ) ) {
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
				$error = 'no-location';
			} else {
				foreach ( $selected_locations as $selected_location ) {
					if ( ! in_array( $selected_location, $allowed_location_ids, true ) ) {
						$error = 'invalid-location';

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
			case 'invalid-location':
				$error_description = __( 'One of the locations you chose is not available for you.', 'geolocation' );
				break;
			case 'no-location':
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

add_action( 'manage_users_custom_column', function( $value, $column_name, $user_id ) {
    if ( 'allowed_locations' === $column_name ) {
		$allowed_location_ids = geolocation_get_user_allowed_location_ids( $user_id );

		$value = '';

		if ( empty( $allowed_location_ids ) ) {
			$value = __( 'All locations', 'geolocation' );
		} else {
			foreach ( $allowed_location_ids as $location_id ) {
				$location = geolocation_get_location( $location_id );

				if ( $location ) {
					$value .= ( empty( $value ) ? '' : '<br/>' ) . esc_html( $location->name );
				}
			}
		}
	}

	return $value;
} );

add_filter( 'geolocation_pre_get_posts_locations', function( $location_ids, $query, $operator, $is_admin ) {
	if ( $is_admin ) {
		/**
		 * adm. We will apply this filter to all users except administrators
		 * (that's why we check if they have the 'manage_options' capability).
		 *
		 * If that attribute is empty, it means that the user can work with any
		 * location.
		 */
		if ( ! current_user_can( 'manage_options' ) ) {
			$allowed_location_ids = geolocation_get_current_user_allowed_location_ids();

			if ( ! empty( $allowed_location_ids ) ) {
				if ( empty( $location_ids ) ) {
					$location_ids = $allowed_location_ids;
				} else {
					$filtered_location_ids    = array();
					$allowed_location_ids_map = array_flip( $allowed_location_ids );

					foreach ( $location_ids as $index => $location_id ) {
						if ( isset( $allowed_location_ids_map[ $location_id ] ) ) {
							$filtered_location_ids[] = $location_id;
						}
					}

					$location_ids = $filtered_location_ids;
				}
			}
		}
	}

	return $location_ids;
}, 10, 4 );

add_filter( 'manage_users_columns', function( $columns ) {
    $columns['allowed_locations'] = __( 'Allowed locations', 'geolocation' );

    return $columns;
} );
