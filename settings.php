<?php
/**
 * Settings extension.
 *
 * @package Geolocated_Content
 */

/**
 * Function to be hooked to the pre_option_$option_name filter.
 *
 * @param mixed  $value Original option value.
 * @param string $name  Name of option.
 *
 * @return mixed The value of the option.
 */
function geolocated_content_settings_pre_option( $value, $name ) {
	$location_id = geolocated_content_get_current_location_id();

	if ( $location_id ) {
		$value = apply_filters( 'geolocated_content_settings_pre_option_' . $name, geolocated_content_settings_get_option( $name, $location_id ), $location_id, $name );
	}

	return $value;
}

/**
 * Returns the meta key used to store an option for a specific location.
 *
 * @param string $option_name The name of the option.
 *
 * @return string The meta key for that option.
 */
function geolocated_content_settings_get_option_meta_key( $option_name ) {
	return 'geolocated_content_' . $option_name;
}

/**
 * Returns the option value for an specific location.
 *
 * @param string $option_name The option name.
 * @param int    $location_id The location ID.
 *
 * @return mixed The option value. FALSE if it is not found.
 */
function geolocated_content_settings_get_option( $option_name, $location_id ) {
	$meta_key = geolocated_content_settings_get_option_meta_key( $option_name );
	$value    = false;

	if ( metadata_exists( 'term', $location_id, $meta_key ) ) {
		$value = get_term_meta( $location_id, $meta_key, true );
	}

	return $value;
}

/**
 * Sets the option value for an specific location.
 *
 * @param string $option_name The option name.
 * @param int    $location_id The location ID.
 * @param mixed  $value       The option value.
 *
 * @return boolean Returns TRUE if the option could be set.
 */
function geolocated_content_settings_set_option( $option_name, $location_id, $value ) {
	$meta_key = geolocated_content_settings_get_option_meta_key( $option_name );

	return ! ! update_term_meta( $location_id, $meta_key, $value );
}

/**
 * Deletes the option value for a specific location.
 *
 * @param string $option_name The option name.
 * @param int    $location_id The location ID.
 *
 * @return boolean TRUE on success, FALSE otherwise.
 */
function geolocated_content_settings_delete_option( $option_name, $location_id ) {
	$status   = false;
	$meta_key = geolocated_content_settings_get_option_meta_key( $option_name );

	if ( metadata_exists( 'term', $location_id, $meta_key ) ) {
		$status = delete_term_meta( $location_id, $meta_key );
	}

	return $status;
}

add_action( 'admin_init', function() {
	add_settings_field(
		'geolocated_content_settings_enabled',
		__( 'Enable geolocated settings?', 'geolocated-content' ),
		function() {
			printf(
				'<input type="checkbox" name="geolocated_content_settings_enabled" value="yes"%s />',
				checked( 'yes', get_option( 'geolocated_content_settings_enabled' ), false )
			);

			printf(
				'<p class="description">%s</p>',
				esc_html__( 'If you enable this, settings from other plugins can be modified to have different values for each location.', 'geolocated-content' )
			);
		},
		'geolocated-content',
		'geolocated-content'
	);

	register_setting(
		'geolocated-content',
		'geolocated_content_settings_enabled',
		function( $value ) {
			return 'yes' === $value ? 'yes' : 'no';
		}
	);
}, 11 );

/**
 * In order to avoid searching for every option, only saved options through
 * this page will be filtered based on the location. To do that, we use an
 * special option called 'geolocated_content_settings_cache' which contains all
 * the options that were overriden.
 */
$geolocated_content_settings_enabled = get_option( 'geolocated_content_settings_enabled' );

if ( 'yes' === $geolocated_content_settings_enabled ) {
	/**
	 * Adds the location selector to the admin bar.
	 */
	add_action( 'admin_bar_menu', function( $wp_admin_bar ) {
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			$locations = geolocated_content_get_locations();

			if ( ! empty( $locations ) ) {
				$current_location_id   = geolocated_content_get_current_user_location_id();
				$default_location_name = __( 'Default', 'geolocated-content' );
				$current_location_name = $default_location_name;

				if ( isset( $locations[ $current_location_id ] ) ) {
					$current_location_name = $locations[ $current_location_id ]->name;
				}

				$node = array(
					'id'     => 'geolocated_content_location',
					'parent' => false,
					// translators: location name.
					'title'  => sprintf( __( 'Current location: %s', 'geolocated-content' ), $current_location_name ),
				);

				$wp_admin_bar->add_node( $node );

				$node = array(
					'href'   => remove_query_arg( 'geolocated_content_location_id' ),
					'id'     => 'geolocated_content_location_default',
					'parent' => 'geolocated_content_location',
					'title'  => $default_location_name,
				);

				$wp_admin_bar->add_node( $node );

				foreach ( $locations as $location ) {
					$node = array(
						'href'   => add_query_arg( 'geolocated_content_location_id', $location->term_id ),
						'id'     => 'geolocated_content_location_' . $location->term_id,
						'parent' => 'geolocated_content_location',
						'title'  => $location->name,
					);

					$wp_admin_bar->add_node( $node );
				}
			}
		}
	}, 999 );

	add_action( 'admin_init', function() {
		global $wp_registered_settings;

		$geolocated_content_settings_cache = array();

		foreach ( $wp_registered_settings as $option_name => $args ) {
			if ( 0 !== strpos( $option_name, 'geolocated_content_' ) ) {
				$geolocated_content_settings_cache[ $option_name ] = true;
			}
		}

		/**
		 * We only save the geolocated content settings if they were modified.
		 */
		if ( get_option( 'geolocated_content_settings_cache' ) !== $geolocated_content_settings_cache ) {
			update_option( 'geolocated_content_settings_cache', $geolocated_content_settings_cache );
		}
	}, 999 );

	/**
	 * Here we enqueue a script and a style files that allow us to identify
	 * visually which options are being altered by location.
	 */
	add_action( 'admin_enqueue_scripts', function() {
		if ( current_user_can( 'manage_options' ) ) {
			wp_enqueue_style(
				'geolocated-content-settings',
				plugins_url( 'assets/administrator/css/settings.css', __FILE__ )
			);

			wp_enqueue_style( 'wp-jquery-ui-dialog' );

			wp_enqueue_script(
				'geolocated-content-settings',
				plugins_url( 'assets/administrator/js/settings.js', __FILE__ )
			);

			wp_enqueue_script( 'jquery-ui-dialog' );

			wp_localize_script(
				'geolocated-content-settings',
				'geolocated_content_settings',
				array(
					'setting_names' => array_keys( get_option( 'geolocated_content_settings_cache' ) ),
					'nonces'        => array(
						'load'   => wp_create_nonce( 'geolocated_content_settings_load' ),
						'delete' => wp_create_nonce( 'geolocated_content_settings_delete' ),
					),
					'i18n'          => array(
						'delete'         => __( 'Delete', 'geolocated-content' ),
						'reload'         => __( 'Reload', 'geolocated-content' ),
						'confirm_delete' => __( 'Are you sure to delete this value?', 'geolocated-content' ),
						'modal_loading'  => __( 'Loading...', 'geolocated-content' ),
						// translators: setting name.
						'modal_title'    => __( 'Viewing values for setting "%s"...', 'geolocated-content' ),
						'error_delete'   => __( 'Cannot delete setting, maybe it does not exist.', 'geolocated-content' ),
						'error_unknown'  => __( 'Unknown error. Please, try again later.', 'geolocated-content' ),
						'error_setting'  => __( 'Unknown setting.', 'geolocated-content' ),
					),
				)
			);
		}
	} );

	/**
	 * Added dialog for visualization of other location settings.
	 */
	add_action( 'admin_footer', function() {
		if ( current_user_can( 'manage_options' ) ) {
			print( '<div id="geolocated-content-settings-modal"></div>' );
		}
	} );

	/**
	 * AJAX handler for deleting setting.
	 */
	add_action( 'wp_ajax_geolocated_content_settings_delete', function() {
		if ( current_user_can( 'manage_options' ) ) {
			check_ajax_referer( 'geolocated_content_settings_delete' );

			if ( isset( $_POST['geolocated_content_settings_setting_name'] ) ) {
				$setting = sanitize_text_field( wp_unslash( $_POST['geolocated_content_settings_setting_name'] ) );
			} else {
				$setting = null;
			}

			if ( isset( $_POST['geolocated_content_settings_location_id'] ) ) {
				$location_id = absint( wp_unslash( $_POST['geolocated_content_settings_location_id'] ) );
			} else {
				$location_id = null;
			}

			$response = false;

			if ( $setting && null !== $location_id ) {
				if ( $location_id ) {
					$response = geolocated_content_settings_delete_option( $setting, $location_id );
				} else {
					$response = delete_option( $location_id );
				}
			}

			wp_send_json( $response );
		}
	} );

	/**
	 * AJAX handler for loading setting for all locations.
	 */
	add_action( 'wp_ajax_geolocated_content_settings_load', function() {
		if ( current_user_can( 'manage_options' ) ) {
			check_ajax_referer( 'geolocated_content_settings_load' );

			if ( isset( $_POST['geolocated_content_settings_setting_name'] ) ) {
				$setting = sanitize_text_field( wp_unslash( $_POST['geolocated_content_settings_setting_name'] ) );
			} else {
				$setting = null;
			}

			$response = null;

			if ( $setting ) {
				$response = array();

				remove_filter( 'pre_option_' . $setting, 'geolocated_content_settings_pre_option', 10, 3 );

				$response[] = array(
					'id'            => 0,
					'name'          => __( 'Default', 'geolocated-content' ),
					'setting_value' => var_export( get_option( $setting ), true ),
				);

				add_filter( 'pre_option_' . $setting, 'geolocated_content_settings_pre_option', 10, 3 );

				$locations = geolocated_content_get_locations();

				foreach ( $locations as $location ) {
					$response[] = array(
						'id'            => $location->term_id,
						'name'          => $location->name,
						'setting_value' => var_export( geolocated_content_settings_get_option( $setting, $location->term_id ), true ),
					);
				}
			}

			wp_send_json( $response );
		}
	} );

	/**
	 * We add a filter for each option previously registered in
	 * "geolocated_content_settings_cache".
	 */
	$geolocated_content_settings_cache = get_option( 'geolocated_content_settings_cache' );

	if ( $geolocated_content_settings_cache ) {
		foreach ( $geolocated_content_settings_cache as $option_name => $option_value ) {
			add_filter( 'pre_option_' . $option_name, 'geolocated_content_settings_pre_option', 10, 3 );

			add_filter( 'pre_update_option_' . $option_name, function( $value, $old_value, $option_name ) {
				if ( current_user_can( 'manage_options' ) ) {
					$location_id = geolocated_content_get_current_user_location_id();

					if ( $location_id ) {
						$current_value = geolocated_content_settings_get_option( $option, $location_id );

						if ( $current_value !== $value ) {
							geolocated_content_settings_set_option( $option_name, $location_id, $value );
						}

						/**
						 * Returning the old value we make sure the original
						 * option is not updated.
						 *
						 * @link https://codex.wordpress.org/Plugin_API/Filter_Reference/pre_update_option_(option_name)
						 */
						$value = $old_value;
					}
				}

				return $value;
			}, 10, 3 );
		}
	}
}
